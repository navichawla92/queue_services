<?php

namespace App\Http\Controllers\PublicSite;

use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsOptOut;
use App\Domain\Notifications\SmsConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Twilio\Security\RequestValidator;

/**
 * Twilio callbacks for one tenant (/webhooks/sms/twilio/{tenant}/…). Every
 * request must carry a valid X-Twilio-Signature for the tenant's auth token;
 * anything else is rejected without touching data.
 */
class TwilioWebhookController extends Controller
{
    private const STOP = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT', 'REVOKE'];

    private const START = ['START', 'YES', 'UNSTOP', 'OPTIN'];

    private const HELP = ['HELP', 'INFO'];

    /** Twilio → provider status for a message we sent. */
    private const STATUS_MAP = [
        'accepted' => SmsMessage::QUEUED, 'queued' => SmsMessage::QUEUED, 'sending' => SmsMessage::SENT,
        'sent' => SmsMessage::SENT, 'delivered' => SmsMessage::DELIVERED, 'read' => SmsMessage::DELIVERED,
        'undelivered' => SmsMessage::UNDELIVERED, 'failed' => SmsMessage::FAILED,
    ];

    public function __construct(private readonly SmsConfig $config) {}

    public function status(Request $request): Response
    {
        $this->verify($request);

        $message = SmsMessage::query()->where('provider_message_id', (string) $request->input('MessageSid'))->first();
        $status = self::STATUS_MAP[strtolower((string) $request->input('MessageStatus'))] ?? null;

        // Never move backwards (callbacks can arrive out of order).
        if ($message && $status && $this->rank($status) >= $this->rank($message->status)) {
            $message->forceFill([
                'status' => $status,
                'error_code' => $request->input('ErrorCode') ?: $message->error_code,
                'delivered_at' => $status === SmsMessage::DELIVERED ? now() : $message->delivered_at,
                'price' => $request->input('Price') !== null ? ltrim((string) $request->input('Price'), '-') : $message->price,
            ])->save();
        }

        return response('', 204);
    }

    public function inbound(Request $request): Response
    {
        $this->verify($request);

        $from = (string) $request->input('From');
        $keyword = strtoupper(trim(strtok((string) $request->input('Body'), " \n") ?: ''));
        $reply = null;

        if (in_array($keyword, self::STOP, true)) {
            SmsOptOut::query()->updateOrCreate(['phone' => $from], ['source' => 'keyword', 'opted_out_at' => now()]);
        } elseif (in_array($keyword, self::START, true)) {
            SmsOptOut::query()->where('phone', $from)->delete();
        } elseif (in_array($keyword, self::HELP, true)) {
            $reply = $this->config->settings()->help_message
                ?: __('This number sends queue updates. Reply STOP to opt out.');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .($reply ? '<Message>'.htmlspecialchars($reply, ENT_XML1).'</Message>' : '')
            .'</Response>';

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    private function verify(Request $request): void
    {
        $token = $this->config->settings()->auth_token;
        $signature = (string) $request->header('X-Twilio-Signature');

        $valid = $token && $signature !== ''
            && (new RequestValidator($token))->validate($signature, $request->fullUrl(), $request->post());

        abort_unless($valid, 403);
    }

    private function rank(string $status): int
    {
        return match ($status) {
            SmsMessage::QUEUED => 0,
            SmsMessage::SENT => 1,
            SmsMessage::DELIVERED, SmsMessage::UNDELIVERED, SmsMessage::FAILED => 2,
            default => -1,
        };
    }
}
