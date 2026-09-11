<?php

declare(strict_types=1);

namespace Securyt\Acl\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aplica o conjunto canônico (Discovery do painel) e a matriz efetiva de grants
 * (config acl.roles + overrides do banco) nas tabelas do spatie/laravel-permission.
 * É a única fonte da lógica de sync — usada pelo comando `acl:sync` e pelas
 * páginas do cluster Shield+.
 */
final class Syncer
{
    /**
     * @param  list<string>|null  $panels  painéis a considerar (padrão: todos)
     */
    public function __construct(private readonly ?array $panels = null) {}

    public function run(bool $prune = true): SyncReport
    {
        $guard = Acl::guard();
        $discovery = new Discovery($this->panels);
        $canonical = $discovery->allPermissions();

        $created = $this->createMissingPermissions($canonical, $guard);

        $roleCounts = [];

        foreach (Acl::rolesWithOverrides() as $role => $grants) {
            $permissions = Acl::permissionsForRole((string) $role, $discovery);
            $this->syncRole((string) $role, $permissions, $guard);
            $roleCounts[(string) $role] = count($permissions);
        }

        $pruned = 0;

        if ($prune) {
            $pruned = $this->pruneOrphans($canonical, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new SyncReport(
            canonical: count($canonical),
            created: $created,
            roleCounts: $roleCounts,
            pruned: $pruned,
        );
    }

    /**
     * @param  list<string>  $canonical
     */
    private function createMissingPermissions(array $canonical, string $guard): int
    {
        $existing = Permission::where('guard_name', $guard)->pluck('name')->all();
        $missing = array_values(array_diff($canonical, $existing));

        foreach ($missing as $name) {
            Permission::findOrCreate($name, $guard);
        }

        return count($missing);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncRole(string $roleName, array $permissions, string $guard): void
    {
        $role = Role::findOrCreate($roleName, $guard);
        $role->syncPermissions($permissions);
    }

    /**
     * @param  list<string>  $canonical
     */
    private function pruneOrphans(array $canonical, string $guard): int
    {
        $orphans = Permission::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', $canonical)
            ->get();

        foreach ($orphans as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }

        return $orphans->count();
    }
}