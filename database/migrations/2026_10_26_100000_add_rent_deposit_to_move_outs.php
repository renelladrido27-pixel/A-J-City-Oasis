<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 1-month deposit is now refunded at move-out when all rent was paid
 * (it pays any unpaid rent otherwise), alongside the security deposit.
 * Rent itself is never refunded, so no more "unused rent" credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('move_outs', function (Blueprint $table) {
            $table->decimal('rent_deposit', 10, 2)->default(0)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('move_outs', function (Blueprint $table) {
            $table->dropColumn('rent_deposit');
        });
    }
};
