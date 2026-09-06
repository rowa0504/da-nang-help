<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\Offer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferAcceptedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function envelope(): Envelope
    {
        $offer = Offer::with('serviceRequest')->find($this->notification->data['offer_id']);

        return new Envelope(
            subject: __('notifications.offer_accepted.subject', ['title' => $offer?->serviceRequest?->title ?? '']),
        );
    }

    public function content(): Content
    {
        $offer = Offer::with('serviceRequest')->find($this->notification->data['offer_id']);

        return new Content(
            view: 'emails.notification',
            with: [
                'greeting' => __('notifications.greeting', ['name' => $this->notification->user->name]),
                'body' => __('notifications.offer_accepted.body', [
                    'title' => $offer?->serviceRequest?->title ?? '',
                ]),
            ],
        );
    }
}
