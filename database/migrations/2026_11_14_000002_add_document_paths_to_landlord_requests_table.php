<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_requests', function (Blueprint $table) {
            $table->string('document_front_path')->nullable();
            $table->string('document_back_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landlord_requests', function (Blueprint $table) {
            $table->dropColumn(['document_front_path', 'document_back_path']);
        });
    }
};
