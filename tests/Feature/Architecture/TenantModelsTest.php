<?php

namespace Tests\Feature\Architecture;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/**
 * CI guard (tasks 2.5): every Eloquent model whose table has a tenant_id
 * column must use BelongsToTenant, unless explicitly allow-listed here with
 * a reason.
 */
class TenantModelsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<class-string, string> */
    private const ALLOWED_WITHOUT_SCOPE = [
        User::class => 'Sign-in resolves users before a tenant is known; guarded by middleware and policies.',
        AuditLog::class => 'Platform-level entries have no tenant; reads use TenantScope directly.',
    ];

    public function test_every_tenant_owned_model_uses_belongs_to_tenant(): void
    {
        $offenders = [];
        $checked = 0;

        foreach ($this->modelClasses() as $class) {
            /** @var Model $model */
            $model = new $class;

            if (! Schema::hasTable($model->getTable()) || ! Schema::hasColumn($model->getTable(), 'tenant_id')) {
                continue;
            }

            $checked++;
            if (isset(self::ALLOWED_WITHOUT_SCOPE[$class])) {
                continue;
            }

            if (! in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $offenders[] = $class;
            }
        }

        $this->assertSame([], $offenders, 'Models with tenant_id must use BelongsToTenant: '.implode(', ', $offenders));
        $this->assertGreaterThan(0, $checked);
    }

    /** @return list<class-string<Model>> */
    private function modelClasses(): array
    {
        $classes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = Str::after($file->getPathname(), app_path().DIRECTORY_SEPARATOR);
            $class = 'App\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relative);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
