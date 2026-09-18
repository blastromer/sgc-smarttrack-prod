<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolRegistrationApproved extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($notifiable->role === 'school_head') {
            return (new MailMessage)
                ->subject('Your SGC SmartTrack School Head account is active')
                ->greeting('School Head')
                ->line('Division Admin accepted your School Head registration for SDO Cadiz City.')
                ->line('You can now sign in, certify School Head QA, and approve Encoder (teacher) registrations for your school.')
                ->action('Sign in to SmartTrack', url('/login'));
        }

        return (new MailMessage)
            ->subject('Your SGC SmartTrack Encoder account is active')
            ->greeting('Encoder')
            ->line('Your School Head accepted your Encoder registration.')
            ->line('You can now sign in, encode functionality indicators, and upload MOVs.')
            ->action('Sign in to SmartTrack', url('/login'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account activated',
            'detail' => $notifiable->role === 'school_head'
                ? 'Division Admin accepted your School Head registration.'
                : 'Your School Head accepted your Encoder registration.',
            'href' => '/school',
        ];
    }
}
