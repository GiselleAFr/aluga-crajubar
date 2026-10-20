<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Snapshot do imóvel para o histórico, mesmo se ele mudar futuramente.
            $table->string('property_name');
            $table->string('city')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('guests')->default(1);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('status', 20)->default('confirmed');
            $table->timestamps();

            $table->index(['user_id', 'check_out']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
