<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Overrides de grants por papel persistidos pela página de configuração do
     * painel (cluster Shield+): estendem/sobrescrevem a matriz de config('acl.roles').
     */
    public function up(): void
    {
        Schema::create('shield_plus_grants', static function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('guard_name')->default('web');
            $table->json('grants')->nullable();
            $table->timestamps();

            $table->unique(['role', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shield_plus_grants');
    }
};