<?php

namespace App\Domain\Access;

use App\Domain\Access\Models\Device;
use App\Domain\Access\Models\DevicePairing;
use App\Domain\Billing\LimitGuard;
use App\Domain\Display\DisplayChanged;
use App\Domain\Display\DisplayNotifier;
use App\Domain\Organization\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pairing flow (access-control "Device credentials", lobby-display "Display
 * pairing"):
 *  1. unpaired browser -> request(): gets a short code + private claim secret
 *  2. admin -> claim(code, location, name): creates the Device and its token
 *  3. browser -> collect(code, claim): receives the token exactly once
 */
class DevicePairingService
{
    /** No 0/O, 1/I/L: codes are read off a TV across a room. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 6;

    public const TTL_MINUTES = 15;

    /** @return array{code: string, claim: string, expires_at: string} */
    public function request(string $type): array
    {
        abort_unless(in_array($type, Device::TYPES, true), 422);

        DevicePairing::query()->where('expires_at', '<', now()->subDay())->delete();

        $claim = Str::random(40);
        $pairing = null;

        for ($attempt = 0; $attempt < 5 && $pairing === null; $attempt++) {
            $code = $this->generateCode();
            if (DevicePairing::query()->where('code', $code)->exists()) {
                continue;
            }
            $pairing = DevicePairing::create([
                'code' => $code,
                'type' => $type,
                'claim_hash' => hash('sha256', $claim),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        }

        abort_if($pairing === null, 503);

        return ['code' => $pairing->code, 'claim' => $claim, 'expires_at' => $pairing->expires_at->toIso8601String()];
    }

    /** Admin side: bind a pending code to a new device in the current tenant. */
    public function claim(string $code, Location $location, string $name): Device
    {
        return DB::transaction(function () use ($code, $location, $name) {
            $pairing = DevicePairing::query()
                ->where('code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            if ($pairing === null || $pairing->isExpired() || $pairing->device_id !== null) {
                throw ValidationException::withMessages(['code' => __('This pairing code is invalid or has expired.')]);
            }

            app(LimitGuard::class)->assertCanAdd('displays', 'code');
            $token = Str::random(64);

            $device = new Device(['location_id' => $location->id, 'type' => $pairing->type, 'name' => $name]);
            $device->forceFill(['token_hash' => Device::hashToken($token), 'channel_key' => Str::random(40), 'paired_at' => now()])->save();

            $pairing->forceFill(['device_id' => $device->id, 'token_encrypted' => $token])->save();

            return $device;
        });
    }

    /**
     * Device side: poll for the result. Returns the token once; afterwards
     * the hand-off copy is wiped.
     *
     * @return array{status: 'pending'|'paired'|'expired'|'invalid', token?: string}
     */
    public function collect(string $code, string $claim): array
    {
        return DB::transaction(function () use ($code, $claim) {
            $pairing = DevicePairing::query()->where('code', strtoupper($code))->lockForUpdate()->first();

            if ($pairing === null || ! hash_equals($pairing->claim_hash, hash('sha256', $claim))) {
                return ['status' => 'invalid'];
            }

            if ($pairing->device_id === null) {
                return ['status' => $pairing->isExpired() ? 'expired' : 'pending'];
            }

            $token = $pairing->token_encrypted;
            if ($token === null) {
                return ['status' => 'invalid']; // already collected
            }

            $pairing->forceFill(['token_encrypted' => null])->save();

            return ['status' => 'paired', 'token' => $token];
        });
    }

    public function revoke(Device $device): void
    {
        // Tell the screen first (on its current channel), then kill token and channel.
        app(DisplayNotifier::class)->device($device, DisplayChanged::REVOKED);
        $device->forceFill(['revoked_at' => now(), 'token_hash' => null, 'channel_key' => null])->save();
    }

    private function generateCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
