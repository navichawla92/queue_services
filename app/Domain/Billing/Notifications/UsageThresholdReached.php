<?php

namespace App\Domain\Billing\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** saas-plans-usage "Limit enforcement": warning at 80% and 100% (banner + email). */
class UsageThresholdReached extends Notification
{
    public function __construct(
        public readonly string $limit,
        public readonly int $threshold,
        public readonly int $used,
        public readonly int $max,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['kind' => 'usage', 'limit' => $this->limit, 'threshold' => $this->threshold, 'used' => $this->used, 'max' => $this->max];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You have used :pct% of your plan (:limit)', ['pct' => $this->threshold, 'limit' => $this->limit]))
            ->line(__('Current usage: :used of :max.', ['used' => $this->used, 'max' => $this->max]))
            ->action(__('View usage'), route('admin.usage'));
    }
}
