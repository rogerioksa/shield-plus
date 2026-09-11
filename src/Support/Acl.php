<?php

declare(strict_types=1);

namespace Securyt\Acl\Support;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Securyt\Acl\Models\ShieldPlusGrant;

/**
 * Nomeação, conjunto canônico e expansão dos grants (DSL) do ACL.
 *
 * Toda a matriz vive em config('acl'); esta classe é só o mecanismo.
 */
final class Acl
{
    public static function config(?string $key = null, mixed $default = null): mixed
    {
        $container = Container::getInstance();

        if ($container !== null && $container->bound('config')) {
            return $key === null ? config('acl') : config("acl.{$key}", $default);
        }

        $values = self::rawConfig();

        return $key === null ? $values : ($values[$key] ?? $default);
    }

    /**
     * Config do arquivo (defaults do pacote + override da aplicação publicada),
     * usada fora do container (testes standalone via testbench/phpunit do pacote).
     *
     * @return array<string, mixed>
     */
    private static function rawConfig(): array
    {
        $package = __DIR__.'/../../config/acl.php';
        $appConfig = __DIR__.'/../../../../../config/acl.php';

        $values = require $package;

        if (is_file($appConfig)) {
            $values = array_replace_recursive($values, require $appConfig);
        }

        return $values;
    }

    public static function case(): string
    {
        return (string) self::config('case', 'pascal');
    }

    public static function separator(): string
    {
        return (string) self::config('separator', ':');
    }

    public static function viewPrefix(): string
    {
        return (string) self::config('view_prefix', 'View');
    }

    public static function guard(): string
    {
        return (string) self::config('guard', 'web');
    }

    public static function superAdminRole(): string
    {
        return (string) self::config('super_admin', 'super_admin');
    }

    public static function normalizeMethod(string $method): string
    {
        return self::case() === 'pascal'
            ? Str::ucfirst(Str::camel($method))
            : Str::camel($method);
    }

    public static function normalizeSubject(string $subject): string
    {
        return Str::ucfirst(Str::camel($subject));
    }

    public static function permission(string $action, string $subject): string
    {
        return self::normalizeMethod($action).self::separator().self::normalizeSubject($subject);
    }

    public static function viewPermission(string $subject): string
    {
        return self::permission(self::viewPrefix(), $subject);
    }

    /** @return list<string> */
    public static function resourceMethods(): array
    {
        return array_map(
            fn (string $method): string => self::normalizeMethod($method),
            (array) self::config('resource_methods', []),
        );
    }

    /** @return list<string> */
    public static function methodsForSubject(string $subject): array
    {
        $name = self::normalizeSubject($subject);
        $overrides = (array) self::config('subject_methods_overrides', []);

        return array_map(
            fn (string $method): string => self::normalizeMethod($method),
            (array) ($overrides[$name] ?? self::config('resource_methods', [])),
        );
    }

    /** @return array<string, mixed> */
    public static function scopes(): array
    {
        return (array) self::config('scopes', []);
    }

    /** @return array<string, mixed> */
    public static function roles(): array
    {
        return (array) self::config('roles', []);
    }

    /**
     * Overrides de grants persistidos no banco (cluster Shield+), por papel.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function roleOverrides(): array
    {
        if (! Container::getInstance()->bound('db') || ! self::hasGrantsTable()) {
            return [];
        }

        return ShieldPlusGrant::query()
            ->pluck('grants', 'role')
            ->map(fn ($grants): array => is_array($grants) ? $grants : [])
            ->all();
    }

    /**
     * Matriz efetiva de papéis: config acl.roles como base, com os overrides do
     * banco substituindo a entrada do papel quando existirem.
     *
     * @param  array<string, mixed>|null  $overrides
     * @return array<string, mixed>
     */
    public static function mergeRoleOverrides(array $configRoles, array $overrides = []): array
    {
        foreach ($overrides as $role => $grants) {
            $configRoles[(string) $role] = is_array($grants) ? $grants : [];
        }

        return $configRoles;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rolesWithOverrides(): array
    {
        return self::mergeRoleOverrides(self::roles(), self::roleOverrides());
    }

    /**
     * Grants efetivos de um papel (override do banco > config), ou [] se o papel
     * não existe na matriz.
     *
     * @return array<string, mixed>|list<string>
     */
    public static function effectiveGrantsForRole(string $role): array
    {
        $overrides = self::roleOverrides();

        if (array_key_exists($role, $overrides)) {
            return $overrides[$role];
        }

        return (array) (self::roles()[$role] ?? []);
    }

    private static function hasGrantsTable(): bool
    {
        try {
            return Schema::hasTable('shield_plus_grants');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function grantIsAll(array|string|null $grant): bool
    {
        return (is_string($grant) && $grant === '*') || (is_array($grant) && in_array('*', $grant, true));
    }

    /**
     * Expande um grant (escopo, escopos ou métodos) para a lista de métodos.
     *
     * @return list<string>
     */
    public static function expandGrant(array|string|null $grant): array
    {
        if (self::grantIsAll($grant)) {
            return self::resourceMethods();
        }

        $tokens = is_array($grant) ? $grant : [$grant];

        return self::expandTokens($tokens);
    }

    /**
     * Permissões efetivas de um subject para um grant.
     *
     * @return list<string>
     */
    public static function permissionsForSubjectGrant(string $subject, array|string|null $grant): array
    {
        $subject = self::normalizeSubject($subject);
        $available = self::methodsForSubject($subject);

        $methods = array_values(array_intersect(
            $available,
            self::expandGrant($grant),
        ));

        return array_map(
            fn (string $method): string => self::permission($method, $subject),
            $methods,
        );
    }

    /**
     * Permissões efetivas de um papel, a partir do conjunto canônico descoberto.
     *
     * @return list<string>
     */
    public static function permissionsForRole(string $role, Discovery $discovery): array
    {
        $grants = self::roles()[$role] ?? [];

        if (self::grantIsAll($grants)) {
            return $discovery->allPermissions();
        }

        $permissions = [];

        foreach ((array) $grants as $subject => $grant) {
            if (in_array($subject, ['pages', 'clusters', 'widgets'], true)) {
                $permissions = [
                    ...$permissions,
                    ...self::permissionsForGroup((string) $subject, (array) $grant, $discovery),
                ];

                continue;
            }

            if (is_string($grant) || is_array($grant)) {
                $permissions = [
                    ...$permissions,
                    ...self::permissionsForSubjectGrant((string) $subject, $grant),
                ];
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private static function expandTokens(array $tokens): array
    {
        $scopes = self::scopes();
        $methods = [];

        foreach ($tokens as $token) {
            if (array_key_exists($token, $scopes)) {
                $scope = $scopes[$token];

                $methods = $scope === null
                    ? [...$methods, ...self::resourceMethods()]
                    : [...$methods, ...(array) $scope];
            } else {
                $methods[] = $token;
            }
        }

        return array_values(array_unique(array_map(
            fn (string $method): string => self::normalizeMethod($method),
            $methods,
        )));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function permissionsForGroup(string $group, array $names, Discovery $discovery): array
    {
        $available = $discovery->group($group);

        if (self::grantIsAll($names)) {
            $names = array_keys($available);
        }

        $permissions = [];

        foreach ($names as $name) {
            $subject = self::normalizeSubject($name);

            if (! array_key_exists($subject, $available)) {
                continue;
            }

            $permissions[] = self::viewPermission($subject);
        }

        return $permissions;
    }
}
