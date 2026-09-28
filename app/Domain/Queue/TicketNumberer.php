<?php

namespace App\Domain\Queue;

use App\Domain\Organization\Models\Department;
use Illuminate\Support\Facades\DB;

/**
 * Daily per-location, per-department ticket numbers ("A-001" …). Must run
 * inside QueueLock so the sequence row increment is serialized.
 */
class TicketNumberer
{
    /** @return array{sequence: int, number: string} */
    public function next(Department $department, string $localDate, string $format): array
    {
        $key = ['location_id' => $department->location_id, 'department_id' => $department->id, 'local_date' => $localDate];

        DB::table('ticket_sequences')->insertOrIgnore($key + ['last_number' => 0]);
        DB::table('ticket_sequences')->where($key)->lockForUpdate()->first();
        DB::table('ticket_sequences')->where($key)->increment('last_number');
        $sequence = (int) DB::table('ticket_sequences')->where($key)->value('last_number');

        return ['sequence' => $sequence, 'number' => $this->format($format, $department->prefix, $sequence)];
    }

    /** Format tokens: {prefix}, {seq} or {seq:N} (zero-padded to N). */
    public function format(string $format, string $prefix, int $sequence): string
    {
        return (string) preg_replace_callback('/\{(prefix|seq)(?::(\d+))?\}/', function ($m) use ($prefix, $sequence) {
            return $m[1] === 'prefix'
                ? $prefix
                : str_pad((string) $sequence, isset($m[2]) ? (int) $m[2] : 1, '0', STR_PAD_LEFT);
        }, $format);
    }
}
