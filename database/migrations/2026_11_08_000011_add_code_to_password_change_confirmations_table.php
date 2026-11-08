<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('password_change_confirmations', 'code')) {
            Schema::table('password_change_confirmations', function (Blueprint $table) {
                // Hash do código de 6 dígitos enviado por e-mail.
                $table->string('code')->nullable()->after('token');
            });
        }

        if (! Schema::hasColumn('password_change_confirmations', 'attempts')) {
            Schema::table('password_change_confirmations', function (Blueprint $table) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('code');
            });
        }
    }

    public function down(): void
    {
        foreach (['attempts', 'code'] as $column) {
            if (Schema::hasColumn('password_change_confirmations', $column)) {
                Schema::table('password_change_confirmations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};