<?php

namespace App\Livewire\Admin;

use App\Domain\Access\LocationAccess;
use App\Domain\Feedback\Models\FeedbackResponse;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Service;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Feedback review (customer-feedback "Feedback review"): managers filter by
 * employee, department, service, location, rating and dates within their
 * location scope; employees may see their own when the company allows it.
 */
#[Layout('layouts.app')]
class FeedbackReview extends Component
{
    use WithPagination;

    #[Locked]
    public bool $own = false;

    #[Url]
    public ?int $employee = null;

    #[Url]
    public ?int $department = null;

    #[Url]
    public ?int $service = null;

    #[Url]
    public ?int $location = null;

    #[Url]
    public ?int $maxRating = null;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function mount(): void
    {
        $this->own = request()->routeIs('staff.feedback');
        $this->authorizeView();
        if (! $this->own) {
            auth()->user()->unreadNotifications()->where('data->kind', 'low_feedback')->update(['read_at' => now()]);
        }
    }

    public function hydrate(): void
    {
        $this->authorizeView();
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        if ($this->own) {
            $allowed = (bool) app(TenantContext::class)->require()->settings()->get('employees_view_own_feedback', false);
            abort_unless($allowed && $user->employee()->exists(), 403);

            return;
        }
        abort_unless($user->can('feedback.view'), 403);
    }

    /** @return Builder<FeedbackResponse> */
    private function query(): Builder
    {
        $user = auth()->user();
        $access = app(LocationAccess::class);
        $tz = app(TenantContext::class)->require()->settings()->timezone();

        return FeedbackResponse::query()
            ->when($this->own, fn ($q) => $q->where('employee_id', $user->employee?->id))
            ->when(! $this->own && ! $access->coversAllLocations($user), fn ($q) => $q->whereIn('location_id', $access->accessibleLocations($user)->pluck('id')))
            ->when($this->employee && ! $this->own, fn ($q) => $q->where('employee_id', $this->employee))
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->when($this->service, fn ($q) => $q->where('service_id', $this->service))
            ->when($this->location, fn ($q) => $q->where('location_id', $this->location))
            ->when($this->maxRating, fn ($q) => $q->where('rating', '<=', $this->maxRating))
            ->when($this->from, fn ($q) => $q->where('submitted_at', '>=', CarbonImmutable::parse($this->from, $tz)->startOfDay()->utc()))
            ->when($this->to, fn ($q) => $q->where('submitted_at', '<=', CarbonImmutable::parse($this->to, $tz)->endOfDay()->utc()));
    }

    public function render()
    {
        $access = app(LocationAccess::class);

        return view('livewire.admin.feedback-review', [
            'responses' => $this->query()->with('employee', 'location', 'service', 'department', 'ticket')->latest('submitted_at')->paginate(30),
            'average' => round((float) $this->query()->avg('rating'), 2),
            'count' => $this->query()->count(),
            'locations' => $access->accessibleLocations(auth()->user())->get(),
            'employees' => $this->own ? collect() : Employee::query()->orderBy('display_name')->get(),
            'departments' => Department::query()->active()->ordered()->get(),
            'services' => Service::query()->orderBy('name')->get(),
            'tz' => app(TenantContext::class)->require()->settings()->timezone(),
        ]);
    }
}
