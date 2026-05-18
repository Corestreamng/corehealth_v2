<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Polymorphic result view tracking table.
     * Records each time a user views or prints a lab/imaging result.
     *
     * viewable_type: 'App\Models\LabServiceRequest' or 'App\Models\ImagingServiceRequest'
     * viewable_id:   the lab_service_requests.id or imaging_service_requests.id
     * view_type:     'modal' (View button) or 'print' (Print button / direct URL)
     */
    public function up(): void
    {
        Schema::create('result_views', function (Blueprint $table) {
            $table->id();
            $table->morphs('viewable');                     // viewable_type + viewable_id (indexed)
            $table->unsignedBigInteger('user_id');
            $table->string('view_type', 20)->default('modal'); // 'modal' or 'print'
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Composite index for fast "has this user viewed this result?" queries
            $table->index(['viewable_type', 'viewable_id', 'user_id'], 'result_views_viewable_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_views');
    }
};
