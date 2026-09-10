<?php

declare(strict_types=1);

namespace Securyt\Acl;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Securyt\Acl\Commands\AclPolicyCommand;
use Securyt\Acl\Commands\AclSyncCommand;
use Securyt\Acl\Policies\RolePolicy;
use Spatie\Permission\Models\Role;

class AclServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/acl.php', 'acl');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/acl.php' => $this->app->configPath('acl.php'),
            ], 'acl-config');

            $this->commands([
                AclSyncCommand::class,
                AclPolicyCommand::class,
            ]);
        }

        Gate::policy(Role::class, RolePolicy::class);
    }
}
