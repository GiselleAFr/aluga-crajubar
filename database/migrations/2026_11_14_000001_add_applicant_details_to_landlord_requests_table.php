<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_requests', function (Blueprint $table) {
            $table->string('full_name')->nullable();
            $table->string('cpf', 11)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('document_type', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('primary_phone', 16)->nullable();
            $table->string('secondary_phone', 16)->nullable();
            $table->string('commercial_phone', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landlord_requests', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'cpf',
                'birth_date',
                'document_type',
                'email',
                'primary_phone',
                'secondary_phone',
                'commercial_phone',
            ]);
        });
    }
};
