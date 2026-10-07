<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panel feedback:
 * - Store the name as separate first / middle / surname fields. `name` stays as
 *   the combined display name (kept in sync by the User model) so existing
 *   screens, emails and reports keep working.
 * - Accounts are verified with a 6-digit code sent by email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            // Hashed 6-digit code + expiry; cleared once the email is verified.
            $table->string('email_verification_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code');
        });

        foreach (DB::table('users')->get(['id', 'name', 'email_verified_at']) as $user) {
            // Best-effort split of existing names: last word is the surname, a
            // middle word (if there are 3+) the middle name, the rest the first name.
            $parts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY);
            $last = count($parts) > 1 ? array_pop($parts) : null;
            $middle = count($parts) > 1 ? array_pop($parts) : null;

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => implode(' ', $parts) ?: null,
                'middle_name' => $middle,
                'last_name' => $last,
                // Accounts that existed before verification was introduced are trusted.
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'email_verification_code', 'email_verification_expires_at']);
        });
    }
};
