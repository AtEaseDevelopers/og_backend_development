<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{data: string, name: string, mime?: string}|null  $attachment  in-memory file (e.g. a generated PDF)
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?array $attachment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.system-notification',
            with: ['body' => $this->body],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->attachment) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->attachment['data'], $this->attachment['name'])
                ->withMime($this->attachment['mime'] ?? 'application/pdf'),
        ];
    }
}
