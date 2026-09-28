<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Arr;

/**
 * Resolves tenant settings with defaults; a location may override a subset
 * (currently the time zone). Pass the location via forLocation().
 */
class TenantSettings
{
    public const DEFAULTS = [
        'timezone' => 'America/New_York',
        'locale' => 'en',
        'ticket_number_format' => '{prefix}-{seq:3}',
        'retention_days' => 730,
        'show_customer_names_on_display' => false,
        'transfer_keeps_queue_position' => true,
        'notifications' => [],
    ];

    /** Keys a location is allowed to override. */
    public const LOCATION_OVERRIDABLE = ['timezone'];

    private ?object $location = null;

    public function __construct(private readonly Tenant $tenant) {}

    /** @param  object|null  $location  any model exposing the overridable keys as attributes */
    public function forLocation(?object $location): static
    {
        $clone = clone $this;
        $clone->location = $location;

        return $clone;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->location !== null && in_array($key, self::LOCATION_OVERRIDABLE, true)) {
            $override = $this->location->{$key} ?? null;
            if ($override !== null && $override !== '') {
                return $override;
            }
        }

        $value = Arr::get($this->tenant->settings ?? [], $key);

        return $value ?? Arr::get(self::DEFAULTS, $key, $default);
    }

    public function timezone(): string
    {
        return (string) $this->get('timezone');
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_replace_recursive(self::DEFAULTS, $this->tenant->settings ?? []);
    }
}
