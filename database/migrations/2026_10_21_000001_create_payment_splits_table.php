<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payment_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->string('payment_method', 50);
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('reference_no', 255)->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');
            $table->index('payment_id', 'idx_psplits_payment');
            $table->index('payment_method', 'idx_psplits_method');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_split_payment')->default(false)->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('is_split_payment');
        });

        Schema::dropIfExists('payment_splits');
    }
};
