<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE audit_stamps MODIFY from_date DATETIME');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE audit_stamps MODIFY to_date DATETIME');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE audit_stamps MODIFY from_date DATE');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE audit_stamps MODIFY to_date DATE');
    }
};
