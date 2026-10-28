<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance is assigned to a person by name (and an optional phone number),
 * not to a system account: most maintenance workers never log in. Existing
 * assignments keep the account holder's name and phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->string('assignee_name', 100)->nullable()->after('assigned_to');
            $table->string('assignee_phone', 20)->nullable()->after('assignee_name');
        });

        DB::table('maintenance_requests')->whereNotNull('assigned_to')->orderBy('id')->each(function ($request) {
            $user = DB::table('users')->where('id', $request->assigned_to)->first(['name', 'phone']);
            if ($user) {
                DB::table('maintenance_requests')->where('id', $request->id)->update([
                    'assignee_name' => $user->name,
                    'assignee_phone' => $user->phone,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['assignee_name', 'assignee_phone']);
        });
    }
};
