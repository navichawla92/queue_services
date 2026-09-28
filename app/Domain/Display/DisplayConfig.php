<?php

namespace App\Domain\Display;

use App\Domain\Access\Models\Device;

/**
 * Lobby display settings stored on the device (lobby-display "Screen
 * layouts", panels). Unknown keys are dropped; missing keys use defaults.
 */
final class DisplayConfig
{
    public const LAYOUTS = ['queue', 'split'];

    public const ORIENTATIONS = ['landscape', 'portrait'];

    public const DEFAULTS = [
        'layout' => 'queue',            // queue | split (queue zone + signage zone)
        'orientation' => 'landscape',
        'queue_zone' => 'left',         // split: where the queue zone sits
        'department_ids' => [],         // empty = all departments of the location
        'waiting_rows' => 8,
        'show_employee_name' => false,
        'show_avg_wait' => true,
        'highlight_seconds' => 10,
        'chime' => true,
        'header' => true,
        'ticker' => true,
    ];

    /** @return array<string, mixed> */
    public static function for(Device $device): array
    {
        return self::normalize($device->config ?? []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $c = array_replace(self::DEFAULTS, array_intersect_key($input, self::DEFAULTS));

        return [
            'layout' => in_array($c['layout'], self::LAYOUTS, true) ? $c['layout'] : 'queue',
            'orientation' => in_array($c['orientation'], self::ORIENTATIONS, true) ? $c['orientation'] : 'landscape',
            'queue_zone' => $c['queue_zone'] === 'right' ? 'right' : 'left',
            'department_ids' => array_values(array_map('intval', (array) $c['department_ids'])),
            'waiting_rows' => max(0, min(30, (int) $c['waiting_rows'])),
            'show_employee_name' => (bool) $c['show_employee_name'],
            'show_avg_wait' => (bool) $c['show_avg_wait'],
            'highlight_seconds' => max(3, min(60, (int) $c['highlight_seconds'])),
            'chime' => (bool) $c['chime'],
            'header' => (bool) $c['header'],
            'ticker' => (bool) $c['ticker'],
        ];
    }
}
