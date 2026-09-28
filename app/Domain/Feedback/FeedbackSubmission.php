<?php

namespace App\Domain\Feedback;

use App\Domain\Access\Roles;
use App\Domain\Feedback\Models\FeedbackRequest;
use App\Domain\Feedback\Models\FeedbackResponse;
use App\Domain\Feedback\Notifications\LowFeedbackScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Stores a response (single use, not expired), attributes it to the visit's
 * employee / department / service / location, and alerts that location's
 * managers when it is at or below the threshold (customer-feedback spec).
 */
class FeedbackSubmission
{
    /**
     * @param  list<array{question: string, rating: int}>  $answers
     *
     * @throws RuntimeException when the link was used or expired
     */
    public function submit(FeedbackRequest $request, int $rating, array $answers = [], ?string $comment = null): FeedbackResponse
    {
        $response = DB::transaction(function () use ($request, $rating, $answers, $comment) {
            $fresh = FeedbackRequest::query()->with('ticket')->lockForUpdate()->findOrFail($request->id);
            if (! $fresh->isUsable()) {
                throw new RuntimeException('Feedback link already used or expired.');
            }

            $ticket = $fresh->ticket;
            $response = FeedbackResponse::create([
                'feedback_request_id' => $fresh->id,
                'ticket_id' => $ticket->id,
                'location_id' => $ticket->location_id,
                'department_id' => $ticket->department_id,
                'service_id' => $ticket->service_id,
                'employee_id' => $ticket->serving_employee_id,
                'rating' => max(1, min(5, $rating)),
                'answers' => $answers ?: null,
                'comment' => $comment ?: null,
                'submitted_at' => now(),
            ]);
            $fresh->forceFill(['used_at' => now()])->save();

            return $response;
        });

        $this->alertIfLow($response);

        return $response;
    }

    private function alertIfLow(FeedbackResponse $response): void
    {
        $response->loadMissing('location.tenant', 'employee', 'ticket');
        $settings = $response->location->tenant->settings();
        $threshold = (int) $settings->get('feedback_alert_threshold', 2);

        if ($threshold < 1 || $response->rating > $threshold) {
            return;
        }

        // Company admins plus managers assigned to (or covering) this location.
        $recipients = User::query()
            ->where('tenant_id', $response->tenant_id)->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Roles::COMPANY_ADMIN, Roles::LOCATION_MANAGER]))
            ->get()
            ->filter(fn (User $u) => $u->hasRole(Roles::COMPANY_ADMIN) || $u->all_locations
                || $u->locations()->whereKey($response->location_id)->exists());

        Notification::send($recipients, new LowFeedbackScore($response, (bool) $settings->get('feedback_alert_email', false)));
    }
}
