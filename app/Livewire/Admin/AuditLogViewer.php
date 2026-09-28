<?php

namespace App\Livewire\Admin;

use App\Domain\Access\Models\AuditLog;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only audit trail for company admins (permission: audit.view). */
#[Layout('layouts.app')]
class AuditLogViewer extends Component
{
    use WithPagination;

    #[Url]
    public string $action = '';

    #[Url]
    public string $actor = '';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $entries = AuditLog::query()
            ->when($this->action !== '', fn ($q) => $q->where('action', 'like', $this->action.'%'))
            ->when($this->actor !== '', fn ($q) => $q->where('actor_name', 'like', '%'.$this->actor.'%'))
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', Carbon::parse($this->from)->startOfDay()))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', Carbon::parse($this->to)->endOfDay()))
            ->latest('id')
            ->paginate(50);

        return view('livewire.admin.audit-log-viewer', ['entries' => $entries]);
    }
}
