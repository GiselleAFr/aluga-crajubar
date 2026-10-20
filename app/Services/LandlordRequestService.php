<?php

namespace App\Services;

use App\Enums\LandlordRequestStatus;
use App\Enums\UserRole;
use App\Models\LandlordRequest;
use App\Models\User;
use App\Notifications\LandlordRequestApprovedNotification;
use App\Notifications\LandlordRequestRejectedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class LandlordRequestService
{
    private const DUPLICATE_MESSAGE = 'Você já possui uma solicitação pendente de análise.';

    /**
     * Registra uma nova solicitação com status "pending".
     *
     * @param  array{message?: string|null}  $data
     */
    public function submit(User $user, array $data): LandlordRequest
    {
        return DB::transaction(function () use ($user, $data): LandlordRequest {
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
                $landlordRequest = new LandlordRequest();
                $landlordRequest->forceFill([
                    'user_id' => $applicant->id,
                    'status' => LandlordRequestStatus::PENDING,
                    'message' => $data['message'] ?? null,
                ])->save();
            } catch (UniqueConstraintViolationException) {
                // Rede de segurança: o índice único parcial do banco barrou uma corrida.
                throw $this->error(self::DUPLICATE_MESSAGE);
            }

            return $landlordRequest;
        });
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

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['landlord_request' => [$message]]);
    }
}