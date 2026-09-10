<?php

declare(strict_types=1);

namespace Securyt\Acl\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Securyt\Acl\Support\Acl;

/**
 * Escopo de registro por papel (ADR-005 / glossário): papéis restritos
 * (acl.restricted_roles) enxergam somente os próprios registros; demais papéis
 * (e visitantes anônimos) veem todos. A coluna de responsável é configurável
 * por modelo (acl.owner_columns ou owner_column_default).
 */
trait ScopesVisibleTo
{
    public function scopeVisibleTo(Builder $query, ?Model $user): Builder
    {
        if (! $user instanceof Model) {
            return $query;
        }

        if (! $this->isRestrictedOperator($user)) {
            return $query;
        }

        return $query->where($this->visibleToOwnerColumn(), $user->getKey());
    }

    protected function visibleToOwnerColumn(): string
    {
        return (string) (Acl::config('owner_columns.'.static::class) ?? Acl::config('owner_column_default', 'corretor_id'));
    }

    protected function isRestrictedOperator(Model $user): bool
    {
        if (method_exists($user, 'isRestrictedOperator')) {
            return (bool) $user->isRestrictedOperator();
        }

        return (bool) $user->hasAnyRole((array) Acl::config('restricted_roles', []));
    }
}
