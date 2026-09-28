<?php

namespace App\Domain\Access\Concerns;

use App\Domain\Access\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records created / updated / deleted audit entries with before/after values
 * of the changed attributes. Action names: "<model>.created", etc.
 *
 * @mixin Model
 */
trait Auditable
{
    /** Attributes never written to the audit log. */
    protected static array $auditExcept = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditLogger::class)->log(static::auditAction('created'), $model, after: static::auditValues($model, $model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changed = array_keys($model->getChanges());
            $changed = array_values(array_diff($changed, static::$auditExcept));
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
        $values = array_diff_key($values, array_flip(array_merge(static::$auditExcept, $model->getHidden())));

        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v, $values);
    }
}
