<?php

namespace App\Notifications;

use App\Models\Assessment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PacketValidated extends Notification
{
    public function __construct(public Assessment $assessment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $result = $this->assessment->result === 'functional'
            ? 'Functional (10 of 12 FIs validated).'
            : 'Not yet functional.';

        return (new MailMessage)
            ->subject('SGC FAT result posted')
            ->line('Division finished validation. Result: '.$result)
            ->action('View result', url('/school/submit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'SGC FAT result posted',
            'detail' => $this->assessment->result === 'functional' ? 'Functional (10/12).' : 'Not yet functional.',
            'href' => '/school/submit',
        ];
    }
}
