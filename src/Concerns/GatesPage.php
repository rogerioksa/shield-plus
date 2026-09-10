<?php

declare(strict_types=1);

namespace Securyt\Acl\Concerns;

use Filament\Facades\Filament;
use Securyt\Acl\Support\Acl;

/**
 * Gate de acesso para Pages do Filament baseado na permissão "View:<Page>".
 */
trait GatesPage
{
    public static function pagePermission(): string
    {
        return Acl::viewPermission(class_basename(static::class));
    }

    public static function canAccess(): bool
    {
        return (bool) (Filament::auth()->user()?->can(static::pagePermission()) ?? false);
    }
}
