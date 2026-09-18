<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolRegistrationSubmitted extends Notification
{
    public function __construct(public User $applicant) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->applicant->isEncoder()) {
            return (new MailMessage)
                ->subject('Encoder registration needs your approval')
                ->greeting('School Head')
                ->line("{$this->applicant->name} requested an Encoder account for {$this->applicant->school_name}.")
                ->line('Email: '.$this->applicant->email)
                ->line('Position: '.($this->applicant->position ?: 'Teacher'))
                ->action('Review encoder', url('/school/encoders'))
                ->line('Accept the request if this teacher should encode SGC FAT for your school.');
        }

        return (new MailMessage)
            ->subject('School Head registration needs verification')
            ->greeting('Division Admin')
            ->line("{$this->applicant->name} requested a School Head account for {$this->applicant->school_name}.")
            ->line('Email: '.$this->applicant->email)
            ->line('School ID: '.($this->applicant->school_code ?: '—'))
            ->action('Review registration', url('/division/registrations'))
            ->line('Accept the request to activate the School Head account.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        if ($this->applicant->isEncoder()) {
            return [
                'title' => 'Encoder registration pending',
                'detail' => $this->applicant->name.' · '.$this->applicant->position,
                'href' => '/school/encoders',
                'school_user_id' => $this->applicant->id,
            ];
        }

        return [
            'title' => 'School Head registration pending',
            'detail' => $this->applicant->name.' · '.$this->applicant->school_name,
            'href' => '/division/registrations',
            'school_user_id' => $this->applicant->id,
        ];
    }
}
