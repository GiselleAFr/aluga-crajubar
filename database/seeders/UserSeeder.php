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
            ],
            [
                'name' => 'Cliente Teste',
                'email' => 'cliente@alugacrajubar.test',
                'role' => 'client',
            ],
            [
                'name' => 'Proprietário Teste',
                'email' => 'proprietario@alugacrajubar.test',
                'role' => 'landlord',
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

            if (! User::where('email', $emailProprietario)->exists()) {
                User::factory()->landlord()->create([
                    'name' => "Proprietário {$i}",
                    'email' => $emailProprietario,
                ]);
            }
        }
    }
}
