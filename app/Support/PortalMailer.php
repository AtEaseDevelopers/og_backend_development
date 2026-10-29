<?php

namespace App\Support;

use App\Mail\SystemNotificationMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Account emails of the customer portal (email verification, password reset). They always go out: unlike the
 * business notifications they are not switched off by "Send system-generated emails" or a customer's opt-out.
 */
class PortalMailer
{
    public static function send(string $email, string $subject, string $body): bool
    {
        try {
            MailSettings::apply();
            Mail::to($email)->send(new SystemNotificationMail($subject, $body));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
