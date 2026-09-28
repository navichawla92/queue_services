<?php

namespace App\Domain\Access\Concerns;

use App\Domain\Access\AuditLogger;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records created / updated / deleted audit entries with before/after values
 * of the changed attributes. Action names: "<model>.created", etc.
 *
 * A model may declare `protected static array $auditIgnore = [...]` for
 * operational columns that should not produce setup audit entries.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditLogger::class)->log(static::auditAction('created'), $model, after: static::auditValues($model, $model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changed = array_values(array_diff(array_keys($model->getChanges()), static::auditExcluded()));
            if ($changed === []) {
                return;
            }

            $before = array_intersect_key($model->getOriginal(), array_flip($changed));
            $after = array_intersect_key($model->getAttributes(), array_flip($changed));

            app(AuditLogger::class)->log(static::auditAction('updated'), $model, static::auditValues($model, $before), static::auditValues($model, $after));
        });

        static::deleted(function (Model $model) {
            app(AuditLogger::class)->log(static::auditAction('deleted'), $model, before: static::auditValues($model, $model->getOriginal()));
        });
    }

    /** @return list<string> */
    protected static function auditExcluded(): array
    {
        $ignore = property_exists(static::class, 'auditIgnore') ? static::$auditIgnore : [];

        return array_merge(['created_at', 'updated_at', 'password', 'remember_token'], $ignore);
    }

    protected static function auditAction(string $event): string
    {
        return Str::snake(class_basename(static::class)).'.'.$event;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function auditValues(Model $model, array $values): array
    {
        $values = array_diff_key($values, array_flip(array_merge(static::auditExcluded(), $model->getHidden())));

        return array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format(DATE_ATOM) : $v, $values);
    }
}
