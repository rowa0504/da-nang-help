<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\ServiceJob;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobCompletionReportedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function envelope(): Envelope
    {
        $job = ServiceJob::with('serviceRequest')->find($this->notification->data['job_id']);

        return new Envelope(
            subject: __('notifications.job_completion_reported.subject', ['title' => $job?->serviceRequest?->title ?? '']),
        );
    }

    public function content(): Content
    {
        $job = ServiceJob::with('serviceRequest')->find($this->notification->data['job_id']);

        return new Content(
            view: 'emails.notification',
            with: [
                'greeting' => __('notifications.greeting', ['name' => $this->notification->user->name]),
                'body' => __('notifications.job_completion_reported.body', [
                    'title' => $job?->serviceRequest?->title ?? '',
                ]),
            ],
        );
    }
}
