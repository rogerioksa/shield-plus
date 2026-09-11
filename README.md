# Shield+ (`rogerioksa/shield-plus`)

ACL por convenção para painéis **Filament**: o conjunto canônico de permissões é
**descoberto do painel** (resources, pages, clusters, widgets), os grants de cada
papel são declarados numa **DSL de escopos** (`read`, `crud`, `manage`, `full` ou
lista crua de métodos), e o ownership por dados (`canOwn()`) restringe papéis aos
próprios registros.

Construído sobre `spatie/laravel-permission` (namespace `Securyt\Acl\`).

## Requisitos

- PHP ^8.3
- Laravel ^13.0
- Filament ^5.0
- `spatie/laravel-permission` instalado e configurado (trait `HasRoles` no User)
- Tabelas do permission: as migrations do plugin as criam (guardadas — se o app
  já publicou as do spatie, são ignoradas)

## Instalação

0. (Se consumir via repositório VCS) declare no `composer.json` do projeto:

   ```json
   {
       "repositories": [
           { "type": "vcs", "url": "https://github.com/rogerioksa/shield-plus" }
       ]
   }
   ```
   Ou use um Packagist privado (Satis) e omita o passo acima.

1. Instale e publique a config e as migrations:

   ```bash
   composer require rogerioksa/shield-plus:^0.1
   php artisan vendor:publish --tag=acl-config
   php artisan migrate                 # tabelas spatie (se não existirem) + shield_plus_grants
   ```

   As migrations do plugin são **guardadas**: tabelas do
   `spatie/laravel-permission` já criadas no app são ignoradas (no-op), e a
   tabela `shield_plus_grants` (overrides de grants do editor visual) é sempre
   criada se não existir. Também publicáveis via `--tag=acl-migrations`.

2. Publique/configure a matriz no `config/acl.php` do app:

   - `roles`: mapa papel → grants. `['*']` = conjunto canônico inteiro; senão
     `['Subject' => 'escopo']` (ou `['escopo1', 'method']`), com os grupos
     `pages` / `clusters` / `widgets` aceitando `'*'` ou lista de subjects.
   - `restricted_roles` / `supervision_roles` + `owner_column_default`
     (`'corretor_id'` por padrão — sobrescreva; ex.: `'user_id'`) / `owner_columns`
     por modelo (ex.: `App\Models\Appointment::class => 'user_id'`).

3. Ajuste o modelo de usuário (traits):

   ```php
   use Filament\Models\Contracts\HasName; // opcional
   use Securyt\Acl\Concerns\HasAclRoles;
   use Spatie\Permission\Traits\HasRoles;

   class User extends Authenticatable
   {
       use HasRoles, HasAclRoles;
   }
   ```

4. Provas e gates no painel:

   - **Resources/páginas:** nada obrigatório para abrir menus (Filament descobre);
     para pages standalone use o trait `GatesPage` (executa `View:<Page>`).
   - **Clusters:** trait `GatesCluster` (`canAccessClusteredComponents`).
   - **Widgets:** trait `GatesWidget` (`canView`).
   - **Models com ownership:** traits `ScopesVisibleTo` (escopo `visibleTo`) e
     `RestrictsOwnRecords` (`canOwn()` nas policies).

5. Rode os comandos:

   ```bash
   php artisan acl:sync                       # provê permissões + grants
   php artisan acl:sync --no-prune            # 1ª vez, se houver perms manuais
   php artisan acl:policy:generate --all      # policies canônicas com canOwn()
   ```

   Nas primeiras execuções use `--no-prune` enquanto existirem permissões
   fora do conjunto canônico; depois rode sem ele para remover órfãs.

   > `User` não deve ser passado ao gerador (`--except=User`) se houver policy
   > manual com regras de negócio.

## Como funciona

- **Conjunto canônico:** `Securyt\Acl\Support\Discovery` lê os painéis Filament
  registrados (resources por modelo, pages, clusters, widgets) e deriva
  `<Action>:<Subject>` (ex.: `ViewAny:Lead`, `View:DealPipeline`), respeitando
  `case`, `separator`, `resource_methods` e `subject_methods_overrides`.
- **Grants (DSL):** `config('acl.roles')` → `Acl::permissionsForRole()` expande
  escopos (`read` = `ViewAny, View`; `crud`; `manage`; `full` = todos) e os
  grupos de pages/clusters/widgets.
- **`acl:sync`:** cria permissões faltantes, faz `syncPermissions` por papel e
  remove órfãs (fora do canônico) — exceto com `--no-prune`. A matriz efetiva é
  `config('acl.roles')` **sobreposta** pelos overrides de `shield_plus_grants`
  (editor visual do cluster).
- **Gates:** `GatesPage` (sobre `canAccess`), `GatesCluster`, `GatesWidget` —
  sempre derivados do conjunto canônico, sem strings hardcoded.
- **Ownership:** `ScopesVisibleTo::scopeVisibleTo($user)` + `RestrictsOwnRecords::canOwn()`.

## Gestão de papéis

### Cluster Shield+ (editor visual)

O pacote entrega um cluster de configuração no painel (`ShieldPlusCluster` +
`ShieldPlusPlugin`), com duas páginas:

- **Visão geral** (`ShieldPlusOverviewPage`): estado atual (canônico, papéis,
  overrides do banco) e sincronização com um clique — mesma lógica do
  `acl:sync`, com toggle de prune.
- **Papéis e grants** (`ShieldPlusRolesPage`): editor da matriz por papel. Os
  grants salvos são persistidos em `shield_plus_grants` e **sobrepõem**
  `config('acl.roles')`, sem reescrever a config: `acl:sync` aplica a matriz
  efetiva (config + overrides). Deixar idêntico à config remove o override.

Para registrar o cluster num painel:

```php
use Securyt\Acl\Filament\ShieldPlusPlugin;

// dentro do panel(...) do PanelProvider:
->plugins([ShieldPlusPlugin::make()])
```

O acesso é permissionado pelas permissões canônicas (**`View:ShieldPlusCluster`**,
`View:ShieldPlusOverviewPage`, `View:ShieldPlusRolesPage`) — derivadas
automaticamente pelo `Discovery` e provisionadas pelo `acl:sync`. Papeis com `*`
(os super) as recebem; papéis explícitos precisam referenciá-las em `roles` da
config (grupos `pages` / `clusters`).

> Para **edição completa de papéis** (criar/renomear papéis, ajustar permissões
> pontuais no banco) a tela do app (ex.: RoleResource) continua app-level — o
> cluster gerencia a **matriz de grants**.

## Testes do pacote

```bash
composer test:package    # na app hospedeira: phpunit com bootstrap no vendor
```

## Config de referência

Ver `config/acl.php` do pacote (publicável com `--tag=acl-config`).

## Licença

MIT.