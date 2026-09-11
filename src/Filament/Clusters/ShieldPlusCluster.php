<?php

declare(strict_types=1);

namespace Securyt\Acl\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Securyt\Acl\Concerns\GatesCluster;
use UnitEnum;

/**
 * Cluster de configuração do Shield+ no painel Filament.
 *
 * Acessível apenas para quem possui a permissão canônica "View:ShieldPlusCluster"
 * (derivada automaticamente pelo Discovery → provisionada por acl:sync).
 */
class ShieldPlusCluster extends Cluster
{
    use GatesCluster;

    protected static ?string $slug = 'shield-plus';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static ?string $navigationLabel = 'Shield+';

    protected static string|UnitEnum|null $navigationGroup = 'Configuração';

    protected static ?int $navigationSort = 10;

    protected static ?string $clusterBreadcrumb = 'Shield+';
}