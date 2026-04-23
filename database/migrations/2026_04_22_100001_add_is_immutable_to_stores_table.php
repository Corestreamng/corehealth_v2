<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds is_immutable flag to stores.
 *
 * Immutable stores (canonical pharmacy hub and central store) cannot be
 * deleted, deactivated, or have their distribution_role changed by
 * the StoreObserver.
 *
 * Plan §4 — Store governance; canonical store protection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'is_immutable')) {
                $table->boolean('is_immutable')->default(false)->after('is_default')
                    ->comment('When true, store cannot be deleted or deactivated via the UI/observer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'is_immutable')) {
                $table->dropColumn('is_immutable');
            }
        });
    }
};
