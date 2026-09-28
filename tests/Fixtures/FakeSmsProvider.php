<?php

namespace Tests\Fixtures;

use App\Domain\Notifications\Providers\SmsPermanentException;
use App\Domain\Notifications\Providers\SmsProvider;
use App\Domain\Notifications\Providers\SmsResult;
use App\Domain\Notifications\Providers\SmsSender;
use App\Domain\Notifications\Providers\SmsTransientException;

/** Records sends; can be told to fail. */
class FakeSmsProvider implements SmsProvider
{
    /** @var list<array{to: string, body: string, from: string|null}> */
    public array $sent = [];

    public ?string $failWith = null; // 'transient' | 'permanent'

    public function name(): string
    {
        return 'fake';
    }

    public function send(SmsSender $sender, string $to, string $body, ?string $statusCallbackUrl = null): SmsResult
    {
        if ($this->failWith === 'transient') {
            throw new SmsTransientException('503 from provider');
        }
        if ($this->failWith === 'permanent') {
            throw new SmsPermanentException('Invalid number', '21211');
        }

        $this->sent[] = ['to' => $to, 'body' => $body, 'from' => $sender->fromNumber];

        return new SmsResult('SM'.count($this->sent).bin2hex(random_bytes(4)), 'queued', 1);
    }
}
