<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\SmsTemplate;

/** Event toggles (location overrides company) and template lookup. */
class NotificationPreferences
{
    public function enabled(NotificationEvent $event, ?int $locationId): bool
    {
        $rows = NotificationSetting::query()
            ->where('event', $event->value)
            ->where(fn ($q) => $q->whereNull('location_id')->when($locationId, fn ($q) => $q->orWhere('location_id', $locationId)))
            ->get();

        /** @var NotificationSetting|null $row */
        $row = ($locationId ? $rows->firstWhere('location_id', $locationId) : null) ?? $rows->firstWhere('location_id', null);

        return $row === null ? $event->defaultEnabled() : $row->enabled;
    }

    public function template(NotificationEvent $event, string $locale = 'en'): string
    {
        $templates = SmsTemplate::query()->where('event', $event->value)->whereIn('locale', array_unique([$locale, 'en']))->get();

        /** @var SmsTemplate|null $template */
        $template = $templates->firstWhere('locale', $locale) ?? $templates->firstWhere('locale', 'en');

        return $template === null ? $event->defaultTemplate() : $template->body;
    }
}
