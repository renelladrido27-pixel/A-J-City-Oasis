<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->unsignedTinyInteger('floor')->default(1);
            $table->string('type')->default('standard');
            $table->decimal('monthly_rate', 10, 2);
            $table->enum('status', ['vacant', 'occupied', 'maintenance', 'reserved'])->default('vacant');
            $table->timestamps();

            $table->unique(['property_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
