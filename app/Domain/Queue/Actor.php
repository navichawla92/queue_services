<?php

namespace App\Domain\Queue;

use App\Domain\Access\Models\Device;
use App\Domain\Organization\Models\Employee;
use App\Models\User;

/** Who performed a queue action (recorded on every ticket event). */
final class Actor
{
    private function __construct(
        public readonly string $type,
        public readonly ?int $id = null,
        public readonly ?string $name = null,
    ) {}

    public static function user(User $user): self
    {
        return new self('user', $user->id, $user->name);
    }

    public static function employee(Employee $employee): self
    {
        return new self('user', $employee->user_id, $employee->display_name);
    }

    public static function device(Device $device): self
    {
        return new self('device', $device->id, $device->name);
    }

    public static function customer(): self
    {
        return new self('customer');
    }

    public static function system(): self
    {
        return new self('system');
    }

    /** The signed-in user, else system. */
    public static function current(): self
    {
        $user = auth()->user();

        return $user instanceof User ? self::user($user) : self::system();
    }
}
