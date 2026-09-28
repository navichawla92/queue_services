<?php

namespace Tests\Feature\Feedback;

use App\Domain\Access\Roles;
use App\Domain\Feedback\Models\FeedbackRequest;
use App\Domain\Feedback\Models\FeedbackResponse;
use App\Domain\Feedback\Notifications\LowFeedbackScore;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Actor;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\Admin\FeedbackReview;
use App\Livewire\PublicSite\FeedbackForm;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 14:00', 'UTC'));
    }

    /** Serve one customer to completion; returns the ticket. */
    private function serve(string $phone = '+12025550123', bool $consent = true): Ticket
    {
        $maria = Employee::query()->first() ?? $this->onShiftEmployee('Maria');
        $ticket = $this->issue('Jane Doe', $phone, ['smsConsent' => $consent]);
        $queue = app(TicketStateMachine::class);
        $queue->call($ticket, $maria->fresh(), Actor::system());
        $queue->start($ticket, Actor::system());
        $queue->complete($ticket, Actor::system());

        return $ticket->fresh();
    }

    public function test_completion_sends_one_delayed_feedback_request_with_link(): void
    {
        $ticket = $this->serve();

        $request = FeedbackRequest::query()->sole();
        $this->assertSame($ticket->id, $request->ticket_id);
        $sms = SmsMessage::query()->where('event', 'feedback_request')->sole();
        $this->assertStringContainsString($request->url(), $sms->body);
        $this->assertSame('14:10', $sms->scheduled_for->format('H:i'));
    }

    public function test_no_request_without_consent_or_when_location_disabled(): void
    {
        $this->serve('+12025550123', consent: false);
        $this->assertSame(0, FeedbackRequest::query()->count());

        $this->location->update(['feedback_enabled' => false]);
        $this->serve('+12025550124');
        $this->assertSame(0, FeedbackRequest::query()->count());
    }

    public function test_cooldown_prevents_second_request_within_seven_days(): void
    {
        $this->serve();
        $this->travel(2)->days();
        $this->serve();
        $this->assertSame(1, FeedbackRequest::query()->count());

        $this->travel(6)->days();
        $this->serve();
        $this->assertSame(2, FeedbackRequest::query()->count());
    }

    public function test_submit_attributes_to_visit_and_link_is_single_use(): void
    {
        $ticket = $this->serve();
        $request = FeedbackRequest::query()->sole();
        $this->tenantContext()->clear();
        $this->get('/f/'.$request->token)->assertOk()->assertSee('How did we do?');
        $this->get('/f/nope')->assertNotFound();
        $this->actingAsTenant($this->tenant);

        Livewire::test(FeedbackForm::class, ['feedback' => $request->token])
            ->call('submit')->assertHasErrors('rating')
            ->set('rating', 4)->set('comment', 'Quick and friendly')->call('submit')
            ->assertSee('Thank you for your feedback!');

        $response = FeedbackResponse::query()->sole();
        $this->assertSame(4, $response->rating);
        $this->assertSame($ticket->serving_employee_id, $response->employee_id);
        $this->assertSame($ticket->department_id, $response->department_id);
        $this->assertSame($this->location->id, $response->location_id);

        Livewire::test(FeedbackForm::class, ['feedback' => $request->token])
            ->assertSee("Thanks, we've already received your feedback.");
    }

    public function test_expired_link(): void
    {
        $this->serve();
        $request = FeedbackRequest::query()->sole();
        $this->travel(8)->days();

        Livewire::test(FeedbackForm::class, ['feedback' => $request->token])->assertSee('This feedback link has expired.');
    }

    public function test_extra_questions_are_recorded(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['feedback_questions' => ['Wait time?', 'Staff courtesy?']])])->save();
        $this->serve();
        $request = FeedbackRequest::query()->sole();

        Livewire::test(FeedbackForm::class, ['feedback' => $request->token])
            ->assertSee('Staff courtesy?')
            ->set('rating', 5)->set('extra.1', 4)->call('submit');

        $this->assertSame([['question' => 'Staff courtesy?', 'rating' => 4]], FeedbackResponse::query()->sole()->answers);
    }

    public function test_low_score_alerts_location_managers_and_admins_only(): void
    {
        Notification::fake();
        $admin = $this->userIn($this->tenant);
        $admin->assignRole(Roles::COMPANY_ADMIN);
        $here = $this->userIn($this->tenant);
        $here->assignRole(Roles::LOCATION_MANAGER);
        $here->locations()->attach($this->location);
        $elsewhere = $this->userIn($this->tenant);
        $elsewhere->assignRole(Roles::LOCATION_MANAGER);
        $elsewhere->locations()->attach(Location::factory()->create());

        $this->serve();
        Livewire::test(FeedbackForm::class, ['feedback' => FeedbackRequest::query()->sole()->token])
            ->set('rating', 1)->set('comment', 'Waited forever')->call('submit');

        Notification::assertSentTo([$admin, $here], LowFeedbackScore::class);
        Notification::assertNotSentTo($elsewhere, LowFeedbackScore::class);
    }

    public function test_review_filters_and_scope_and_employee_self_view(): void
    {
        $this->serve();
        Livewire::test(FeedbackForm::class, ['feedback' => FeedbackRequest::query()->sole()->token])->set('rating', 3)->call('submit');
        $other = Location::factory()->create();
        FeedbackResponse::query()->first()->replicate()->forceFill(['location_id' => $other->id, 'rating' => 5, 'feedback_request_id' => FeedbackRequest::create([
            'ticket_id' => $this->issue('X')->id, 'location_id' => $other->id, 'token' => 'tok'.uniqid(), 'expires_at' => now()->addDay(),
        ])->id])->save();

        $manager = $this->userIn($this->tenant);
        $manager->assignRole(Roles::LOCATION_MANAGER);
        $manager->locations()->attach($this->location);
        $this->actingAs($manager);
        Livewire::test(FeedbackReview::class)->assertSee('3.00')->assertSee('1 response');

        $employee = Employee::query()->first();
        $employee->user->assignRole(Roles::EMPLOYEE);
        $this->actingAs($employee->user);
        $this->tenantContext()->clear();
        $this->get('/staff/my-feedback')->assertForbidden();
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['employees_view_own_feedback' => true])])->save();
        $this->get('/staff/my-feedback')->assertOk()->assertSee('My feedback');
    }
}
