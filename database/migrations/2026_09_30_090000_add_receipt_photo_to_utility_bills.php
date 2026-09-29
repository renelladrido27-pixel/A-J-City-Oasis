<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_bills', function (Blueprint $table) {
            // Photo of the utility provider's bill/receipt the amount was encoded from.
            $table->string('receipt_photo')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('utility_bills', function (Blueprint $table) {
            $table->dropColumn('receipt_photo');
        });
    }
};
