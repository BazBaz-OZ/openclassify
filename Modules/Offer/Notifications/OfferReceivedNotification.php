<?php

declare(strict_types=1);

namespace Modules\Offer\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $amount,
        private readonly string $listingTitle,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) $notifiable->name);

        return (new MailMessage)
            ->subject('New offer on '.$this->listingTitle)
            ->greeting($name !== '' ? 'Hi '.$name.',' : 'Hi,')
            ->line(
                'You received a new offer of '
                .$this->amount
                .' for '
                .$this->listingTitle
                .'.'
            )
            ->action('View Offer', route('panel.offers.index'))
            ->line('Sign in to Sell My Junk to review, accept or decline the offer.');
    }
}
