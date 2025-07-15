<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This is a no-op migration since the column name is already correct in the database
// The issue was in the code referencing the wrong column name
return new class extends Migration
{
    public function up()
    {
        // No changes needed - database column is already administered_at
    }

    public function down()
    {
        // No changes needed
    }
};
