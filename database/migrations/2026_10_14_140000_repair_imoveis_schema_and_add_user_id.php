<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('imoveis')) {
            Schema::create('imoveis', function (Blueprint $table) {
                $table->id();
                $table->string('titulo');
                $table->text('descricao')->nullable();
                $table->string('tipo');
                $table->string('finalidade');
                $table->decimal('preco', 12, 2);
                $table->string('cidade');
                $table->string('bairro');
                $table->string('endereco');
                $table->unsignedInteger('quartos')->default(0);
                $table->unsignedInteger('banheiros')->default(0);
                $table->decimal('area', 10, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('imoveis', 'user_id')) {
            Schema::table('imoveis', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('imoveis', 'user_id')) {
            Schema::table('imoveis', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};