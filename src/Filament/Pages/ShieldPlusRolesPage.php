<?php

declare(strict_types=1);

namespace Securyt\Acl\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Securyt\Acl\Concerns\GatesPage;
use Securyt\Acl\Filament\Clusters\ShieldPlusCluster;
use Securyt\Acl\Models\ShieldPlusGrant;
use Securyt\Acl\Support\Acl;
use Securyt\Acl\Support\Discovery;
use Securyt\Acl\Support\Syncer;

/**
 * Editor de grants por papel (personalizações do ACL): sobrepõe a matriz de
 * config('acl.roles') com overrides persistidos em `shield_plus_grants`.
 */
class ShieldPlusRolesPage extends Page implements HasForms
{
    use GatesPage, InteractsWithForms;

    protected static string $cluster = ShieldPlusCluster::class;

    protected static ?string $slug = 'papeis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected static ?string $navigationLabel = 'Papéis e grants';

    protected static ?string $title = 'Shield+ — Papéis e grants';

    protected static ?int $navigationSort = 2;

    protected string $view = 'shield-plus::roles';

    public ?array $data = [];

    /** @var array<string, string> */
    protected array $roleOptions = [];

    /** @var array<string, list<string>> */
    protected array $groupOptions = [];

    /** @var list<string> */
    protected array $scopeOptions = [];

    /** @var list<string> */
    protected array $resourceSubjects = [];

    public function mount(): void
    {
        $this->roleOptions = $this->buildRoleOptions();

        $firstRole = array_key_first($this->roleOptions) ?? '';

        $this->form->fill([
            'role' => $firstRole,
            'super' => false,
            'resources' => [],
            'pages' => [],
            'clusters' => [],
            'widgets' => [],
            'sync_after' => true,
            'prune' => false,
        ]);

        if ($firstRole !== '') {
            $this->applyRoleToState($firstRole);
        }
    }

    /** @return array<string, string> */
    private function buildRoleOptions(): array
    {
        $options = [];

        foreach (Acl::rolesWithOverrides() as $role => $grants) {
            $options[(string) $role] = (string) $role;
        }

        return $options;
    }

    private function formGroupOptions(): void
    {
        if ($this->groupOptions !== []) {
            return;
        }

        $discovery = Discovery::all();

        $this->groupOptions = [
            'pages' => array_keys($discovery->pages()),
            'clusters' => array_keys($discovery->clusters()),
            'widgets' => array_keys($discovery->widgets()),
        ];

        $this->scopeOptions = [
            ...array_keys(Acl::scopes()),
            '*',
        ];

        $this->resourceSubjects = array_keys($discovery->resources());
    }

    public function applyRoleToState(string $role): void
    {
        $this->formGroupOptions();

        $grants = Acl::effectiveGrantsForRole($role);
        $isAll = Acl::grantIsAll($grants);

        $resources = [];
        $groups = ['pages' => [], 'clusters' => [], 'widgets' => []];

        if (! $isAll && is_array($grants)) {
            foreach ($grants as $subject => $grant) {
                if (in_array($subject, array_keys($groups), true)) {
                    $groups[$subject] = array_values((array) $grant);

                    continue;
                }

                if (is_string($grant)) {
                    $resources[] = ['subject' => $subject, 'grant' => $grant];
                }
            }
        }

        $resourceOptions = collect($this->resourceSubjects)
            ->mapWithKeys(fn (string $subject): array => [$subject => $subject])
            ->all();

        $grantOptions = collect($this->scopeOptions)
            ->mapWithKeys(fn (string $scope): array => [$scope => $scope])
            ->all();

        $this->form->fill([
            'role' => $role,
            'super' => $isAll,
            'resources' => $resources,
            'pages' => $groups['pages'],
            'clusters' => $groups['clusters'],
            'widgets' => $groups['widgets'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $this->formGroupOptions();

        $resourceOptions = collect($this->resourceSubjects)
            ->mapWithKeys(fn (string $subject): array => [$subject => $subject])
            ->all();

        $grantOptions = collect($this->scopeOptions)
            ->mapWithKeys(fn (string $scope): array => [$scope => $scope])
            ->all();

        $groupCheckboxes = function (string $group, string $label): CheckboxList {
            $options = collect($this->groupOptions[$group])
                ->mapWithKeys(fn (string $subject): array => [$subject => $subject])
                ->all();

            return CheckboxList::make($group)
                ->label($label)
                ->options($options)
                ->columns(2);
        };

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Papel')
                    ->schema([
                        Select::make('role')
                            ->label('Papel')
                            ->options($this->roleOptions)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (string $state): void {
                                $this->applyRoleToState($state);
                            }),

                        Toggle::make('super')
                            ->label('Todas as permissões do conjunto canônico (papel "*")')
                            ->helperText('Equivale a `*` na matriz — sobrescreve a matriz inteira do papel.')
                            ->live(),
                    ]),
                Section::make('Grants por recurso')
                    ->description('Usa o DSL de escopos da config `acl.scopes`.')
                    ->visible(fn (Get $get): bool => ! (bool) $get('super'))
                    ->schema([
                        Repeater::make('resources')
                            ->label('Recursos')
                            ->schema([
                                Select::make('subject')
                                    ->label('Recurso')
                                    ->options($resourceOptions)
                                    ->searchable()
                                    ->required(),

                                Select::make('grant')
                                    ->label('Grant')
                                    ->options($grantOptions)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Adicionar recurso')
                            ->defaultItems(0),
                    ]),
                Section::make('Grupos (pages / clusters / widgets)')
                    ->visible(fn (Get $get): bool => ! (bool) $get('super'))
                    ->schema([
                        Tabs::make('groups')
                            ->contained()
                            ->tabs([
                                Tab::make('pages')
                                    ->label('Páginas')
                                    ->schema([$groupCheckboxes('pages', 'Páginas')]),
                                Tab::make('clusters')
                                    ->label('Clusters')
                                    ->schema([$groupCheckboxes('clusters', 'Clusters')]),
                                Tab::make('widgets')
                                    ->label('Widgets')
                                    ->schema([$groupCheckboxes('widgets', 'Widgets')]),
                            ]),
                    ]),
                Section::make('Aplicação')
                    ->schema([
                        Toggle::make('sync_after')
                            ->label('Sincronizar os papéis após salvar')
                            ->default(true),

                        Toggle::make('prune')
                            ->label('Remover permissões fora do conjunto canônico (prune)')
                            ->helperText('Desligue para preservar permissões extras aplicadas manualmente.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $role = (string) ($data['role'] ?? '');

        if ($role === '') {
            Notification::make()
                ->title('Selecione um papel para salvar')
                ->danger()
                ->send();

            return;
        }

        $map = $this->buildGrants($data);

        $configDefault = (array) (Acl::roles()[$role] ?? []);

        if ($map === $configDefault) {
            ShieldPlusGrant::query()
                ->where('role', $role)
                ->where('guard_name', Acl::guard())
                ->delete();
        } else {
            ShieldPlusGrant::updateOrCreate(
                ['role' => $role, 'guard_name' => Acl::guard()],
                ['grants' => $map],
            );
        }

        if ((bool) ($data['sync_after'] ?? false)) {
            $report = (new Syncer)->run(prune: (bool) ($data['prune'] ?? false));

            Notification::make()
                ->title('Grants do papel '.$role.' salvos e ACL sincronizado')
                ->body(sprintf(
                    '%d permissões canônicas · %d novas · %d podadas.',
                    $report->canonical,
                    $report->created,
                    $report->pruned,
                ))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Grants do papel '.$role.' salvos')
            ->body('Sincronize depois na página "Visão geral" (ou rode `php artisan acl:sync`).')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|list<string>
     */
    private function buildGrants(array $data): array
    {
        if ((bool) ($data['super'] ?? false)) {
            return ['*'];
        }

        $groups = ['pages', 'clusters', 'widgets'];
        $map = [];

        foreach ((array) ($data['resources'] ?? []) as $row) {
            $subject = (string) ($row['subject'] ?? '');
            $grant = (string) ($row['grant'] ?? '');

            if ($subject === '' || $grant === '') {
                continue;
            }

            $map[$subject] = $grant;
        }

        foreach ($groups as $group) {
            $members = array_values(array_filter((array) ($data[$group] ?? [])));

            if ($members !== []) {
                $map[$group] = $members;
            }
        }

        return $map;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar grants do papel')
                ->submit('save'),
        ];
    }
}