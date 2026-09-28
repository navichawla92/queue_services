<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AuditLogger;
use App\Domain\Analytics\Kpis;
use App\Domain\Analytics\ReportFilter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of any report (analytics-reporting "Export"): respects the
 * filters and the user's location scope; every export is audited.
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, Kpis $kpis, AuditLogger $audit): StreamedResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:summary,trend,employee,department,service,location'],
            'grain' => ['nullable', 'in:day,week,month'],
            'from' => ['required', 'date'], 'to' => ['required', 'date'],
            'location' => ['nullable', 'integer'], 'department' => ['nullable', 'integer'],
            'service' => ['nullable', 'integer'], 'employee' => ['nullable', 'integer'],
        ]);

        $filter = ReportFilter::for($request->user(), $data['from'], $data['to'],
            $data['location'] ?? null, $data['department'] ?? null, $data['service'] ?? null, $data['employee'] ?? null);

        [$header, $rows] = $this->table($data['type'], $data['grain'] ?? 'day', $filter, $kpis);

        $audit->log('report.exported', meta: ['type' => $data['type'], 'filter' => $filter->toArray(), 'rows' => count($rows)]);

        $name = sprintf('%s_%s_%s.csv', $data['type'], $filter->from->toDateString(), $filter->to->toDateString());

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row)); // no formula injection
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function table(string $type, string $grain, ReportFilter $filter, Kpis $kpis): array
    {
        if ($type === 'summary') {
            $s = $kpis->summary($filter);

            return [['metric', 'value'], array_map(fn ($k, $v) => [$k, $v], array_keys($s), $s)];
        }

        if ($type === 'trend') {
            return [['period', 'check_ins', 'served', 'avg_wait_min', 'avg_service_min'],
                array_map(fn ($r) => array_values($r), $kpis->trend($filter, $grain))];
        }

        $names = match ($type) {
            'employee' => Employee::query()->pluck('display_name', 'id'),
            'department' => Department::query()->pluck('name', 'id'),
            'service' => Service::query()->pluck('name', 'id'),
            default => Location::query()->pluck('name', 'id'),
        };
        $satisfaction = $kpis->feedbackBy($filter, $type);

        return [
            [$type, 'check_ins', 'served', 'avg_wait_min', 'avg_service_min', 'no_show_rate_pct', 'satisfaction'],
            array_map(fn ($r) => [
                $names[$r['id']] ?? $r['id'], $r['tickets'], $r['served'], $r['avg_wait_min'], $r['avg_service_min'],
                $r['no_show_rate'], $satisfaction[$r['id']]['avg'] ?? null,
            ], $kpis->breakdown($filter, $type)),
        ];
    }
}
