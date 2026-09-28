<?php

namespace App\Livewire\Admin;

use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\NotificationEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** SMS message log with delivery status (permission: notifications.manage). */
#[Layout('layouts.app')]
class SmsLog extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $event = '';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function mount(): void
    {
        Gate::authorize('notifications.manage');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $messages = SmsMessage::query()->with('location', 'ticket')
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->event !== '', fn ($q) => $q->where('event', $this->event))
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', Carbon::parse($this->from)->startOfDay()))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', Carbon::parse($this->to)->endOfDay()))
            ->latest('id')->paginate(50);

        return view('livewire.admin.sms-log', [
            'messages' => $messages,
            'statuses' => SmsMessage::STATUSES,
            'events' => NotificationEvent::cases(),
        ]);
    }
}
