<?php

namespace App\Notifications;

use App\Models\Mov;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MovRemovalRequested extends Notification
{
    public function __construct(public Mov $mov, public User $encoder) {}

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
            ->subject('Removal requested: '.$this->mov->code)
            ->line($this->encoder->name.' asked to remove '.$this->mov->code.' ('.($this->mov->original_name ?: 'uploaded file').').')
            ->line('The file is not yet accepted by Division. Open MOV files to remove it, or leave it in place.')
            ->action('Open MOV files', url('/school/movs'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Removal requested: '.$this->mov->code,
            'detail' => $this->encoder->name.' asked to remove '.($this->mov->original_name ?: $this->mov->code).'.',
            'href' => '/school/movs',
        ];
    }
}
