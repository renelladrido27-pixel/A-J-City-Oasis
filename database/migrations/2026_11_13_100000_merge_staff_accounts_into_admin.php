<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The staff role is gone: staff work is done by the admin. Existing staff
 * accounts become admin accounts (kept, not deleted, so their history stays).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'staff')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // Which admins used to be staff isn't recorded; nothing to undo.
    }
};
