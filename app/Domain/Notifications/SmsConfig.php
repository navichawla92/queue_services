<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Models\SmsSetting;
use App\Domain\Notifications\Providers\LogSmsProvider;
use App\Domain\Notifications\Providers\SmsProvider;
use App\Domain\Notifications\Providers\SmsSender;
use App\Domain\Notifications\Providers\TwilioSmsProvider;

/**
 * Resolves the current tenant's SMS provider, sender (location override)
 * and options. Outside production the log driver is forced unless
 * SMS_ALLOW_REAL_SENDS=true, so development never texts real customers.
 */
class SmsConfig
{
    public function settings(?int $locationId = null): SmsSetting
    {
        $company = SmsSetting::query()->whereNull('location_id')->first() ?? new SmsSetting;

        if ($locationId === null) {
            return $company;
        }

        $local = SmsSetting::query()->where('location_id', $locationId)->first();
        if ($local && ($local->from_number || $local->messaging_service_sid)) {
            $merged = clone $company;
            $merged->from_number = $local->from_number;
            $merged->messaging_service_sid = $local->messaging_service_sid;

            return $merged;
        }

        return $company;
    }

    public function provider(SmsSetting $settings): SmsProvider
    {
        $realAllowed = app()->isProduction() || (bool) config('services.sms.allow_real_sends');

        if ($settings->provider === 'twilio' && $realAllowed && $settings->account_sid && $settings->auth_token) {
            return new TwilioSmsProvider($settings->account_sid, $settings->auth_token);
        }

        return new LogSmsProvider;
    }

    public function sender(SmsSetting $settings): SmsSender
    {
        return new SmsSender($settings->from_number, $settings->messaging_service_sid);
    }
}
