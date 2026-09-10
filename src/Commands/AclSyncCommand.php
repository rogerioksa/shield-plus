<?php

declare(strict_types=1);

namespace Securyt\Acl\Commands;

use Illuminate\Console\Command;
use Securyt\Acl\Support\Acl;
use Securyt\Acl\Support\Discovery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AclSyncCommand extends Command
{
    protected $signature = 'acl:sync
        {--panel=* : Painéis a considerar (padrão: todos)}
        {--no-prune : Mantém permissões fora do conjunto canônico}';

    protected $description = 'Sincroniza permissões e grants de papéis a partir do conjunto canônico (discovery do painel + config acl.roles).';

    public function handle(): int
    {
        $guard = Acl::guard();
        $discovery = new Discovery($this->option('panel') ?: null);
        $canonical = $discovery->allPermissions();

        $this->components->task('Descobrindo conjunto canônico ('.count($canonical).' permissões)', static fn () => true);

        $this->createMissingPermissions($canonical, $guard);

        $this->components->info('Grants por papel:');

        foreach (Acl::roles() as $roleName => $grants) {
            $permissions = Acl::permissionsForRole((string) $roleName, $discovery);
            $this->syncRole((string) $roleName, $permissions, $guard);
            $this->line('  '.str_pad((string) $roleName, 14).number_format(count($permissions)));
        }

        if (! $this->option('no-prune')) {
            $this->pruneOrphans($canonical, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->components->info('ACL sincronizado ('.count(Acl::roles()).' papéis, '.count($canonical).' permissões canônicas).');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $canonical
     */
    private function createMissingPermissions(array $canonical, string $guard): void
    {
        $existing = Permission::where('guard_name', $guard)->pluck('name')->all();
        $missing = array_values(array_diff($canonical, $existing));

        if ($missing === []) {
            return;
        }

        $this->components->task('Criando permissões novas ('.count($missing).')', function () use ($missing, $guard): void {
            foreach ($missing as $name) {
                Permission::findOrCreate($name, $guard);
            }
        });
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
    private function pruneOrphans(array $canonical, string $guard): void
    {
        $orphans = Permission::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', $canonical)
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        $count = $orphans->count();

        $this->components->task('Removendo permissões órfãs ('.$count.')', function () use ($orphans): void {
            foreach ($orphans as $permission) {
                $permission->roles()->detach();
                $permission->delete();
            }
        });
    }
}
