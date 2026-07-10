<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateStaffBillPaymentAllocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('staff_bill_payment_allocations')) {
            Schema::create('staff_bill_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_bill_id')->constrained('staff_bills')->cascadeOnDelete();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->decimal('amount_allocated', 12, 2);
                $table->decimal('discount_allocated', 12, 2)->default(0.00);
                $table->timestamps();

                $table->index('staff_bill_id');
                $table->index('payment_id');
            });
        } else {
            // If the table exists from a previously failed run, clear it to avoid duplicate inserts
            DB::table('staff_bill_payment_allocations')->truncate();
        }

        // Migrate historical settled/partial bills into allocations table
        $historicalBills = DB::table('staff_bills')
            ->whereNotNull('settlement_payment_id')
            ->get();

        foreach ($historicalBills as $bill) {
            $discount = floatval($bill->discount_amount);
            $totalAmount = floatval($bill->total_amount);
            $outstanding = floatval($bill->outstanding_amount);

            // Amount paid is original total minus what remains outstanding, minus discount
            $amountPaid = max(0.00, $totalAmount - $outstanding - $discount);

            DB::table('staff_bill_payment_allocations')->insert([
                'staff_bill_id'      => $bill->id,
                'payment_id'        => $bill->settlement_payment_id,
                'amount_allocated'   => $amountPaid,
                'discount_allocated' => $discount,
                'created_at'        => $bill->settled_at ?? $bill->updated_at ?? now(),
                'updated_at'        => $bill->settled_at ?? $bill->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_bill_payment_allocations');
    }
}
