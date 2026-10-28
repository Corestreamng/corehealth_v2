<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add is_locum flag to users table if not already present
        if (!Schema::hasColumn('users', 'is_locum')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_locum')->default(false)->after('status')->index();
            });
        }

        // 2. Create locum_profiles table
        if (!Schema::hasTable('locum_profiles')) {
            Schema::create('locum_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('regulatory_body', 50)->default('MDCN'); // MDCN, RRBN, MLSCN, NMCN, PCN, OTHER
                $table->string('license_number', 100);
                $table->string('primary_specialty', 150)->nullable();
                $table->string('cadre_template', 100)->nullable(); // DOCTOR, RADIOLOGIST, LAB_SCIENTIST, etc.
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['regulatory_body', 'license_number']);
            });
        }

        // 3. Create locum_passes table
        if (!Schema::hasTable('locum_passes')) {
            Schema::create('locum_passes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('pin_display', 12)->nullable(); // Displayed once upon issuance
                $table->string('passcode_hash'); // Bcrypt hash of the 6-digit PIN
                $table->string('shift_type', 30)->default('NIGHT'); // MORNING, AFTERNOON, NIGHT, CUSTOM
                $table->dateTime('starts_at');
                $table->dateTime('expires_at');
                $table->json('allowed_clinic_ids')->nullable();
                $table->json('allowed_department_ids')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->string('revocation_reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('revoked_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['user_id', 'expires_at', 'revoked_at'], 'idx_locum_passes_status');
                $table->index(['starts_at', 'expires_at'], 'idx_locum_passes_window');
            });
        }

        // 4. Ensure staff.employment_type ENUM includes 'locum'
        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'employment_type')) {
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `staff` MODIFY COLUMN `employment_type` ENUM('full_time','part_time','contract','intern','locum') NOT NULL DEFAULT 'full_time'");
            } catch (\Exception $e) {
                // Ignore if not on MySQL or column is already varchar
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locum_passes');
        Schema::dropIfExists('locum_profiles');

        if (Schema::hasColumn('users', 'is_locum')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['is_locum']);
                $table->dropColumn('is_locum');
            });
        }
    }
};
