<?php

declare(strict_types=1);

namespace Securyt\Acl\Filament;

use Filament\Clusters\Cluster;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Securyt\Acl\Filament\Clusters\ShieldPlusCluster;

/**
 * Registra o cluster Shield+ (páginas de configuração do ACL) em um painel.
 *
 * Uso: no painel Filament, `->plugins([ShieldPlusPlugin::make()])`.
 * O registro é idempotente (clusters já registrados são ignorados).
 */
class ShieldPlusPlugin implements Plugin
{
    public function getId(): string
    {
        return 'shield-plus';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        if (in_array(ShieldPlusCluster::class, $panel->getClusters(), true)) {
            return;
        }

        $panel->discoverClusters(
            in: __DIR__,
            for: 'Securyt\Acl\Filament',
        );
    }

    public function boot(Panel $panel): void {}
}