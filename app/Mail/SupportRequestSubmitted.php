<?php

namespace App\Mail;

use App\Models\SupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emails support recipients when a user submits an in-app support request.
 */
class SupportRequestSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportRequest $supportRequest) {}

    public function envelope(): Envelope
    {
        $category = $this->supportRequest->category->label();

        return new Envelope(
            subject: "[Velsa support] {$category}: {$this->supportRequest->subject}",
        );
    }

    public function content(): Content
    {
        $request = $this->supportRequest;

        return new Content(
            markdown: 'mail.support-request',
            with: [
                'category' => $request->category->label(),
                'subject' => $request->subject,
                'body' => $request->body,
                'fromName' => $request->user?->name,
                'fromEmail' => $request->user?->email,
                'pageUrl' => $request->page_url,
                'appVersion' => $request->app_version,
            ],
        );
    }
}
