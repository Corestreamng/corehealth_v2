{{--
    Appointment Notification Email Template

    Hospital-branded email for appointment lifecycle events.
    Branding values sourced from application_status via appsettings().

    Variables: hospital_name, hospital_logo, hospital_address, hospital_phones,
    hospital_emails, hospital_color, footer_text, event, patient_name, doctor_name,
    clinic_name, appointment_date, start_time, end_time, appointment_type, priority,
    notes, cancellation_reason, old_date, old_time, reassignment_reason, old_doctor,
    recipient_type (doctor|patient)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $hospital_name }} — Appointment Notification</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased;">

    <!-- Outer container -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6f9; padding: 32px 0;">
        <tr>
            <td align="center">
                <!-- Inner card -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); max-width: 600px; width: 100%;">

                    {{-- ═══════════════ HEADER ═══════════════ --}}
                    <tr>
                        <td style="background-color: {{ $hospital_color }}; padding: 24px 32px; text-align: center;">
                            @if($hospital_logo)
                                <img src="data:image/png;base64,{{ $hospital_logo }}" alt="{{ $hospital_name }}" style="max-height: 60px; max-width: 200px; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto;">
                            @endif
                            <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;">
                                {{ $hospital_name }}
                            </h1>
                            @if($hospital_address)
                                <p style="color: rgba(255,255,255,0.85); margin: 4px 0 0 0; font-size: 12px;">
                                    {{ $hospital_address }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- ═══════════════ EVENT BANNER ═══════════════ --}}
                    <tr>
                        <td style="padding: 0;">
                            @php
                                $bannerConfig = match($event) {
                                    'created'     => ['bg' => '#e8f5e9', 'color' => '#2e7d32', 'icon' => '📋', 'title' => 'New Appointment Scheduled'],
                                    'rescheduled' => ['bg' => '#fff3e0', 'color' => '#e65100', 'icon' => '🔄', 'title' => 'Appointment Rescheduled'],
                                    'cancelled'   => ['bg' => '#ffebee', 'color' => '#c62828', 'icon' => '❌', 'title' => 'Appointment Cancelled'],
                                    'checked_in'  => ['bg' => '#e3f2fd', 'color' => '#1565c0', 'icon' => '✅', 'title' => 'Patient Checked In'],
                                    'no_show'     => ['bg' => '#fce4ec', 'color' => '#ad1457', 'icon' => '⚠️', 'title' => 'Appointment No-Show'],
                                    'reassigned'  => ['bg' => '#f3e5f5', 'color' => '#6a1b9a', 'icon' => '🔀', 'title' => 'Appointment Reassigned'],
                                    default       => ['bg' => '#e8eaf6', 'color' => '#283593', 'icon' => '📌', 'title' => 'Appointment Update'],
                                };
                            @endphp
                            <div style="background-color: {{ $bannerConfig['bg'] }}; padding: 16px 32px; text-align: center; border-bottom: 2px solid {{ $bannerConfig['color'] }}20;">
                                <span style="font-size: 28px;">{{ $bannerConfig['icon'] }}</span>
                                <h2 style="color: {{ $bannerConfig['color'] }}; margin: 8px 0 0; font-size: 18px; font-weight: 700;">
                                    {{ $bannerConfig['title'] }}
                                </h2>
                            </div>
                        </td>
                    </tr>

                    {{-- ═══════════════ GREETING ═══════════════ --}}
                    <tr>
                        <td style="padding: 24px 32px 8px;">
                            <p style="color: #333; font-size: 15px; margin: 0; line-height: 1.6;">
                                @if($recipient_type === 'doctor')
                                    Dear <strong>Dr. {{ $doctor_name }}</strong>,
                                @else
                                    Dear <strong>{{ $patient_name }}</strong>,
                                @endif
                            </p>
                            <p style="color: #555; font-size: 14px; margin: 8px 0 0; line-height: 1.6;">
                                @switch($event)
                                    @case('created')
                                        @if($recipient_type === 'doctor')
                                            A new appointment has been scheduled for your clinic.
                                        @else
                                            Your appointment has been successfully scheduled. Details below:
                                        @endif
                                        @break
                                    @case('rescheduled')
                                        @if($recipient_type === 'doctor')
                                            An appointment in your clinic has been rescheduled.
                                        @else
                                            Your appointment has been rescheduled. Please note the updated details below:
                                        @endif
                                        @break
                                    @case('cancelled')
                                        @if($recipient_type === 'doctor')
                                            An appointment in your clinic has been cancelled.
                                        @else
                                            We regret to inform you that your appointment has been cancelled.
                                        @endif
                                        @break
                                    @case('checked_in')
                                        @if($recipient_type === 'doctor')
                                            A patient has checked in for their appointment.
                                        @else
                                            You have been checked in successfully. Please proceed to the waiting area.
                                        @endif
                                        @break
                                    @case('no_show')
                                        @if($recipient_type === 'doctor')
                                            A patient did not attend their scheduled appointment.
                                        @else
                                            You were marked as a no-show for your scheduled appointment. Please contact us to reschedule.
                                        @endif
                                        @break
                                    @case('reassigned')
                                        @if($recipient_type === 'doctor')
                                            An appointment has been reassigned to you.
                                        @else
                                            Your appointment has been reassigned to a different doctor.
                                        @endif
                                        @break
                                    @default
                                        There has been an update to an appointment.
                                @endswitch
                            </p>
                        </td>
                    </tr>

                    {{-- ═══════════════ APPOINTMENT DETAILS TABLE ═══════════════ --}}
                    <tr>
                        <td style="padding: 16px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 6px 0; color: #6c757d; font-size: 13px; font-weight: 600; width: 140px; vertical-align: top;">
                                                    📅 Date
                                                </td>
                                                <td style="padding: 6px 0; color: #212529; font-size: 14px; font-weight: 600;">
                                                    {{ $appointment_date }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; color: #6c757d; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                    🕐 Time
                                                </td>
                                                <td style="padding: 6px 0; color: #212529; font-size: 14px;">
                                                    {{ $start_time }}{{ $end_time ? ' — ' . $end_time : '' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; color: #6c757d; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                    🏥 Clinic
                                                </td>
                                                <td style="padding: 6px 0; color: #212529; font-size: 14px;">
                                                    {{ $clinic_name }}
                                                </td>
                                            </tr>
                                            @if($recipient_type === 'patient')
                                            <tr>
                                                <td style="padding: 6px 0; color: #6c757d; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                    👨‍⚕️ Doctor
                                                </td>
                                                <td style="padding: 6px 0; color: #212529; font-size: 14px;">
                                                    Dr. {{ $doctor_name }}
                                                </td>
                                            </tr>
                                            @endif
                                            @if($recipient_type === 'doctor')
                                            <tr>
                                                <td style="padding: 6px 0; color: #6c757d; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                    🧑 Patient
                                                </td>
                                                <td style="padding: 6px 0; color: #212529; font-size: 14px;">
                                                    {{ $patient_name }}
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding: 6px 0; color: #6c757d; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                    📋 Type
                                                </td>
                                                <td style="padding: 6px 0; color: #212529; font-size: 14px;">
                                                    {{ $appointment_type }}
                                                </td>
                                            </tr>
                                            @if($priority && $priority !== 'Routine')
                                            <tr>
                                                <td style="padding: 6px 0; color: #6c757d; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                    ⚡ Priority
                                                </td>
                                                <td style="padding: 6px 0; color: {{ $priority === 'Emergency' ? '#c62828' : ($priority === 'Urgent' ? '#e65100' : '#212529') }}; font-size: 14px; font-weight: 600;">
                                                    {{ $priority }}
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ═══════════════ CONTEXT-SPECIFIC SECTIONS ═══════════════ --}}

                    {{-- Rescheduled: show old date/time --}}
                    @if($event === 'rescheduled' && ($old_date || $old_time))
                    <tr>
                        <td style="padding: 0 32px 16px;">
                            <div style="background-color: #fff8e1; border-left: 4px solid #f9a825; padding: 12px 16px; border-radius: 0 8px 8px 0;">
                                <p style="margin: 0; color: #795548; font-size: 13px;">
                                    <strong>Previously scheduled:</strong>
                                    {{ $old_date }}{{ $old_time ? ' at ' . $old_time : '' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endif

                    {{-- Cancelled: show reason --}}
                    @if($event === 'cancelled' && $cancellation_reason)
                    <tr>
                        <td style="padding: 0 32px 16px;">
                            <div style="background-color: #ffebee; border-left: 4px solid #e53935; padding: 12px 16px; border-radius: 0 8px 8px 0;">
                                <p style="margin: 0; color: #b71c1c; font-size: 13px;">
                                    <strong>Reason:</strong> {{ $cancellation_reason }}
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endif

                    {{-- Reassigned: show old doctor and reason --}}
                    @if($event === 'reassigned')
                    <tr>
                        <td style="padding: 0 32px 16px;">
                            <div style="background-color: #f3e5f5; border-left: 4px solid #8e24aa; padding: 12px 16px; border-radius: 0 8px 8px 0;">
                                @if($old_doctor)
                                    <p style="margin: 0 0 4px; color: #4a148c; font-size: 13px;">
                                        <strong>Previously assigned to:</strong> Dr. {{ $old_doctor }}
                                    </p>
                                @endif
                                @if($reassignment_reason)
                                    <p style="margin: 0; color: #4a148c; font-size: 13px;">
                                        <strong>Reason:</strong> {{ $reassignment_reason }}
                                    </p>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endif

                    {{-- Notes --}}
                    @if($notes)
                    <tr>
                        <td style="padding: 0 32px 16px;">
                            <div style="background-color: #f1f8ff; border-left: 4px solid #1976d2; padding: 12px 16px; border-radius: 0 8px 8px 0;">
                                <p style="margin: 0; color: #0d47a1; font-size: 13px;">
                                    <strong>Notes:</strong> {{ $notes }}
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endif

                    {{-- ═══════════════ CALL TO ACTION ═══════════════ --}}
                    @if($event !== 'cancelled' && $event !== 'no_show')
                    <tr>
                        <td style="padding: 8px 32px 24px; text-align: center;">
                            <p style="color: #666; font-size: 13px; margin: 0;">
                                @if($recipient_type === 'patient')
                                    Please arrive at least <strong>15 minutes</strong> before your appointment time.
                                    Bring your ID card and any relevant medical records.
                                @else
                                    This is an automated notification from the appointment system.
                                @endif
                            </p>
                        </td>
                    </tr>
                    @endif

                    {{-- ═══════════════ CONTACT INFO ═══════════════ --}}
                    @if($hospital_phones || $hospital_emails)
                    <tr>
                        <td style="padding: 0 32px 16px;">
                            <div style="background-color: #f8f9fa; padding: 12px 16px; border-radius: 8px; text-align: center;">
                                <p style="margin: 0; color: #6c757d; font-size: 12px;">
                                    For enquiries, contact us:
                                    @if($hospital_phones)
                                        📞 {{ $hospital_phones }}
                                    @endif
                                    @if($hospital_phones && $hospital_emails)
                                        &nbsp;|&nbsp;
                                    @endif
                                    @if($hospital_emails)
                                        ✉️ {{ $hospital_emails }}
                                    @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endif

                    {{-- ═══════════════ FOOTER ═══════════════ --}}
                    <tr>
                        <td style="background-color: {{ $hospital_color }}10; padding: 20px 32px; border-top: 1px solid #e9ecef; text-align: center;">
                            <p style="margin: 0 0 4px; color: #6c757d; font-size: 12px; font-weight: 600;">
                                {{ $hospital_name }}
                            </p>
                            @if($hospital_address)
                                <p style="margin: 0 0 4px; color: #999; font-size: 11px;">
                                    {{ $hospital_address }}
                                </p>
                            @endif
                            @if($footer_text)
                                <p style="margin: 0 0 4px; color: #999; font-size: 11px;">
                                    {{ $footer_text }}
                                </p>
                            @endif
                            <p style="margin: 8px 0 0; color: #aaa; font-size: 10px;">
                                This is an automated email from {{ $hospital_name }}'s appointment system.
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
