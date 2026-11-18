<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Único caminho para criar um super administrador: o terminal do servidor.
 * Não existe rota pública para isso.
 *
 *   php artisan admin:create-super
 *   php artisan admin:create-super --name="Fulano" --email=fulano@exemplo.com
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super
        {--name= : Nome do super administrador}
        {--email= : E-mail do super administrador}';

    protected $description = 'Cria um super administrador (ou promove uma conta existente). A senha é pedida no terminal.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail'))));

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'max:255']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        return $existing ? $this->promote($existing) : $this->create($email);
    }

    private function promote(User $user): int
    {
        if ($user->role === UserRole::SUPER_ADMIN) {
            $this->info("{$user->email} já é super administrador.");

            return self::SUCCESS;
        }

        $confirmed = $this->confirm(
            "Já existe uma conta com {$user->email} (papel atual: {$user->role->value}). Promover a super administrador?",
            false,
        );

        if (! $confirmed) {
            $this->warn('Nada foi alterado.');

            return self::FAILURE;
        }

        $user->forceFill(['role' => UserRole::SUPER_ADMIN])->save();
        // Obriga um novo login, já com o novo papel.
        $user->tokens()->delete();

        $this->info("{$user->email} agora é super administrador.");

        return self::SUCCESS;
    }

    private function create(string $email): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Nome')));
        $password = (string) $this->secret('Senha (mínimo 12 caracteres, com letras e números)');
        $confirmation = (string) $this->secret('Repita a senha');

        $validator = Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // A senha é gravada com hash pelo cast do model.
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill([
            'role' => UserRole::SUPER_ADMIN,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Super administrador criado: {$user->email}");

        return self::SUCCESS;
    }
}