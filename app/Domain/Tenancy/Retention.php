<?php

namespace App\Domain\Tenancy;

use App\Domain\Access\AuditLogger;
use App\Domain\Feedback\Models\FeedbackResponse;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Queue\Models\Customer;
use App\Domain\Queue\Models\Note;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Scheduling\Models\Appointment;

/**
 * Personal-data retention (proposal "Security and privacy"; design Decision
 * 13): after the tenant's retention period, names / phones / emails /
 * free-text are removed; timings and aggregate facts stay for reports.
 */
class Retention
{
    public const PLACEHOLDER = 'Anonymized';

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array<string, int> rows anonymized per kind */
    public function run(): array
    {
        $days = (int) $this->tenants->require()->settings()->get('retention_days', 730);
        if ($days < 30) {
            $days = 30; // guard against accidental wipe-outs
        }
        $cutoff = now()->subDays($days);

        $counts = [
            'tickets' => Ticket::query()->where('checked_in_at', '<', $cutoff)->where('customer_name', '!=', self::PLACEHOLDER)
                ->update(['customer_name' => self::PLACEHOLDER, 'customer_phone' => null]),
            'appointments' => Appointment::query()->where('starts_at', '<', $cutoff)->where('customer_name', '!=', self::PLACEHOLDER)
                ->update(['customer_name' => self::PLACEHOLDER, 'customer_phone' => null, 'customer_email' => null, 'notes' => null]),
            'customers' => Customer::query()->whereNull('anonymized_at')
                ->where(fn ($q) => $q->where('last_visit_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('last_visit_at')->where('created_at', '<', $cutoff)))
                ->update(['name' => self::PLACEHOLDER, 'phone' => null, 'email' => null, 'anonymized_at' => now()]),
            'sms' => SmsMessage::query()->where('created_at', '<', $cutoff)->where('body', '!=', '[redacted]')
                ->update(['to' => '[redacted]', 'body' => '[redacted]']),
            'notes' => Note::query()->where('created_at', '<', $cutoff)->delete(),
            'feedback_comments' => FeedbackResponse::query()->where('submitted_at', '<', $cutoff)->whereNotNull('comment')
                ->update(['comment' => null]),
        ];

        if (array_sum($counts) > 0) {
            $this->audit->log('privacy.retention_applied', meta: ['retention_days' => $days] + $counts);
        }

        return $counts;
    }
}
