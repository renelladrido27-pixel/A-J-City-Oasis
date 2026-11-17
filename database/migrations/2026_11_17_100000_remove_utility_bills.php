<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants pay water and electricity to the providers themselves, so the system
 * no longer bills utilities: the utility_bills table, utility payments and
 * utility notifications go, and "utility" leaves the payment type list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('utility_bills');
        DB::table('payments')->where('type', 'utility')->delete();
        DB::table('notifications')->where('type', 'utility')->delete();

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('type', ['booking_upfront', 'rent', 'transfer_adjustment', 'move_out_balance'])
                ->default('rent')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('type', ['booking_upfront', 'rent', 'utility', 'transfer_adjustment', 'move_out_balance'])
                ->default('rent')
                ->change();
        });

        // The deleted rows are not restored; use the database backup for that.
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['water', 'electricity']);
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->foreignId('encoded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['unpaid', 'paid', 'overdue'])->default('unpaid');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_photo')->nullable();
            $table->timestamps();
        });
    }
};
