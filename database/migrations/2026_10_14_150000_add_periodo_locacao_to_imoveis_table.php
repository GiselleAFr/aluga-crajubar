<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('imoveis', 'periodo_locacao')) {
            Schema::table('imoveis', function (Blueprint $table) {
                $table->string('periodo_locacao')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('imoveis', 'periodo_locacao')) {
            Schema::table('imoveis', function (Blueprint $table) {
                $table->dropColumn('periodo_locacao');
            });
        }
    }
};