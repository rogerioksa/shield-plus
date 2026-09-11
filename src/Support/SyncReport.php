<?php

declare(strict_types=1);

namespace Securyt\Acl\Support;

use Spatie\Permission\Models\Permission;

/**
 * Resultado de uma sincronização do ACL.
 */
final class SyncReport
{
    /**
     * @param  int  $canonical  total de permissões do conjunto canônico
     * @param  int  $created  permissões novas criadas
     * @param  array<string, int>  $roleCounts  papel => qtde de permissões aplicadas
     * @param  int  $pruned  permissões órfãs removidas
     */
    public function __construct(
        public readonly int $canonical,
        public readonly int $created,
        public readonly array $roleCounts,
        public readonly int $pruned,
    ) {}
}