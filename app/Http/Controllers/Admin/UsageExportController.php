<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AuditLogger;
use App\Domain\Billing\Usage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Per-period usage summary as CSV (for future invoicing). */
class UsageExportController extends Controller
{
    public function __invoke(Request $request, Usage $usage, AuditLogger $audit): StreamedResponse
    {
        $period = $request->validate(['period' => ['nullable', 'regex:/^\d{4}-\d{2}$/']])['period'] ?? $usage->period();
        $rows = $usage->report($period);
        $audit->log('usage.exported', meta: ['period' => $period]);

        return response()->streamDownload(function () use ($rows, $period) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['period', 'metric', 'used', 'limit', 'percent']);
            foreach ($rows as $r) {
                fputcsv($out, [$period, $r['metric'], $r['used'], $r['limit'], $r['percent']]);
            }
            fclose($out);
        }, "usage_{$period}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
