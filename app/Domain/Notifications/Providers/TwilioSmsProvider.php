<?php

namespace App\Domain\Notifications\Providers;

use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;
use Twilio\Http\CurlClient;
use Twilio\Rest\Client;

/** Twilio Programmable Messaging. */
class TwilioSmsProvider implements SmsProvider
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
    ) {}

    public function name(): string
    {
        return 'twilio';
    }

    public function send(SmsSender $sender, string $to, string $body, ?string $statusCallbackUrl = null): SmsResult
    {
        $options = ['body' => $body];
        if ($sender->messagingServiceSid) {
            $options['messagingServiceSid'] = $sender->messagingServiceSid;
        } elseif ($sender->fromNumber) {
            $options['from'] = $sender->fromNumber;
        } else {
            throw new SmsPermanentException('No SMS sender configured.', 'no_sender');
        }
        if ($statusCallbackUrl) {
            $options['statusCallback'] = $statusCallbackUrl;
        }

        try {
            $message = $this->client()->messages->create($to, $options);
        } catch (RestException $e) {
            if ($e->getStatusCode() >= 500 || $e->getStatusCode() === 429) {
                throw new SmsTransientException($e->getMessage(), $e->getCode(), $e);
            }
            throw new SmsPermanentException($e->getMessage(), (string) $e->getCode());
        } catch (TwilioException $e) {
            // Network / timeout errors.
            throw new SmsTransientException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return new SmsResult(
            providerMessageId: (string) $message->sid,
            status: in_array($message->status, ['sent', 'delivered'], true) ? $message->status : 'queued',
            segments: $message->numSegments !== null ? (int) $message->numSegments : null,
            price: $message->price !== null ? ltrim((string) $message->price, '-') : null,
            priceUnit: $message->priceUnit,
        );
    }

    private function client(): Client
    {
        return $this->client ??= new Client($this->accountSid, $this->authToken, null, null, new CurlClient([CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]));
    }
}
