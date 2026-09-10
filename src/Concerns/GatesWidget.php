<?php

declare(strict_types=1);

namespace Securyt\Acl\Concerns;

use Filament\Facades\Filament;
use Securyt\Acl\Support\Acl;

/**
 * Gate de visibilidade para Widgets do Filament baseado na permissão
 * "View:<Widget>".
 */
trait GatesWidget
{
    public static function canView(): bool
    {
        return (bool) (Filament::auth()->user()?->can(Acl::viewPermission(class_basename(static::class))) ?? false);
    }
}
