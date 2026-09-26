<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('to_room_id')->constrained('rooms')->cascadeOnDelete();
            $table->decimal('deposit_adjustment', 10, 2);
            $table->enum('status', ['pending', 'approved', 'completed', 'rejected'])->default('pending');
            $table->timestamp('requested_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_transfers');
    }
};
