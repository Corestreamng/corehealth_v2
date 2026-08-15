<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsAuditedToProceduresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('procedures', function (Blueprint $table) {
            if (!Schema::hasColumn('procedures', 'is_audited')) {
                $table->boolean('is_audited')->default(false)->after('updated_at');
            }
            if (!Schema::hasColumn('procedures', 'audited_by')) {
                $table->unsignedBigInteger('audited_by')->nullable()->after('is_audited');
            }
            if (!Schema::hasColumn('procedures', 'audited_at')) {
                $table->timestamp('audited_at')->nullable()->after('audited_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn(['is_audited', 'audited_by', 'audited_at']);
        });
    }
}
