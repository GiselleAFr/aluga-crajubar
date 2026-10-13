<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imoveis', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->enum('tipo', ['casa', 'apartamento', 'quarto', 'pousada', 'kitnet', 'comercial', 'terreno']);
            $table->enum('finalidade', ['aluguel', 'venda', 'diaria']);
            $table->decimal('preco', 12, 2); // Ex: Preço total ou valor da diária
            $table->enum('unidade_preco', ['mes', 'dia', 'total'])->default('mes');
            $table->string('cidade');
            $table->string('bairro');
            $table->string('endereco')->nullable();
            $table->integer('quartos')->default(0);
            $table->integer('banheiros')->default(0);
            $table->decimal('area', 8, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            // Índices para performance no PostgreSQL
            $table->index(['cidade', 'bairro']);
            $table->index('preco');
            $table->index('tipo');
            $table->index('finalidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imoveis');
    }
};