<?php

declare(strict_types=1);

namespace Securyt\Acl\Concerns;

use Filament\Facades\Filament;
use Securyt\Acl\Support\Acl;

/**
 * Gate de acesso para Clusters do Filament (v5) baseado na permissão
 * "View:<Cluster>". Em v4/v5 o hook de cluster é canAccessClusteredComponents()
 * (não existe equivalentes grant-trait no plugin original) e controla a
 * visibilidade no menu e o acesso às páginas do cluster.
 */
trait GatesCluster
{
    public static function canAccessClusteredComponents(): bool
    {
        return (bool) (Filament::auth()->user()?->can(Acl::viewPermission(class_basename(static::class))) ?? false);
    }
}
