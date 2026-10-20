<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('message')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // O PostgreSQL não cria índice automaticamente para chaves estrangeiras.
            $table->index('user_id');
        });

        // Garante no banco que cada usuário tenha no máximo UMA solicitação pendente.
        // Solicitações aprovadas/recusadas continuam livres (histórico e novas tentativas).
        // Esta sintaxe funciona tanto no PostgreSQL quanto no SQLite usado nos testes.
        DB::statement(
            "CREATE UNIQUE INDEX landlord_requests_one_pending_per_user ON landlord_requests (user_id) WHERE status = 'pending'"
        );
    }

    public function down(): void
    {
        // Remover a tabela remove também os índices dela.
        Schema::dropIfExists('landlord_requests');
    }
};