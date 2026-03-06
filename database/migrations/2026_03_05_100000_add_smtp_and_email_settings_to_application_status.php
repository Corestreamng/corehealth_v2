<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add SMTP credentials and appointment email notification settings
     * to the application_status table.
     */
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            // SMTP Configuration
            $table->string('smtp_host')->nullable()->after('imaging_results_require_approval');
            $table->unsignedInteger('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_username')->nullable()->after('smtp_port');
            $table->string('smtp_password')->nullable()->after('smtp_username');
            $table->string('smtp_encryption', 10)->nullable()->after('smtp_password'); // tls, ssl, or null
            $table->string('smtp_from_address')->nullable()->after('smtp_encryption');
            $table->string('smtp_from_name')->nullable()->after('smtp_from_address');

            // Appointment Email Notification Flags
            $table->boolean('send_appointment_email_to_doctors')->default(false)->after('smtp_from_name');
            $table->boolean('send_appointment_email_to_patients')->default(false)->after('send_appointment_email_to_doctors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn([
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'smtp_from_address',
                'smtp_from_name',
                'send_appointment_email_to_doctors',
                'send_appointment_email_to_patients',
            ]);
        });
    }
};
