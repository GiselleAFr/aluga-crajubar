<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuários fixos de demonstração (senha de todos: password).
 * Não usa factory/Faker, para rodar também dentro do Docker.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            // [nome, e-mail, papel, telefone]
            ['Administrador', 'admin@alugacrajubar.test', 'admin', null],
            ['Cliente Teste', 'cliente@alugacrajubar.test', 'client', null],
            ['Ana Clara Souza', 'cliente1@alugacrajubar.test', 'client', null],
            ['Bruno Alencar', 'cliente2@alugacrajubar.test', 'client', null],
            ['Proprietário Teste', 'proprietario@alugacrajubar.test', 'landlord', $this->telefoneDeTeste()],
            ['Maria do Socorro Lima', 'proprietario1@alugacrajubar.test', 'landlord', '+5588900000001'],
            ['José Wellington Pereira', 'proprietario2@alugacrajubar.test', 'landlord', '+5588900000002'],
        ];

        foreach ($usuarios as [$nome, $email, $papel, $telefone]) {
            $usuario = User::firstOrNew(['email' => $email]);

            $usuario->forceFill([
                'name' => $nome,
                'password' => Hash::make('password'),
                'role' => $papel,
                'phone' => $telefone,
                'email_verified_at' => now(),
            ])->save();
        }

        $this->command?->info('Usuários de demonstração criados (senha: password).');
    }

    /** Telefone do .env (SEED_TEST_PHONE) no formato +55DDDNÚMERO. */
    private function telefoneDeTeste(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) env('SEED_TEST_PHONE'));

        if ($digitos === '') {
            return null;
        }

        if (in_array(strlen($digitos), [10, 11], true)) {
            $digitos = '55'.$digitos;
        }

        return preg_match('/^[1-9]\d{9,14}$/', $digitos) ? '+'.$digitos : null;
    }
}