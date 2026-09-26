<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['water', 'electricity']);
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->foreignId('encoded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['unpaid', 'paid', 'overdue'])->default('unpaid');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bills');
    }
};
