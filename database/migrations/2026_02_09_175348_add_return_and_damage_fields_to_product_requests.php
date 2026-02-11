<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReturnAndDamageFieldsToProductRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Return tracking fields
            $table->unsignedBigInteger('returned_by')->nullable()->after('dispensed_by');
            $table->timestamp('returned_date')->nullable()->after('dispense_date');
            $table->decimal('returned_qty', 10, 2)->nullable()->after('qty');
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->text('return_reason')->nullable();
            $table->string('return_condition', 50)->nullable()->comment('good, damaged, expired');

            // Damage tracking fields
            $table->unsignedBigInteger('damaged_by')->nullable();
            $table->timestamp('damaged_date')->nullable();
            $table->decimal('damaged_qty', 10, 2)->nullable();
            $table->text('damage_reason')->nullable();
            $table->string('damage_type', 50)->nullable()->comment('expired, broken, contaminated, other');

            // Approval workflow
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Foreign keys
            $table->foreign('returned_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('damaged_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropForeign(['returned_by']);
            $table->dropForeign(['damaged_by']);
            $table->dropForeign(['approved_by']);

            $table->dropColumn([
                'returned_by', 'returned_date', 'returned_qty',
                'refund_amount', 'return_reason', 'return_condition',
                'damaged_by', 'damaged_date', 'damaged_qty',
                'damage_reason', 'damage_type',
                'approved_by', 'approved_at', 'approval_notes'
            ]);
        });
    }
}
