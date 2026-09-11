<?php

declare(strict_types=1);

namespace Securyt\Acl\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Securyt\Acl\Concerns\GatesPage;
use Securyt\Acl\Filament\Clusters\ShieldPlusCluster;
use Securyt\Acl\Support\Acl;
use Securyt\Acl\Support\Discovery;
use Securyt\Acl\Support\Syncer;
use Spatie\Permission\Models\Role;

/**
 * Visão geral do ACL: estado atual (canônico, papéis, overrides) e a sincronização
 * com um clique (mesma lógica do comando `acl:sync`).
 */
class ShieldPlusOverviewPage extends Page implements HasForms
{
    use GatesPage, InteractsWithForms;

    protected static string $cluster = ShieldPlusCluster::class;

    protected static ?string $slug = 'visao-geral';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Visão geral';

    protected static ?string $title = 'Shield+ — Visão geral';

    protected static ?int $navigationSort = 1;

    protected string $view = 'shield-plus::overview';

    public ?array $data = [];

    public int $canonical = 0;

    public int $overrides = 0;

    public int $rolesCount = 0;

    /** @var list<array{name: string, permissions_count: int}> */
    public array $roles = [];

    public function mount(): void
    {
        $this->form->fill([
            'prune' => false,
        ]);

        $this->refreshState();
    }

    public function refreshState(): void
    {
        $this->canonical = count((new Discovery)->allPermissions());
        $this->overrides = count(Acl::roleOverrides());

        $this->roles = Role::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'permissions_count' => (int) $role->permissions_count,
            ])
            ->all();

        $this->rolesCount = count($this->roles);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Estado atual')
                    ->description('Conjunto canônico derivado dos painéis Filament e da matriz efetiva (config + overrides do banco).')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('canonical')
                                    ->label('Permissões canônicas')
                                    ->content($this->canonical.' permissões'),

                                Placeholder::make('roles_count')
                                    ->label('Papéis')
                                    ->content($this->rolesCount.' papéis'),

                                Placeholder::make('overrides')
                                    ->label('Overrides no banco')
                                    ->content($this->overrides.' papel(éis) com grants editados'),
                            ]),
                    ]),
                Section::make('Papéis')
                    ->collapsible()
                    ->schema([
                        Grid::make(4)
                            ->schema(
                                collect($this->roles)
                                    ->map(fn (array $role): Placeholder => Placeholder::make('role_'.$role['name'])
                                        ->label($role['name'])
                                        ->content(number_format($role['permissions_count']).' permissões'))
                                    ->values()
                                    ->all(),
                            ),
                    ]),
                Section::make('Sincronizar')
                    ->description('Equivale a `php artisan acl:sync`. Reaplica grants (config + overrides do banco) nos papéis.')
                    ->schema([
                        Toggle::make('prune')
                            ->label('Remover permissões fora do conjunto canônico (prune de órfãs)')
                            ->helperText('Desligue para preservar permissões extras aplicadas manualmente.'),
                    ]),
            ]);
    }

    public function sync(): void
    {
        $data = $this->form->getState();

        $report = (new Syncer)->run(prune: (bool) ($data['prune'] ?? false));

        $this->refreshState();

        Notification::make()
            ->title('ACL sincronizado')
            ->body(sprintf(
                '%d permissões canônicas · %d novas · %d podadas · %d papéis.',
                $report->canonical,
                $report->created,
                $report->pruned,
                count($report->roleCounts),
            ))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sincronizar agora')
                ->submit('sync'),
        ];
    }
}