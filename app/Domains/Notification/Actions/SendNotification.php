<?php

namespace App\Domains\Notification\Actions;

use App\Domains\Notification\Models\NotificationLog;
use App\Mail\SystemNotificationMail;
use App\Support\CurrentBranch;
use App\Support\CurrentCompany;
use App\Support\MailSettings;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Records and (where possible) delivers a notification for an order-flow event.
 *
 * Email is sent through the configured mailer when the Admin switch is on.
 * WhatsApp has no API integration yet: a pre-filled wa.me link is stored so staff
 * can send it with one click from the document page; the log still tracks it.
 */
class SendNotification
{
    /**
     * Emails sent after the response (user, 11 Nov 2026): set while an order is created, so the page does not wait
     * for the mail server; the log shows "sending" until the mail is out. Off everywhere else (an action that tells
     * the user whether the email went out still sends it at once).
     */
    public static bool $afterResponse = false;

    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null, type?: string}  $recipient
     * @param  list<string>  $channels
     * @return Collection<int, NotificationLog>
     */
    /**
     * @param  array{data: string, name: string, mime?: string}|null  $attachment  emailed as a file (e.g. invoice PDF)
     */
    public function execute(
        string $event,
        array $recipient,
        string $subject,
        string $message,
        ?Model $related = null,
        array $channels = [NotificationLog::CHANNEL_EMAIL, NotificationLog::CHANNEL_WHATSAPP],
        ?array $attachment = null,
    ): Collection {
        $logs = collect();

        foreach ($channels as $channel) {
            $logs->push($this->deliver($channel, $event, $recipient, $subject, $message, $related, $attachment));
        }

        return $logs;
    }

    /** @param  array{name?: string|null, email?: string|null, phone?: string|null, type?: string}  $recipient */
    private function deliver(string $channel, string $event, array $recipient, string $subject, string $message, ?Model $related, ?array $attachment = null): NotificationLog
    {
        $log = new NotificationLog([
            'company_id' => $related?->company_id ?? CurrentCompany::id(),
            'branch_id' => $related?->source_branch_id ?? $related?->branch_id ?? CurrentBranch::id(),
            'event' => $event,
            'channel' => $channel,
            'recipient_type' => $recipient['type'] ?? 'customer',
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_contact' => $channel === NotificationLog::CHANNEL_EMAIL
                ? ($recipient['email'] ?? null)
                : ($recipient['phone'] ?? $recipient['email'] ?? null),
            'subject' => $subject,
            'message' => $message,
            'created_by' => auth()->id(),
        ]);

        if ($related) {
            $log->notifiable()->associate($related);
        }

        match ($channel) {
            NotificationLog::CHANNEL_EMAIL => $this->sendEmail($log, $recipient, $attachment),
            NotificationLog::CHANNEL_WHATSAPP => $this->prepareWhatsApp($log, $recipient),
            default => $log->fill(['status' => NotificationLog::STATUS_SENT, 'sent_at' => now()]),
        };

        $log->save();

        return $log;
    }

    /** @param  array{email?: string|null}  $recipient */
    private function sendEmail(NotificationLog $log, array $recipient, ?array $attachment = null): void
    {
        if (! SystemSettings::bool(SystemSettings::EMAILS_ENABLED)) {
            $log->fill(['status' => NotificationLog::STATUS_SKIPPED, 'error' => 'Emails disabled by Admin']);

            return;
        }

        $email = $recipient['email'] ?? null;

        if (! $email) {
            $log->fill(['status' => NotificationLog::STATUS_SKIPPED, 'error' => 'No email address']);

            return;
        }

        $send = function () use ($log, $email, $attachment): void {
            try {
                // the SMTP account set up under System Settings (falls back to the .env mailer)
                MailSettings::apply();
                Mail::to($email)->send(new SystemNotificationMail($log->subject, $log->message, $attachment));
                $log->fill(['status' => NotificationLog::STATUS_SENT, 'sent_at' => now(), 'error' => null]);
            } catch (Throwable $e) {
                $log->fill(['status' => NotificationLog::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        };

        if (static::$afterResponse && ! app()->runningInConsole()) {
            // sent once the response is out; the log (saved by deliver) is updated then
            $log->fill(['status' => NotificationLog::STATUS_SENDING]);
            \Illuminate\Support\defer(function () use ($send, $log): void {
                $send();
                $log->exists ? $log->save() : null;
            });

            return;
        }

        $send();
    }

    /** @param  array{phone?: string|null}  $recipient */
    private function prepareWhatsApp(NotificationLog $log, array $recipient): void
    {
        if (! SystemSettings::bool(SystemSettings::WHATSAPP_ENABLED)) {
            $log->fill(['status' => NotificationLog::STATUS_SKIPPED, 'error' => 'WhatsApp disabled by Admin']);

            return;
        }

        $phone = preg_replace('/\D+/', '', (string) ($recipient['phone'] ?? ''));

        if ($phone === '') {
            $log->fill(['status' => NotificationLog::STATUS_SKIPPED, 'error' => 'No phone number']);

            return;
        }

        if (str_starts_with($phone, '0')) {
            $phone = '60'.substr($phone, 1); // Malaysian local → international
        }

        $log->fill([
            'status' => NotificationLog::STATUS_READY,
            'whatsapp_url' => 'https://wa.me/'.$phone.'?text='.rawurlencode($log->subject."\n\n".$log->message),
        ]);
    }
}
