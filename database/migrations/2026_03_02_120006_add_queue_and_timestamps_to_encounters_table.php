<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('queue_id')->nullable()->after('patient_id')
                  ->comment('Direct link to DoctorQueue entry');
            $table->timestamp('started_at')->nullable()->after('completed')
                  ->comment('When doctor first opened encounter');
            $table->timestamp('completed_at')->nullable()->after('started_at')
                  ->comment('When encounter was finalized');
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropColumn(['queue_id', 'started_at', 'completed_at']);
        });
    }
};
