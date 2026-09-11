<?php

declare(strict_types=1);

namespace Securyt\Acl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Override de grants por papel (cluster Shield+ → painel Filament).
 * Cada linha representa a matriz DSL completa de um papel, substituindo a
 * entrada correspondente de config('acl.roles') quando presente.
 */
class ShieldPlusGrant extends Model
{
    protected $table = 'shield_plus_grants';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'grants' => 'array',
        ];
    }
}