<?php

namespace App\Notifications;

use App\Models\CoachingSession;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachingScheduled extends Notification
{
    public function __construct(
        public CoachingSession $session,
        public string $schoolName,
        public bool $rescheduled = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->session->scheduled_at?->format('D, M j, Y g:i A') ?: 'the scheduled time';
        $mode = $this->session->mode === 'online' ? 'online' : 'on-site';

        return (new MailMessage)
            ->subject($this->rescheduled ? 'SGC coaching rescheduled' : 'SGC coaching scheduled')
            ->line('Division scheduled governance coaching for '.$this->schoolName.'.')
            ->line($when.' · '.$mode.($this->session->venue ? ' · '.$this->session->venue : ''))
            ->line($this->session->focus ? 'Focus: '.$this->session->focus : 'Review the School Governance Improvement Plan before the session.')
            ->action('Open improvement plan', url('/school/improvement-plan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $when = $this->session->scheduled_at?->format('M j, g:i A') ?: 'soon';

        return [
            'title' => $this->rescheduled ? 'SGC coaching rescheduled' : 'SGC coaching scheduled',
            'detail' => $this->schoolName.' · '.$when.($this->session->focus ? ' · '.$this->session->focus : ''),
            'href' => '/school/improvement-plan',
            'kind' => 'coaching',
        ];
    }
}
