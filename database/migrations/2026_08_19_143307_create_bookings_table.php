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
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->timestamp('booked_at');
            $table->date('move_in_deadline');
            $table->date('move_in_date')->nullable();
            $table->enum('status', ['pending_payment', 'confirmed', 'cancelled', 'expired'])->default('pending_payment');
            $table->decimal('advance_amount', 10, 2);
            $table->decimal('deposit_amount', 10, 2);
            $table->decimal('security_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
