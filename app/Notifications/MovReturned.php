<?php

namespace App\Notifications;

use App\Models\Mov;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MovReturned extends Notification
{
    public function __construct(public Mov $mov) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->mov->code.' MOV returned')
            ->line('Division returned '.$this->mov->code.'. Replace only this file, certify School Head QA again, then resubmit.')
            ->line('Reason: '.($this->mov->return_reason ?: 'Invalid MOV'))
            ->action('Replace MOV', url('/school/movs'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->mov->code.' MOV returned',
            'detail' => $this->mov->return_reason ?: 'Replace the invalid file, then QA and resubmit.',
            'href' => '/school/movs',
        ];
    }
}
