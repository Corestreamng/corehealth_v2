<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * AppointmentNotificationMail
 *
 * A simple Mailable that wraps pre-rendered HTML content
 * for appointment notification emails.
 */
class AppointmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlContent;
    public string $fromAddr;
    public string $fromNameStr;

    public function __construct(string $subject, string $htmlContent, string $fromAddress, string $fromName)
    {
        $this->subject = $subject;
        $this->htmlContent = $htmlContent;
        $this->fromAddr = $fromAddress;
        $this->fromNameStr = $fromName;
    }

    public function build()
    {
        return $this->from($this->fromAddr, $this->fromNameStr)
                    ->subject($this->subject)
                    ->html($this->htmlContent);
    }
}
