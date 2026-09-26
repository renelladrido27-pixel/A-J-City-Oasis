<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['booking_upfront', 'rent', 'utility', 'transfer_adjustment'])->default('rent');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'refunded'])->default('pending');
            $table->string('xendit_invoice_id')->nullable();
            $table->string('xendit_payment_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
