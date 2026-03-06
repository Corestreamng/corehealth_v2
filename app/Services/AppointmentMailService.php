<?php

namespace App\Services;

use App\Mail\AppointmentNotificationMail;
use App\Models\DoctorAppointment;
use App\Models\patient;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

/**
 * AppointmentMailService
 *
 * Sends hospital-branded appointment notification emails to doctors and patients.
 * Uses SMTP credentials from application_status (via appsettings()) when configured,
 * falls back to PHP's native mail() function otherwise.
 *
 * Triggered by the DoctorAppointmentObserver on create/update/cancel/reschedule events.
 */
class AppointmentMailService
{
    /**
     * Send appointment notification emails based on the event type.
     *
     * @param DoctorAppointment $appointment
     * @param string            $event  created|rescheduled|cancelled|checked_in|no_show|reassigned
     * @param array             $extra  Extra context (e.g. old_date, old_time, reason)
     */
    public function notify(DoctorAppointment $appointment, string $event, array $extra = []): void
    {
        try {
            $settings = appsettings();
            if (!$settings) {
                return;
            }

            $appointment->loadMissing(['patient.user', 'clinic', 'doctor.user']);

            $data = $this->buildTemplateData($appointment, $event, $extra);

            // Send to doctor
            if ($settings->send_appointment_email_to_doctors && $appointment->staff_id) {
                $doctorEmail = $this->getDoctorEmail($appointment->staff_id);
                if ($doctorEmail) {
                    $subject = $this->getSubject($event, 'doctor', $data);
                    $html = View::make('emails.appointment-notification', array_merge($data, ['recipient_type' => 'doctor']))->render();
                    $this->sendMail($doctorEmail, $subject, $html, $settings);
                }
            }

            // Send to patient
            if ($settings->send_appointment_email_to_patients && $appointment->patient_id) {
                $patientEmail = $this->getPatientEmail($appointment->patient_id);
                if ($patientEmail) {
                    $subject = $this->getSubject($event, 'patient', $data);
                    $html = View::make('emails.appointment-notification', array_merge($data, ['recipient_type' => 'patient']))->render();
                    $this->sendMail($patientEmail, $subject, $html, $settings);
                }
            }
        } catch (\Exception $e) {
            Log::error('AppointmentMailService: Failed to send notification', [
                'event'          => $event,
                'appointment_id' => $appointment->id ?? null,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the data array passed to the email Blade template.
     */
    protected function buildTemplateData(DoctorAppointment $appointment, string $event, array $extra): array
    {
        $settings = appsettings();

        return [
            // Hospital branding
            'hospital_name'    => $settings->site_name ?? 'Hospital',
            'hospital_logo'    => $settings->logo ?? null,
            'hospital_address' => $settings->contact_address ?? '',
            'hospital_phones'  => $settings->contact_phones ?? '',
            'hospital_emails'  => $settings->contact_emails ?? '',
            'hospital_color'   => $settings->hos_color ?? '#011b33',
            'footer_text'      => $settings->footer_text ?? '',

            // Appointment details
            'event'            => $event,
            'patient_name'     => $appointment->patient && $appointment->patient->user
                                    ? $appointment->patient->user->name
                                    : 'Patient',
            'doctor_name'      => $appointment->doctor && $appointment->doctor->user
                                    ? $appointment->doctor->user->name
                                    : 'Doctor',
            'clinic_name'      => $appointment->clinic->name ?? 'Clinic',
            'appointment_date' => $appointment->appointment_date
                                    ? $appointment->appointment_date->format('l, F j, Y')
                                    : 'N/A',
            'start_time'       => $appointment->start_time
                                    ? \Carbon\Carbon::parse($appointment->start_time)->format('g:i A')
                                    : 'N/A',
            'end_time'         => $appointment->end_time
                                    ? \Carbon\Carbon::parse($appointment->end_time)->format('g:i A')
                                    : '',
            'appointment_type' => ucfirst(str_replace('_', ' ', $appointment->appointment_type ?? 'scheduled')),
            'priority'         => ucfirst($appointment->priority ?? 'routine'),
            'notes'            => $appointment->notes ?? '',

            // Extra context
            'cancellation_reason' => $extra['reason'] ?? $appointment->cancellation_reason ?? '',
            'old_date'            => $extra['old_date'] ?? '',
            'old_time'            => $extra['old_time'] ?? '',
            'reassignment_reason' => $extra['reassignment_reason'] ?? $appointment->reassignment_reason ?? '',
            'old_doctor'          => $extra['old_doctor'] ?? '',
        ];
    }

    /**
     * Generate the email subject line based on event and recipient type.
     */
    protected function getSubject(string $event, string $recipientType, array $data): string
    {
        $hospital = $data['hospital_name'];

        return match ($event) {
            'created'    => "{$hospital} — New Appointment Scheduled",
            'rescheduled' => "{$hospital} — Appointment Rescheduled",
            'cancelled'  => "{$hospital} — Appointment Cancelled",
            'checked_in' => "{$hospital} — Patient Checked In",
            'no_show'    => "{$hospital} — Appointment No-Show",
            'reassigned' => "{$hospital} — Appointment Reassigned",
            default      => "{$hospital} — Appointment Update",
        };
    }

    /**
     * Send an email using SMTP (if configured) or PHP mail() as fallback.
     *
     * @param string $to       Recipient email address
     * @param string $subject  Email subject
     * @param string $htmlBody HTML body content
     * @param object $settings ApplicationStatu model
     */
    protected function sendMail(string $to, string $subject, string $htmlBody, $settings): void
    {
        $fromAddress = $settings->smtp_from_address ?: ($settings->contact_emails ?: 'noreply@hospital.com');
        $fromName    = $settings->smtp_from_name ?: ($settings->site_name ?? 'Hospital');

        // If SMTP is configured, use it
        if ($settings->smtp_host && $settings->smtp_port) {
            $this->sendViaSMTP($to, $subject, $htmlBody, $fromAddress, $fromName, $settings);
        } else {
            // Fallback to PHP native mail()
            $this->sendViaPhpMail($to, $subject, $htmlBody, $fromAddress, $fromName);
        }
    }

    /**
     * Send email via SMTP using PHP's native socket connection.
     * This does NOT depend on Laravel's mail config — it reads credentials
     * directly from application_status.
     */
    protected function sendViaSMTP(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, $settings): void
    {
        try {
            // Dynamically configure Laravel's SMTP mailer at runtime
            config([
                'mail.mailers.appointment_smtp' => [
                    'transport'  => 'smtp',
                    'host'       => $settings->smtp_host,
                    'port'       => (int) $settings->smtp_port,
                    'encryption' => $settings->smtp_encryption ?: null,
                    'username'   => $settings->smtp_username,
                    'password'   => $settings->smtp_password,
                    'timeout'    => 15,
                ],
            ]);

            $mailable = new AppointmentNotificationMail($subject, $htmlBody, $fromAddress, $fromName);
            Mail::mailer('appointment_smtp')->to($to)->send($mailable);

            Log::info("AppointmentMailService: SMTP email sent to {$to}", ['subject' => $subject]);
        } catch (\Exception $e) {
            Log::warning("AppointmentMailService: SMTP failed, falling back to PHP mail()", [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
            // Fallback to PHP mail
            $this->sendViaPhpMail($to, $subject, $htmlBody, $fromAddress, $fromName);
        }
    }

    /**
     * Send email using PHP's native mail() function as a last-resort fallback.
     */
    protected function sendViaPhpMail(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName): void
    {
        try {
            $boundary = md5(time());
            $headers  = "From: {$fromName} <{$fromAddress}>\r\n";
            $headers .= "Reply-To: {$fromAddress}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "X-Mailer: CoreHealth-Appointments\r\n";

            $result = @mail($to, $subject, $htmlBody, $headers);

            if ($result) {
                Log::info("AppointmentMailService: PHP mail() sent to {$to}", ['subject' => $subject]);
            } else {
                Log::warning("AppointmentMailService: PHP mail() returned false for {$to}");
            }
        } catch (\Exception $e) {
            Log::error("AppointmentMailService: PHP mail() failed for {$to}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the doctor's email address via Staff → User relationship.
     */
    protected function getDoctorEmail(int $staffId): ?string
    {
        $staff = Staff::with('user')->find($staffId);
        return $staff && $staff->user ? $staff->user->email : null;
    }

    /**
     * Get the patient's email address via Patient → User relationship.
     */
    protected function getPatientEmail(int $patientId): ?string
    {
        $patient = patient::with('user')->find($patientId);
        return $patient && $patient->user ? $patient->user->email : null;
    }
}
