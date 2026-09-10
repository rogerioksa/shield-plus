<?php

declare(strict_types=1);

namespace Securyt\Acl\Concerns;

use Securyt\Acl\Support\Acl;

/**
 * Papéis derivados da config acl.restricted_roles / acl.supervision_roles.
 * Requer Spatie Permission (trait HasRoles) no modelo.
 */
trait HasAclRoles
{
    /**
     * Operador "logado ao próprio registro": papéis restritos só veem/editem
     * os próprios registros (ScopesVisibleTo + RestrictsOwnRecords).
     */
    public function isRestrictedOperator(): bool
    {
        return (bool) $this->hasAnyRole((array) Acl::config('restricted_roles', []));
    }

    /**
     * Papéis de supervisão/gerência enxergam todos os registros.
     */
    public function isSupervisor(): bool
    {
        return (bool) $this->hasAnyRole((array) Acl::config('supervision_roles', []));
    }
}
