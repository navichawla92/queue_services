<?php

namespace App\Domain\Notifications;

/** SMS events (sms-notifications "Notification events"). */
enum NotificationEvent: string
{
    case CheckinConfirmation = 'checkin_confirmation';
    case WaitUpdate = 'wait_update';
    case PositionUpdate = 'position_update';
    case YoureNext = 'youre_next';
    case RepresentativeReady = 'representative_ready';
    case DeskChange = 'desk_change';
    case Transfer = 'transfer';
    case AppointmentConfirmation = 'appointment_confirmation';
    case AppointmentReminder = 'appointment_reminder';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case AppointmentCancelled = 'appointment_cancelled';
    case FeedbackRequest = 'feedback_request';

    public function label(): string
    {
        return match ($this) {
            self::CheckinConfirmation => __('Check-in confirmation'),
            self::WaitUpdate => __('Wait time update'),
            self::PositionUpdate => __('Queue position update'),
            self::YoureNext => __("You're next"),
            self::RepresentativeReady => __('Representative ready (called)'),
            self::DeskChange => __('Desk / room change'),
            self::Transfer => __('Transferred'),
            self::AppointmentConfirmation => __('Appointment confirmation'),
            self::AppointmentReminder => __('Appointment reminder'),
            self::AppointmentRescheduled => __('Appointment rescheduled'),
            self::AppointmentCancelled => __('Appointment cancelled'),
            self::FeedbackRequest => __('Feedback request'),
        };
    }

    /** Non-urgent messages respect quiet hours (deferred until they end). */
    public function respectsQuietHours(): bool
    {
        return in_array($this, [self::AppointmentReminder, self::FeedbackRequest], true);
    }

    /** Only meaningful while the ticket is still in the queue; skipped if stale. */
    public function isTimeSensitive(): bool
    {
        return in_array($this, [self::WaitUpdate, self::PositionUpdate, self::YoureNext, self::RepresentativeReady, self::DeskChange, self::Transfer], true);
    }

    public function defaultEnabled(): bool
    {
        return $this !== self::WaitUpdate;
    }

    /** Default English template; placeholders in {{ braces }}. */
    public function defaultTemplate(): string
    {
        return match ($this) {
            self::CheckinConfirmation => 'Hi {{first_name}}, you are checked in at {{location}}. Your ticket is {{ticket_number}}, estimated wait {{wait}}. Track your place: {{status_link}}',
            self::WaitUpdate => '{{location}}: your estimated wait for ticket {{ticket_number}} is now {{wait}}. {{status_link}}',
            self::PositionUpdate => '{{location}}: you are number {{position}} in line (ticket {{ticket_number}}). Please stay nearby.',
            self::YoureNext => "{{location}}: you're next! Ticket {{ticket_number}} — please get ready.",
            self::RepresentativeReady => "It's your turn! Ticket {{ticket_number}}: please go to {{desk}}.",
            self::DeskChange => 'Ticket {{ticket_number}}: please go to {{desk}} instead.',
            self::Transfer => 'Ticket {{ticket_number}} has been transferred to {{department}}. We will call you soon. {{status_link}}',
            self::AppointmentConfirmation => 'Hi {{first_name}}, your {{service}} appointment at {{location}} is confirmed for {{appointment_time}}. Code: {{confirmation_code}}. Manage: {{manage_link}}',
            self::AppointmentReminder => 'Reminder: {{service}} at {{location}} on {{appointment_time}}. Check in on arrival: {{checkin_link}} Manage: {{manage_link}}',
            self::AppointmentRescheduled => 'Your {{service}} appointment at {{location}} is now {{appointment_time}}. Manage: {{manage_link}}',
            self::AppointmentCancelled => 'Your {{service}} appointment at {{location}} on {{appointment_time}} has been cancelled.',
            self::FeedbackRequest => 'Thanks for visiting {{location}}, {{first_name}}! How did we do? {{feedback_link}}',
        };
    }
}
