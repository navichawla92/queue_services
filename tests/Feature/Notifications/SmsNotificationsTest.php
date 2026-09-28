<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Jobs\SendSms;
use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsOptOut;
use App\Domain\Notifications\Models\SmsSetting;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\NotificationEvent;
use App\Domain\Notifications\Providers\SmsProvider;
use App\Domain\Notifications\Providers\SmsTransientException;
use App\Domain\Notifications\SmsAllowance;
use App\Domain\Notifications\SmsConfig;
use App\Domain\Notifications\SmsContext;
use App\Domain\Notifications\TemplateRenderer;
use App\Domain\Organization\EmployeeShift;
use App\Domain\Organization\Models\Desk;
use App\Domain\Queue\Actor;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\FakeSmsProvider;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class SmsNotificationsTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private FakeSmsProvider $fake;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant, 'America/New_York');

        $this->fake = new FakeSmsProvider;
        $fake = $this->fake;
        $this->app->instance(SmsConfig::class, new class($fake) extends SmsConfig
        {
            public function __construct(private readonly FakeSmsProvider $fake) {}

            public function provider(SmsSetting $settings): SmsProvider
            {
                return $this->fake;
            }
        });
        $this->app->forgetInstance(NotificationDispatcher::class);

        SmsSetting::create(['provider' => 'twilio', 'account_sid' => 'AC123', 'auth_token' => 'secret-token', 'from_number' => '+12025550100']);
    }

    private function bodies(): array
    {
        return array_column($this->fake->sent, 'body');
    }

    public function test_check_in_confirmation_with_consent(): void
    {
        $ticket = $this->issue('Jane Doe', '+12025550123');

        $this->assertCount(1, $this->fake->sent);
        $this->assertSame('+12025550123', $this->fake->sent[0]['to']);
        $this->assertStringContainsString('Hi Jane', $this->bodies()[0]);
        $this->assertStringContainsString('A-001', $this->bodies()[0]);
        $this->assertStringContainsString(route('public.ticket', $ticket->public_token), $this->bodies()[0]);

        $log = SmsMessage::query()->sole();
        $this->assertSame(NotificationEvent::CheckinConfirmation, $log->event);
        $this->assertSame(SmsMessage::SENT, $log->status);
        $this->assertNotNull($log->provider_message_id);
    }

    public function test_no_consent_means_no_sms(): void
    {
        $this->issue('Jane', '+12025550123', ['smsConsent' => false]);

        $this->assertSame([], $this->fake->sent);
        $this->assertSame(0, SmsMessage::query()->count());
    }

    public function test_event_toggles_with_location_override(): void
    {
        NotificationSetting::create(['event' => 'checkin_confirmation', 'enabled' => false]);
        $this->issue('A', '+12025550123');
        $this->assertSame([], $this->fake->sent);

        NotificationSetting::create(['location_id' => $this->location->id, 'event' => 'checkin_confirmation', 'enabled' => true]);
        $this->issue('B', '+12025550124');
        $this->assertCount(1, $this->fake->sent);
    }

    public function test_called_sends_representative_ready_with_desk_and_youre_next_once(): void
    {
        NotificationSetting::create(['event' => 'checkin_confirmation', 'enabled' => false]);
        $maria = $this->onShiftEmployee('Maria');
        $first = $this->issue('First', '+12025550101');
        $second = $this->issue('Second', '+12025550102');
        $this->assertSame([], $this->bodies(), "A-001 was first on arrival: no \"you're next\"");

        app(TicketStateMachine::class)->callNext($maria, $this->location, Actor::system());

        $bodies = $this->bodies();
        $this->assertContains("It's your turn! Ticket A-001: please go to ".$maria->currentDesk->label.'.', $bodies);
        $this->assertContains("{$this->location->name}: you're next! Ticket A-002 — please get ready.", $bodies);

        // Further changes do not repeat "you're next" for A-002.
        app(TicketStateMachine::class)->recall($first, Actor::system());
        $this->assertSame(1, SmsMessage::query()->where('ticket_id', $second->id)->where('event', 'youre_next')->count());
    }

    public function test_position_alert_at_configured_position(): void
    {
        NotificationSetting::create(['event' => 'checkin_confirmation', 'enabled' => false]);
        NotificationSetting::create(['event' => 'youre_next', 'enabled' => false]);
        $maria = $this->onShiftEmployee();
        foreach (range(1, 4) as $i) {
            $this->issue("C$i", '+1202555010'.$i);
        }
        $this->assertSame([], $this->fake->sent, 'no alerts at check-in');

        // C1 is called: C4 moves from 4th to 3rd.
        app(TicketStateMachine::class)->callNext($maria, $this->location, Actor::system());

        $alerts = SmsMessage::query()->where('event', 'position_update')->get();
        $this->assertCount(1, $alerts);
        $this->assertSame('+12025550104', $alerts[0]->to);
        $this->assertStringContainsString('number 3 in line', $alerts[0]->body);
    }

    public function test_desk_change_is_texted_to_called_customer(): void
    {
        NotificationSetting::create(['event' => 'checkin_confirmation', 'enabled' => false]);
        $maria = $this->onShiftEmployee('Maria');
        $this->issue('Jane', '+12025550123');
        app(TicketStateMachine::class)->callNext($maria, $this->location, Actor::system());
        $room = Desk::factory()->create(['location_id' => $this->location->id, 'label' => 'Room B']);

        app(EmployeeShift::class)->changeDesk($maria->fresh(), $room);

        $this->assertContains('Ticket A-001: please go to Room B instead.', $this->bodies());
    }

    public function test_opted_out_number_is_suppressed_and_logged(): void
    {
        SmsOptOut::create(['phone' => '+12025550123', 'opted_out_at' => now()]);

        $this->issue('Jane', '+12025550123');

        $this->assertSame([], $this->fake->sent);
        $log = SmsMessage::query()->sole();
        $this->assertSame(SmsMessage::SUPPRESSED, $log->status);
        $this->assertSame('opted out', $log->status_reason);
    }

    public function test_stop_and_start_keywords_via_signed_webhook(): void
    {
        $url = route('public.webhooks.sms.inbound', $this->tenant->public_id);
        $this->tenantContext()->clear();

        $params = ['From' => '+12025550123', 'Body' => 'stop'];
        $this->post($url, $params, ['X-Twilio-Signature' => $this->sign($url, $params)])->assertOk();
        $this->assertTrue($this->inTenant($this->tenant, fn () => SmsOptOut::query()->where('phone', '+12025550123')->exists()));

        $help = ['From' => '+12025550123', 'Body' => 'HELP'];
        $this->post($url, $help, ['X-Twilio-Signature' => $this->sign($url, $help)])->assertOk()->assertSee('Reply STOP to opt out', false);

        $start = ['From' => '+12025550123', 'Body' => 'START'];
        $this->post($url, $start, ['X-Twilio-Signature' => $this->sign($url, $start)])->assertOk();
        $this->assertFalse($this->inTenant($this->tenant, fn () => SmsOptOut::query()->exists()));
    }

    public function test_forged_callback_is_rejected_without_changes(): void
    {
        $this->issue('Jane', '+12025550123');
        $log = SmsMessage::query()->sole();
        $url = route('public.webhooks.sms.status', $this->tenant->public_id);
        $this->tenantContext()->clear();

        $this->post($url, ['MessageSid' => $log->provider_message_id, 'MessageStatus' => 'delivered'], ['X-Twilio-Signature' => 'forged'])
            ->assertForbidden();

        $this->assertSame(SmsMessage::SENT, $this->inTenant($this->tenant, fn () => $log->fresh()->status));
    }

    public function test_delivery_receipt_updates_status_and_never_moves_backwards(): void
    {
        $this->issue('Jane', '+12025550123');
        $log = SmsMessage::query()->sole();
        $url = route('public.webhooks.sms.status', $this->tenant->public_id);
        $this->tenantContext()->clear();

        $delivered = ['MessageSid' => $log->provider_message_id, 'MessageStatus' => 'delivered'];
        $this->post($url, $delivered, ['X-Twilio-Signature' => $this->sign($url, $delivered)])->assertNoContent();
        $late = ['MessageSid' => $log->provider_message_id, 'MessageStatus' => 'sent'];
        $this->post($url, $late, ['X-Twilio-Signature' => $this->sign($url, $late)])->assertNoContent();

        $fresh = $this->inTenant($this->tenant, fn () => $log->fresh());
        $this->assertSame(SmsMessage::DELIVERED, $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
    }

    public function test_transient_failure_is_retried_then_fails_after_last_attempt(): void
    {
        Queue::fake(); // run the job by hand to observe retry behaviour
        $this->fake->failWith = 'transient';
        $message = app(NotificationDispatcher::class)->send(new SmsContext(
            NotificationEvent::FeedbackRequest, '+12025550123', true, $this->location->id, ['first_name' => 'Jane', 'location' => 'Main', 'feedback_link' => 'x'],
            sendAt: CarbonImmutable::parse('2026-10-05 12:00', 'America/New_York'),
        ));
        Queue::assertPushed(SendSms::class);
        $job = new SendSms($message->id);

        try {
            $job->handle(app(SmsConfig::class), app(SmsAllowance::class));
            $this->fail('Transient errors must be rethrown for retry');
        } catch (SmsTransientException) {
        }
        $this->assertSame(SmsMessage::QUEUED, $message->fresh()->status);

        $job->tries = 1; // last attempt
        $job->handle(app(SmsConfig::class), app(SmsAllowance::class));
        $this->assertSame(SmsMessage::FAILED, $message->fresh()->status);
        $this->assertStringContainsString('retries exhausted', $message->fresh()->status_reason);
    }

    public function test_permanent_failure_is_logged_and_queue_action_still_succeeds(): void
    {
        $this->fake->failWith = 'permanent';

        $ticket = $this->issue('Jane', '+12025550123');

        $this->assertNotNull($ticket->id);
        $log = SmsMessage::query()->sole();
        $this->assertSame(SmsMessage::FAILED, $log->status);
        $this->assertSame('21211', $log->error_code);
    }

    public function test_stale_time_sensitive_message_is_skipped(): void
    {
        NotificationSetting::create(['event' => 'checkin_confirmation', 'enabled' => false]);
        $ticket = $this->issue('Jane', '+12025550123', ['smsConsent' => true]);
        $ticket->forceFill(['status' => 'completed'])->save();

        $message = SmsMessage::create(['ticket_id' => $ticket->id, 'to' => '+12025550123', 'event' => NotificationEvent::YoureNext, 'body' => 'x', 'status' => SmsMessage::QUEUED]);
        (new SendSms($message->id))->handle(app(SmsConfig::class), app(SmsAllowance::class));

        $this->assertSame(SmsMessage::SKIPPED, $message->fresh()->status);
        $this->assertSame('stale', $message->fresh()->status_reason);
    }

    public function test_feedback_request_in_quiet_hours_is_deferred_to_morning(): void
    {
        // 20:55 + 10 min delay = 21:05 New York → quiet hours (21:00–08:00)
        $at = CarbonImmutable::parse('2026-10-05 21:05', 'America/New_York');

        $message = app(NotificationDispatcher::class)->send(new SmsContext(
            NotificationEvent::FeedbackRequest, '+12025550123', true, $this->location->id, ['first_name' => 'Jane'], sendAt: $at,
        ));

        $this->assertSame('2026-10-06 08:00', $message->fresh()->scheduled_for->setTimezone('America/New_York')->format('Y-m-d H:i'));
    }

    public function test_template_renderer_placeholders_and_segments(): void
    {
        $r = app(TemplateRenderer::class);

        $this->assertSame('Hi Jane, ticket A-1', $r->render('Hi {{first_name}}, ticket {{ ticket_number }}', ['first_name' => 'Jane', 'ticket_number' => 'A-1']));
        $this->assertSame(['favourite_color'], $r->unknownPlaceholders('Hi {{first_name}} {{favourite_color}}'));
        $this->assertSame(1, $r->segments(str_repeat('a', 160)));
        $this->assertSame(2, $r->segments(str_repeat('a', 161)));
        $this->assertSame(1, $r->segments(str_repeat('é', 70)));  // é is GSM
        $this->assertSame(2, $r->segments(str_repeat('ñ', 100).'😀'));  // UCS-2
    }

    public function test_sms_to_other_tenants_customers_is_isolated(): void
    {
        $this->issue('Jane', '+12025550123');
        [, $b] = [$this->tenant, Tenant::query()->where('id', '!=', $this->tenant->id)->first()];

        $this->assertSame(0, $this->inTenant($b, fn () => SmsMessage::query()->count()));
    }

    /** @param  array<string, string>  $params */
    private function sign(string $url, array $params): string
    {
        return (new RequestValidator('secret-token'))->computeSignature($url, $params);
    }
}
