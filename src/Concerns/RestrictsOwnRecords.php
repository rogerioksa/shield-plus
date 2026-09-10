<?php

declare(strict_types=1);

namespace Securyt\Acl\Concerns;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;
use Securyt\Acl\Support\Acl;

/**
 * Filtro de ownership por dados (ADR-005): papéis restritos (config
 * acl.restricted_roles) só acessam registros cujo responsável são eles
 * próprios. A coluna de responsável é configurável por modelo
 * (acl.owner_columns ou owner_column_default).
 */
trait RestrictsOwnRecords
{
    use HandlesAuthorization;

    /**
     * Checa a habilidade (permissão) e, para papéis restritos, o ownership.
     */
    protected function canOwn(AuthUser $user, Model $record, string $ability, ?string $ownerColumn = null): bool
    {
        if (! $user->can($ability)) {
            return false;
        }

        if (! $this->isRestrictedOperator($user)) {
            return true;
        }

        $ownerColumn ??= $this->ownerColumnFor($record);
        $ownerValue = $record->getAttribute($ownerColumn);

        return $ownerValue !== null && (int) $ownerValue === (int) $user->getKey();
    }

    protected function ownerColumnFor(Model $record): string
    {
        return (string) (Acl::config('owner_columns.'.$record::class) ?? Acl::config('owner_column_default', 'corretor_id'));
    }

    /**
     * O usuário é um papel restrito? Delega ao modelo (se implementa o método)
     * ou usa hasAnyRole() com acl.restricted_roles.
     */
    protected function isRestrictedOperator(AuthUser|Model $user): bool
    {
        if (method_exists($user, 'isRestrictedOperator')) {
            return (bool) $user->isRestrictedOperator();
        }

        return (bool) $user->hasAnyRole((array) Acl::config('restricted_roles', []));
    }
}
