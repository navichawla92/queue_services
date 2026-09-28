<?php

namespace App\Domain\Feedback\Notifications;

use App\Domain\Feedback\Models\FeedbackResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** customer-feedback "Low-score alerts": in-app, plus email if enabled. */
class LowFeedbackScore extends Notification
{
    use Queueable;

    public function __construct(
        public readonly FeedbackResponse $response,
        private readonly bool $email = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->email ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'low_feedback',
            'response_id' => $this->response->id,
            'rating' => $this->response->rating,
            'ticket' => $this->response->ticket->number,
            'location' => $this->response->location->name,
            'employee' => $this->response->employee?->display_name,
            'comment' => $this->response->comment,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject(__('Low feedback score (:rating/5) at :location', ['rating' => $d['rating'], 'location' => $d['location']]))
            ->line(__('Ticket :ticket served by :employee rated the visit :rating/5.', ['ticket' => $d['ticket'], 'employee' => $d['employee'] ?? '—', 'rating' => $d['rating']]))
            ->line($d['comment'] ? '"'.$d['comment'].'"' : __('No comment.'))
            ->action(__('Review feedback'), route('admin.feedback'));
    }
}
