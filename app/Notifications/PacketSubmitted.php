<?php

namespace App\Notifications;

use App\Models\Assessment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PacketSubmitted extends Notification
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
        $school = $this->assessment->user?->school_name ?: 'A school';

        return (new MailMessage)
            ->subject('SGC FAT packet submitted for validation')
            ->line($school.' submitted a 2026 SGC Functionality Assessment packet.')
            ->action('Open validation queue', url('/division/queue'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New SGC FAT packet',
            'detail' => ($this->assessment->user?->school_name ?: 'School').' submitted for validation.',
            'href' => '/division/queue/'.$this->assessment->id,
        ];
    }
}
