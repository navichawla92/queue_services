<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Access\SupportSession;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportSessionController extends Controller
{
    public function __construct(private readonly SupportSession $support) {}

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->support->start($request->session(), $request->user(), $tenant, $data['reason']);

        return redirect()->route('admin.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->support->end($request->session());

        return redirect()->route('platform.home');
    }
}
