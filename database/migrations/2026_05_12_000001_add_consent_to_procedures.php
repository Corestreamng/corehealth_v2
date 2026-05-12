<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->enum('consent_status', ['pending', 'obtained', 'waived', 'not_required'])
                  ->nullable()
                  ->default(null)
                  ->after('status');
            $table->unsignedBigInteger('consent_marked_by')->nullable()->after('consent_status');
            $table->timestamp('consent_marked_at')->nullable()->after('consent_marked_by');
            $table->string('consent_notes', 500)->nullable()->after('consent_marked_at');

            $table->foreign('consent_marked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropForeign(['consent_marked_by']);
            $table->dropColumn(['consent_status', 'consent_marked_by', 'consent_marked_at', 'consent_notes']);
        });
    }
};
