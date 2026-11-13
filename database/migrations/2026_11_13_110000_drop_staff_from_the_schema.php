<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * With the staff role gone, the schema drops it too: users.role is admin or
 * tenant, and maintenance workers are stored by name (assignee_name /
 * assignee_phone), so the old account link maintenance_requests.assigned_to goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safety: no row may still hold the value being removed from the enum.
        DB::table('users')->where('role', 'staff')->update(['role' => 'admin']);

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'tenant'])->default('tenant')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'tenant'])->default('tenant')->change();
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }
};
