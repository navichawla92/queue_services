<?php

namespace App\Livewire\PublicSite;

use App\Domain\Feedback\FeedbackSubmission;
use App\Domain\Feedback\Models\FeedbackRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/** /f/{token}: branded 1–5 rating + up to 3 extra questions + comment (single use). */
#[Layout('layouts.public')]
class FeedbackForm extends Component
{
    public const MAX_EXTRA_QUESTIONS = 3;

    #[Locked]
    public int $requestId;

    public ?int $rating = null;

    /** @var array<int|string, int|string|null> */
    public array $extra = [];

    public string $comment = '';

    public bool $done = false;

    public function mount(string $feedback): void
    {
        $this->requestId = FeedbackRequest::query()->where('token', $feedback)->firstOrFail()->id;
    }

    public function submit(FeedbackSubmission $submission): void
    {
        $questions = $this->questions();
        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'extra.*' => ['nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['rating.required' => __('Please choose a rating.')]);

        $answers = [];
        foreach ($questions as $i => $question) {
            if (! empty($this->extra[$i])) {
                $answers[] = ['question' => $question, 'rating' => (int) $this->extra[$i]];
            }
        }

        try {
            $submission->submit($this->request(), (int) $this->rating, $answers, trim($this->comment) ?: null);
        } catch (RuntimeException) {
            // Used or expired meanwhile: the render shows the right message.
        }
        $this->done = true;
    }

    private function request(): FeedbackRequest
    {
        return FeedbackRequest::query()->with('location.tenant', 'ticket.service')->findOrFail($this->requestId);
    }

    /** @return list<string> */
    private function questions(): array
    {
        $q = (array) $this->request()->location->tenant->settings()->get('feedback_questions', []);

        return array_slice(array_values(array_filter($q, 'is_string')), 0, self::MAX_EXTRA_QUESTIONS);
    }

    public function render()
    {
        $request = $this->request();

        return view('livewire.public-site.feedback-form', [
            'request' => $request,
            'state' => match (true) {
                $this->done || $request->used_at !== null => 'thanks',
                $request->expires_at->isPast() => 'expired',
                default => 'form',
            },
            'alreadyUsed' => ! $this->done && $request->used_at !== null,
            'questions' => $this->questions(),
        ])->title(__('How did we do?'));
    }
}
