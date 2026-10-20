<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Valor do enum App\Enums\NotificationType (string, sem enum nativo do banco).
            $table->string('type', 50);
            $table->boolean('enabled');
            $table->timestamps();

            // Uma linha por usuário e tipo. Também atende as buscas por user_id (coluna à esquerda).
            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};