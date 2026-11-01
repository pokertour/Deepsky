<?php

namespace App\Mail;

use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class NewAstrophotoPublished extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Astrophoto $astrophoto,
        public NewsletterSubscriber $subscriber,
    ) {}

    public function send($mailer)
    {
        if (! Astrophoto::query()->published()->whereKey($this->astrophoto->id)->exists()) {
            return null;
        }

        return parent::send($mailer);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Nouvelle astrophoto : :title', ['title' => $this->astrophoto->localized('title')]),
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => '<'.route('newsletter.unsubscribe', $this->subscriber->unsubscribe_token).'>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-astrophoto',
            with: [
                'unsubscribeUrl' => route('newsletter.unsubscribe', $this->subscriber->unsubscribe_token),
            ],
        );
    }
}
