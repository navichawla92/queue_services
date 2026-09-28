<?php

namespace App\Livewire\Admin;

use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\SmsSetting;
use App\Domain\Notifications\Models\SmsTemplate;
use App\Domain\Notifications\NotificationEvent;
use App\Domain\Notifications\NotificationPreferences;
use App\Domain\Notifications\TemplateRenderer;
use App\Domain\Organization\Models\Location;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** SMS provider, event toggles and templates (permission: notifications.manage). */
#[Layout('layouts.app')]
class SmsSettings extends Component
{
    // Provider & sender (company level)
    public string $provider = 'log';

    public ?string $account_sid = null;

    public ?string $auth_token = null; // write-only: blank keeps the stored token

    public ?string $from_number = null;

    public ?string $messaging_service_sid = null;

    public ?string $help_message = null;

    public string $quiet_start = '21:00';

    public string $quiet_end = '08:00';

    public int $wait_update_threshold = 10;

    public int $position_alert_at = 3;

    public bool $hasToken = false;

    /** @var array<string, bool> company toggles */
    public array $enabled = [];

    /** @var array<int|string, array<string, string>> location id → event → '' (inherit) | '1' | '0' */
    public array $overrides = [];

    /** @var array<int|string, string> location id → sender number override */
    public array $locationSenders = [];

    // Template editor
    public string $templateEvent = 'checkin_confirmation';

    public string $templateLocale = 'en';

    public string $templateBody = '';

    public ?string $saved = null;

    public function mount(NotificationPreferences $preferences): void
    {
        Gate::authorize('notifications.manage');

        $s = SmsSetting::query()->whereNull('location_id')->first() ?? new SmsSetting;
        $this->fill([
            'provider' => $s->provider, 'account_sid' => $s->account_sid, 'from_number' => $s->from_number,
            'messaging_service_sid' => $s->messaging_service_sid, 'help_message' => $s->help_message,
            'quiet_start' => substr($s->quiet_start, 0, 5), 'quiet_end' => substr($s->quiet_end, 0, 5),
            'wait_update_threshold' => $s->wait_update_threshold, 'position_alert_at' => $s->position_alert_at,
        ]);
        $this->hasToken = (bool) $s->auth_token;

        foreach (NotificationEvent::cases() as $event) {
            $this->enabled[$event->value] = $preferences->enabled($event, null);
        }
        foreach (NotificationSetting::query()->whereNotNull('location_id')->get() as $row) {
            $this->overrides[$row->location_id][$row->event] = $row->enabled ? '1' : '0';
        }
        foreach (SmsSetting::query()->whereNotNull('location_id')->get() as $row) {
            $this->locationSenders[$row->location_id] = (string) $row->from_number;
        }

        $this->loadTemplate($preferences);
    }

    public function saveProvider(): void
    {
        Gate::authorize('notifications.manage');
        $data = $this->validate([
            'provider' => ['required', Rule::in(['log', 'twilio'])],
            'account_sid' => ['nullable', 'required_if:provider,twilio', 'string', 'max:64'],
            'auth_token' => ['nullable', 'string', 'max:128'],
            'from_number' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
            'messaging_service_sid' => ['nullable', 'string', 'max:64'],
            'help_message' => ['nullable', 'string', 'max:320'],
            'quiet_start' => ['required', 'date_format:H:i'],
            'quiet_end' => ['required', 'date_format:H:i'],
            'wait_update_threshold' => ['required', 'integer', 'between:1,120'],
            'position_alert_at' => ['required', 'integer', 'between:2,20'],
            'locationSenders.*' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
        ], ['from_number.regex' => __('Use E.164 format, e.g. +12025550100.')]);

        if ($data['provider'] === 'twilio' && ! $data['from_number'] && ! $data['messaging_service_sid']) {
            $this->addError('from_number', __('Enter a sender number or a messaging service SID.'));

            return;
        }

        $settings = SmsSetting::query()->whereNull('location_id')->first() ?? new SmsSetting;
        $token = $data['auth_token'];
        unset($data['auth_token'], $data['locationSenders']);
        $settings->fill($data);
        if ($token) {
            $settings->auth_token = $token;
        }
        $settings->save();
        $this->hasToken = (bool) $settings->auth_token;
        $this->auth_token = null;

        $locationIds = Location::query()->pluck('id')->all();
        foreach ($this->locationSenders as $locationId => $number) {
            if (! in_array((int) $locationId, $locationIds, true)) {
                continue;
            }
            $row = SmsSetting::query()->firstOrNew(['location_id' => (int) $locationId]);
            $row->from_number = $number ?: null;
            $row->exists || $row->from_number ? $row->save() : null;
        }

        $this->saved = __('SMS settings saved.');
    }

    public function saveToggles(): void
    {
        Gate::authorize('notifications.manage');
        $events = array_column(NotificationEvent::cases(), 'value');
        $locationIds = Location::query()->pluck('id')->all();

        foreach ($this->enabled as $event => $on) {
            if (in_array($event, $events, true)) {
                NotificationSetting::query()->updateOrCreate(['location_id' => null, 'event' => $event], ['enabled' => (bool) $on]);
            }
        }
        foreach ($this->overrides as $locationId => $byEvent) {
            if (! in_array((int) $locationId, $locationIds, true)) {
                continue;
            }
            foreach ($byEvent as $event => $value) {
                if (! in_array($event, $events, true)) {
                    continue;
                }
                $value === ''
                    ? NotificationSetting::query()->where(['location_id' => (int) $locationId, 'event' => $event])->get()->each->delete()
                    : NotificationSetting::query()->updateOrCreate(['location_id' => (int) $locationId, 'event' => $event], ['enabled' => $value === '1']);
            }
        }

        $this->saved = __('Notification toggles saved.');
    }

    public function updatedTemplateEvent(NotificationPreferences $preferences): void
    {
        $this->loadTemplate($preferences);
    }

    public function updatedTemplateLocale(NotificationPreferences $preferences): void
    {
        $this->loadTemplate($preferences);
    }

    public function saveTemplate(TemplateRenderer $renderer): void
    {
        Gate::authorize('notifications.manage');
        $this->validate([
            'templateEvent' => ['required', Rule::in(array_column(NotificationEvent::cases(), 'value'))],
            'templateLocale' => ['required', Rule::in(['en', 'es'])],
            'templateBody' => ['required', 'string', 'max:1000'],
        ]);

        $unknown = $renderer->unknownPlaceholders($this->templateBody);
        if ($unknown !== []) {
            $this->addError('templateBody', __('Unknown placeholder(s): :bad. Valid: :valid', [
                'bad' => implode(', ', $unknown),
                'valid' => implode(', ', array_map(fn ($p) => '{{'.$p.'}}', TemplateRenderer::PLACEHOLDERS)),
            ]));

            return;
        }

        SmsTemplate::query()->updateOrCreate(['event' => $this->templateEvent, 'locale' => $this->templateLocale], ['body' => $this->templateBody]);
        $this->saved = __('Template saved.');
    }

    public function resetTemplate(NotificationPreferences $preferences): void
    {
        Gate::authorize('notifications.manage');
        SmsTemplate::query()->where(['event' => $this->templateEvent, 'locale' => $this->templateLocale])->get()->each->delete();
        $this->loadTemplate($preferences);
    }

    private function loadTemplate(NotificationPreferences $preferences): void
    {
        $event = NotificationEvent::tryFrom($this->templateEvent) ?? NotificationEvent::CheckinConfirmation;
        $this->templateBody = $preferences->template($event, $this->templateLocale);
    }

    public function render(TemplateRenderer $renderer)
    {
        $preview = $renderer->render($this->templateBody, TemplateRenderer::SAMPLE);

        return view('livewire.admin.sms-settings', [
            'events' => NotificationEvent::cases(),
            'locations' => Location::query()->orderBy('name')->get(),
            'preview' => $preview,
            'chars' => mb_strlen($preview),
            'segments' => $renderer->segments($preview),
            'placeholders' => array_map(fn (string $p) => '{{'.$p.'}}', TemplateRenderer::PLACEHOLDERS),
        ]);
    }
}
