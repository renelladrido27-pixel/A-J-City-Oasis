<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('audience', ['all', 'property', 'floor', 'tenant'])->default('all');
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('floor')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
