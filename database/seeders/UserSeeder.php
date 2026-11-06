<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'name' => 'Administrador',
                'email' => 'admin@alugacrajubar.test',
                'role' => 'admin',
                'phone' => null,
            ],
            [
                'name' => 'Cliente Teste',
                'email' => 'cliente@alugacrajubar.test',
                'role' => 'client',
                'phone' => null,
            ],
            [
                'name' => 'Proprietário Teste',
                'email' => 'proprietario@alugacrajubar.test',
                'role' => 'landlord',
                // Número real de quem está testando (definido no .env).
                'phone' => $this->telefoneDeTeste(),
            ],
        ];

        foreach ($usuarios as $dados) {
            $usuario = User::firstOrNew([
                'email' => $dados['email'],
            ]);

            $usuario->name = $dados['name'];
            $usuario->password = Hash::make('password');
            $usuario->role = $dados['role'];
            $usuario->email_verified_at = now();
            $usuario->save();

            $this->definirTelefone($usuario, $dados['phone']);
        }

        // Cria mais 4 clientes e 4 proprietários, sem duplicar
        // as contas nas próximas execuções do seeder.
        for ($i = 1; $i <= 4; $i++) {
            $emailCliente = "cliente{$i}@alugacrajubar.test";

            if (! User::where('email', $emailCliente)->exists()) {
                User::factory()->create([
                    'name' => "Cliente {$i}",
                    'email' => $emailCliente,
                ]);
            }

            $emailProprietario = "proprietario{$i}@alugacrajubar.test";

            $proprietario = User::where('email', $emailProprietario)->first()
                ?? User::factory()->landlord()->create([
                    'name' => "Proprietário {$i}",
                    'email' => $emailProprietario,
                ]);

            // Garante telefone também nas contas que já existiam.
            $this->definirTelefone($proprietario, $this->telefoneFicticio($i));
        }
    }

    /**
     * Telefone de teste vindo do .env (SEED_TEST_PHONE), no mesmo formato
     * que o cadastro da API grava: +55DDDNÚMERO.
     */
    private function telefoneDeTeste(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) env('SEED_TEST_PHONE'));

        if ($digitos === '') {
            $this->command?->warn(
                'SEED_TEST_PHONE não está definido no .env: o "Proprietário Teste" ficará sem telefone.'
            );

            return null;
        }

        if (in_array(strlen($digitos), [10, 11], true)) {
            $digitos = '55'.$digitos;
        }

        $telefone = '+'.$digitos;

        if (! preg_match('/^\+[1-9]\d{9,14}$/', $telefone)) {
            $this->command?->warn("SEED_TEST_PHONE inválido ({$telefone}). Use DDD + número, só dígitos.");

            return null;
        }

        return $telefone;
    }

    /**
     * Números fictícios (DDD 88, prefixo 9 0000-xxxx, faixa não alocada),
     * para nenhum telefone real receber mensagens de teste.
     */
    private function telefoneFicticio(int $indice): string
    {
        return '+55889'.str_pad((string) $indice, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Grava o telefone se ele ainda não estiver com outra conta
     * (a coluna phone é única).
     */
    private function definirTelefone(User $usuario, ?string $telefone): void
    {
        if ($telefone === null || $usuario->phone === $telefone) {
            return;
        }

        $emUso = User::where('phone', $telefone)
            ->where('id', '!=', $usuario->id)
            ->exists();

        if ($emUso) {
            $this->command?->warn(
                "O telefone {$telefone} já pertence a outra conta; {$usuario->email} não foi alterado."
            );

            return;
        }

        $usuario->phone = $telefone;
        $usuario->save();
    }
}