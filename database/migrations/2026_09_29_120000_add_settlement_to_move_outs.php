<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move-out settlement: the admin inspects the room and records itemized damage
 * deductions against the (refundable) security deposit. Unpaid bills are
 * settled against the deposit too; if the total owed exceeds it, one
 * "move_out_balance" bill is created for the tenant to pay via Xendit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('move_out_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('move_out_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::table('move_outs', function (Blueprint $table) {
            // Snapshot of the final settlement, computed when the admin finalizes.
            $table->decimal('unused_rent_credit', 10, 2)->default(0)->after('refund_amount');
            $table->decimal('security_deposit', 10, 2)->default(0)->after('unused_rent_credit');
            $table->decimal('unpaid_dues', 10, 2)->default(0)->after('security_deposit');
            $table->decimal('damage_total', 10, 2)->default(0)->after('unpaid_dues');
            $table->decimal('balance_due', 10, 2)->default(0)->after('damage_total');
            $table->foreignId('balance_payment_id')->nullable()->after('balance_due')->constrained('payments')->nullOnDelete();
        });

        Schema::table('move_outs', function (Blueprint $table) {
            $table->enum('refund_status', ['calculated', 'disbursed_manually', 'no_refund', 'balance_due', 'balance_paid'])
                ->default('calculated')
                ->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('type', ['booking_upfront', 'rent', 'utility', 'transfer_adjustment', 'move_out_balance'])
                ->default('rent')
                ->change();
            // "settled": closed at move-out against the security deposit (or rolled
            // into the move-out balance bill) — the money wasn't paid separately.
            $table->enum('status', ['pending', 'paid', 'overdue', 'refunded', 'settled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('move_outs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('balance_payment_id');
            $table->dropColumn(['unused_rent_credit', 'security_deposit', 'unpaid_dues', 'damage_total', 'balance_due']);
        });

        Schema::dropIfExists('move_out_deductions');
    }
};
