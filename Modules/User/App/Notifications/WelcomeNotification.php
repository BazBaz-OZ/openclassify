<?php

declare(strict_types=1);

namespace Modules\User\App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Sell My Junk')
            ->greeting('Welcome, '.$notifiable->name.'!')
            ->line('Your email address has been verified and your Sell My Junk account is ready to use.')
            ->line('You can now create listings and start selling.')
            ->action('Create a Listing', route('panel.listings.create'))
            ->line('Thanks for joining Sell My Junk.');
    }
}
