<?php

namespace App\Services;

use App\Enums\LandlordRequestStatus;
use App\Enums\UserRole;
use App\Models\LandlordRequest;
use App\Models\User;
use App\Notifications\LandlordRequestApprovedNotification;
use App\Notifications\LandlordRequestRejectedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LandlordRequestService
{
    private const DUPLICATE_MESSAGE = 'Você já possui uma solicitação pendente de análise.';

    /**
     * Registra uma nova solicitação com status "pending".
     *
     * Com LANDLORD_AUTO_APPROVE=true (MVP), a solicitação já nasce aprovada e o
     * usuário vira locador na mesma transação. Os documentos passam a ser opcionais.
     *
     * @param  array{
     *     full_name: string,
     *     cpf: string,
     *     birth_date: string,
     *     document_type?: string|null,
     *     email?: string|null,
     *     primary_phone: string,
     *     secondary_phone?: string|null,
     *     commercial_phone?: string|null,
     *     document_front?: UploadedFile|null,
     *     document_back?: UploadedFile|null,
     *     message?: string|null
     * }  $data
     */
    public function submit(User $user, array $data): LandlordRequest
    {
        $storedPaths = [];
        $approvedNow = false;

        try {
            $landlordRequest = DB::transaction(function () use ($user, $data, &$storedPaths, &$approvedNow): LandlordRequest {
                // Bloqueia a linha do usuário para serializar envios simultâneos dele.
                $applicant = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($applicant->role !== UserRole::CLIENT) {
                    throw $this->error('Somente clientes podem solicitar acesso como locador.');
                }

                $hasPending = $applicant->landlordRequests()
                    ->where('status', LandlordRequestStatus::PENDING->value)
                    ->exists();

                if ($hasPending) {
                    throw $this->error(self::DUPLICATE_MESSAGE);
                }

                try {
                    $request = new LandlordRequest;
                    $request->fill([
                        'full_name' => $data['full_name'],
                        'cpf' => $data['cpf'],
                        'birth_date' => $data['birth_date'],
                        'document_type' => $data['document_type'] ?? null,
                        'email' => $data['email'] ?? null,
                        'primary_phone' => $data['primary_phone'],
                        'secondary_phone' => $data['secondary_phone'] ?? null,
                        'commercial_phone' => $data['commercial_phone'] ?? null,
                        'message' => $data['message'] ?? null,
                    ]);
                    $request->forceFill([
                        'user_id' => $applicant->id,
                        'status' => LandlordRequestStatus::PENDING,
                    ])->save();
                } catch (UniqueConstraintViolationException) {
                    // Rede de segurança: o índice único parcial do banco barrou uma corrida.
                    throw $this->error(self::DUPLICATE_MESSAGE);
                }

                $documentPaths = [];
                foreach (['front', 'back'] as $side) {
                    $field = 'document_'.$side;
                    $file = $data[$field] ?? null;

                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $path = $file->store('landlord-requests/'.$request->id, 'local');
                    if ($path === false) {
                        throw new RuntimeException('Não foi possível armazenar os documentos enviados.');
                    }

                    $storedPaths[] = $path;
                    $documentPaths[$field.'_path'] = $path;
                }

                if ($documentPaths !== []) {
                    $request->forceFill($documentPaths)->save();
                }

                // MVP: aprovação automática, sem painel de administração.
                if (config('landlord.auto_approve')) {
                    $request->forceFill([
                        'status' => LandlordRequestStatus::APPROVED,
                        'reviewed_at' => now(),
                        'rejection_reason' => null,
                    ])->save();

                    // "role" não está no $fillable de propósito; só este fluxo pode alterá-lo.
                    $applicant->forceFill(['role' => UserRole::LANDLORD])->save();

                    $approvedNow = true;
                }

                return $request;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== [] && ! Storage::disk('local')->delete($storedPaths)) {
                throw new RuntimeException(
                    'Não foi possível limpar os documentos após a falha no cadastro da solicitação.',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }

        if ($approvedNow) {
            $this->deleteApplicantDocuments($landlordRequest);
            $this->notifyApplicant($user, new LandlordRequestApprovedNotification($landlordRequest->id));
        }

        return $landlordRequest;
    }

    /**
     * Aprova a solicitação e habilita o papel de locador, na mesma transação.
     */
    public function approve(LandlordRequest $landlordRequest, User $reviewer): LandlordRequest
    {
        $approved = DB::transaction(function () use ($landlordRequest, $reviewer): LandlordRequest {
            $locked = $this->lockPending($landlordRequest);

            $applicant = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();

            if ($applicant->role === UserRole::ADMIN) {
                throw $this->error('Esta solicitação pertence a um administrador e não pode ser aprovada.');
            }

            $locked->forceFill([
                'status' => LandlordRequestStatus::APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            // "role" não está no $fillable de propósito; só este fluxo pode alterá-lo.
            $applicant->forceFill(['role' => UserRole::LANDLORD])->save();

            return $locked;
        });

        $this->deleteApplicantDocuments($approved);
        $this->notifyApplicant($approved->user, new LandlordRequestApprovedNotification($approved->id));

        return $approved;
    }

    /**
     * Recusa a solicitação. Não altera o papel do usuário.
     */
    public function reject(LandlordRequest $landlordRequest, User $reviewer, ?string $reason = null): LandlordRequest
    {
        $rejected = DB::transaction(function () use ($landlordRequest, $reviewer, $reason): LandlordRequest {
            $locked = $this->lockPending($landlordRequest);

            $locked->forceFill([
                'status' => LandlordRequestStatus::REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $locked;
        });

        $this->deleteApplicantDocuments($rejected);
        $this->notifyApplicant(
            $rejected->user,
            new LandlordRequestRejectedNotification($rejected->id, $rejected->rejection_reason),
        );

        return $rejected;
    }

    /**
     * Relê a solicitação com bloqueio e só aceita transições a partir de "pending".
     */
    private function lockPending(LandlordRequest $landlordRequest): LandlordRequest
    {
        $locked = LandlordRequest::query()->whereKey($landlordRequest->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== LandlordRequestStatus::PENDING) {
            throw $this->error('Esta solicitação já foi analisada e não pode ser alterada.');
        }

        return $locked;
    }

    /**
     * Roda DEPOIS do commit. Uma falha ao notificar é registrada no log, mas não pode
     * transformar em erro uma decisão que já foi gravada com sucesso.
     */
    private function notifyApplicant(User $applicant, Notification $notification): void
    {
        try {
            $applicant->notify($notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function deleteApplicantDocuments(LandlordRequest $landlordRequest): void
    {
        $disk = Storage::disk('local');
        $paths = array_filter([
            $landlordRequest->document_front_path,
            $landlordRequest->document_back_path,
        ]);

        foreach ($paths as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Não foi possível excluir os documentos da solicitação analisada.');
            }
        }

        if ($paths !== []) {
            $landlordRequest->forceFill([
                'document_front_path' => null,
                'document_back_path' => null,
            ])->save();
        }
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['landlord_request' => [$message]]);
    }
}