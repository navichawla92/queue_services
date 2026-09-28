<?php

namespace App\Domain\Notifications;

/**
 * Restricted {{placeholder}} renderer (design Decision 8): plain string
 * substitution from a fixed whitelist — no Blade, no code execution.
 */
class TemplateRenderer
{
    public const PLACEHOLDERS = [
        'first_name', 'ticket_number', 'service', 'department', 'location', 'desk',
        'wait', 'position', 'appointment_time', 'confirmation_code',
        'status_link', 'manage_link', 'checkin_link', 'feedback_link',
    ];

    /** Sample values for the editor preview. */
    public const SAMPLE = [
        'first_name' => 'Jane', 'ticket_number' => 'A-012', 'service' => 'Account Opening',
        'department' => 'New Accounts', 'location' => 'Main Office', 'desk' => 'Desk 3',
        'wait' => 'about 10–15 min', 'position' => '3', 'appointment_time' => 'Fri Oct 2, 10:00 AM',
        'confirmation_code' => 'K7Q2MX', 'status_link' => 'https://example.com/t/abc',
        'manage_link' => 'https://example.com/a/abc', 'checkin_link' => 'https://example.com/a/abc/checkin',
        'feedback_link' => 'https://example.com/f/abc',
    ];

    /** @param  array<string, string|int|null>  $vars */
    public function render(string $template, array $vars): string
    {
        $out = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $template);

        return trim((string) preg_replace('/[ \t]{2,}/', ' ', (string) $out));
    }

    /** @return list<string> placeholders used in the template that are not allowed */
    public function unknownPlaceholders(string $template): array
    {
        preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/', $template, $m);

        return array_values(array_unique(array_diff($m[1], self::PLACEHOLDERS)));
    }

    /** SMS segments: GSM-7 160/153 chars, otherwise UCS-2 70/67. */
    public function segments(string $text): int
    {
        $gsm = '@£$¥èéùìòÇ'."\n".'Øø'."\r".'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
        $extended = '^{}\\[~]|€';

        $isGsm = true;
        $length = 0;
        foreach (mb_str_split($text) as $char) {
            if (mb_strpos($gsm, $char) !== false) {
                $length++;
            } elseif (mb_strpos($extended, $char) !== false) {
                $length += 2;
            } else {
                $isGsm = false;
                break;
            }
        }

        if (! $isGsm) {
            $length = mb_strlen($text);

            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}
