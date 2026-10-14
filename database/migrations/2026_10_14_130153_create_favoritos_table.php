<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favoritos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('imovel_id')
                ->constrained('imoveis')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'imovel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoritos');
    }
};