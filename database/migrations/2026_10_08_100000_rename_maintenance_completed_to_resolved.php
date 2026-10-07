<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panel feedback: a finished maintenance request is "resolved", not
 * "completed" (it already records resolved_at). Done in three steps because
 * an enum can't be narrowed while rows still hold the old value.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setStatuses(['pending', 'in_progress', 'completed', 'resolved', 'cancelled']);
        DB::table('maintenance_requests')->where('status', 'completed')->update(['status' => 'resolved']);
        $this->setStatuses(['pending', 'in_progress', 'resolved', 'cancelled']);
    }

    public function down(): void
    {
        $this->setStatuses(['pending', 'in_progress', 'completed', 'resolved', 'cancelled']);
        DB::table('maintenance_requests')->where('status', 'resolved')->update(['status' => 'completed']);
        $this->setStatuses(['pending', 'in_progress', 'completed', 'cancelled']);
    }

    private function setStatuses(array $statuses): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('pending')->change();
        });
    }
};
