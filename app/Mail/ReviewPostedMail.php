<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\Review;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewPostedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('notifications.review_posted.subject'),
        );
    }

    public function content(): Content
    {
        $review = Review::with('job.serviceRequest')->find($this->notification->data['review_id']);

        return new Content(
            view: 'emails.notification',
            with: [
                'greeting' => __('notifications.greeting', ['name' => $this->notification->user->name]),
                'body' => __('notifications.review_posted.body', [
                    'title' => $review?->job?->serviceRequest?->title ?? '',
                    'rating' => $review?->rating ?? '',
                ]),
            ],
        );
    }
}
