<?php

namespace App\Domain\Notifications\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development driver: writes messages to the application log, sends nothing. */
class LogSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function send(SmsSender $sender, string $to, string $body, ?string $statusCallbackUrl = null): SmsResult
    {
        Log::info('[sms:log] to '.$to, ['from' => $sender->fromNumber ?? $sender->messagingServiceSid, 'body' => $body]);

        return new SmsResult('log-'.Str::uuid(), 'delivered');
    }
}
