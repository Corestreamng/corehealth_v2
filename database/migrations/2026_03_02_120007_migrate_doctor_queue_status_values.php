<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate existing doctor_queues.status values to unified QueueStatus enum.
     *
     * Old reception meaning      → New unified
     * 1 (waiting)                → 1 (WAITING)          — no change
     * 2 (vitals_pending)         → 2 (VITALS_PENDING)   — no change
     * 3 (in_consultation)        → 4 (IN_CONSULTATION)  — shift +1
     * 4 (completed)              → 5 (COMPLETED)        — shift +1
     *
     * Order matters to avoid collision: handle 4→5 before 3→4.
     */
    public function up(): void
    {
        // completed first to avoid collision
        DB::table('doctor_queues')->where('status', 4)->update(['status' => 5]);
        // then in_consultation
        DB::table('doctor_queues')->where('status', 3)->update(['status' => 4]);
    }

    public function down(): void
    {
        // reverse: 4→3 first, then 5→4
        DB::table('doctor_queues')->where('status', 4)->update(['status' => 3]);
        DB::table('doctor_queues')->where('status', 5)->update(['status' => 4]);
    }
};
