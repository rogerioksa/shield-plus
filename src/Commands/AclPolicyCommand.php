<?php

declare(strict_types=1);

namespace Securyt\Acl\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Securyt\Acl\Support\Acl;
use Securyt\Acl\Support\Discovery;
use Spatie\Permission\Models\Role;

class AclPolicyCommand extends Command
{
    protected $signature = 'acl:policy:generate
        {--model= : Nome do subject ou classe do modelo (ex.: Lead, App\\Models\\Lead)}
        {--all : Gera/atualiza as policies de todos os recursos descobertos}
        {--force : Sobrescreve policies existentes}
        {--except= : Subjects/classes a pular, vírgula separada (ex.: User)}
        {--path= : Diretório de destino (padrão: acl.policies.path)}';

    protected $description = 'Gera policies estilo Shield a partir do conjunto canônico (canOwn() nos métodos de registro).';

    public function handle(): int
    {
        if (! $this->option('all') && blank($this->option('model'))) {
            $this->components->error('Informe --all ou --model=<subject|classe>.');

            return self::INVALID;
        }

        $discovery = new Discovery;
        $resourceModels = $discovery->resourceModels();
        $exceptions = $this->resolveExceptions();
        $models = $this->resolveModels((string) $this->option('model'), $resourceModels, $exceptions);

        if ($models === []) {
            $this->components->error('Nenhum modelo encontrado.');

            return self::FAILURE;
        }

        $directory = $this->resolveDirectory();
        $namespace = $this->resolveNamespace($directory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach ($models as $modelClass) {
            $this->generatePolicy($modelClass, $directory, $namespace);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, class-string<Model>>  $resourceModels
     * @param  list<class-string<Model>>  $exceptions
     * @return list<class-string<Model>>
     */
    private function resolveModels(string $model, array $resourceModels, array $exceptions): array
    {
        if ($this->option('all')) {
            return array_values(array_filter(
                $resourceModels,
                fn (string $class): bool => ! in_array($class, $exceptions, true),
            ));
        }

        if (str_contains($model, '\\')) {
            return class_exists($model) && ! in_array($model, $exceptions, true) ? [$model] : [];
        }

        $subject = Acl::normalizeSubject($model);
        $class = $resourceModels[$subject] ?? null;

        return $class !== null && ! in_array($class, $exceptions, true) ? [$class] : [];
    }

    /**
     * @return list<class-string<Model>>
     */
    private function resolveExceptions(): array
    {
        $raw = collect(explode(',', (string) $this->option('except')))
            ->filter()
            ->map(fn (string $value): string => trim($value));

        if ($raw->isEmpty()) {
            return [];
        }

        $discovery = new Discovery;
        $resourceModels = $discovery->resourceModels();

        return $raw
            ->map(function (string $value) use ($resourceModels): ?string {
                if (str_contains($value, '\\') || str_contains($value, '/')) {
                    return str_contains($value, '\\') ? $value : str_replace('/', '\\', $value);
                }

                return $resourceModels[Acl::normalizeSubject($value)] ?? null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveDirectory(): string
    {
        $path = (string) $this->option('path') ?: (string) Acl::config('policies.path', 'app/Policies');

        $normalized = strtolower(str_replace('\\', '/', $path));
        $root = strtolower(str_replace('\\', '/', base_path()));

        return str_starts_with($normalized, $root) ? $path : base_path($path);
    }

    private function resolveNamespace(string $directory): string
    {
        $normalized = str_replace('\\', '/', $directory);
        $root = rtrim(str_replace('\\', '/', base_path()), '/');

        $relative = stripos($normalized, $root.'/') === 0
            ? substr($normalized, strlen($root) + 1)
            : $normalized;

        $relative = trim($relative, '/');

        if ($relative === '') {
            return 'App';
        }

        return implode('\\', array_map(
            fn (string $segment): string => Str::studly(Str::lower($segment)),
            explode('/', $relative),
        ));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function generatePolicy(string $modelClass, string $directory, string $namespace): void
    {
        $name = class_basename($modelClass);

        if ($modelClass === Role::class) {
            $this->components->info('[skip] Role: policy registrada pelo pacote securyt/acl.');

            return;
        }

        $file = $directory.DIRECTORY_SEPARATOR."{$name}Policy.php";

        if (is_file($file) && ! $this->option('force')) {
            $this->components->info("[skip] {$name}Policy já existe (use --force para sobrescrever).");

            return;
        }

        $content = $this->renderPolicy($namespace, $modelClass, $name);
        file_put_contents($file, $content);

        $this->components->info("[ok] {$name}Policy gerada em ".$this->relativePath($file));
    }

    private function relativePath(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $root = str_replace('\\', '/', base_path());

        return str_starts_with(strtolower($normalized), strtolower($root))
            ? substr($normalized, strlen($root))
            : $normalized;
    }

    private function renderPolicy(string $namespace, string $modelClass, string $name): string
    {
        $methods = (array) Acl::config('policies.methods');
        $singleParameter = (array) Acl::config('policies.single_parameter_methods', []);
        $ownership = (bool) Acl::config('policies.ownership', true);

        $body = [];

        foreach ($methods as $method) {
            $withModel = ! in_array($method, $singleParameter, true);
            $ability = Acl::permission($method, $name);
            $variable = lcfirst($name);

            $body[] = $withModel
                ? "    public function {$method}(AuthUser \$authUser, {$name} \${$variable}): bool"
                : "    public function {$method}(AuthUser \$authUser): bool";

            $body[] = '    {';

            if ($withModel && $ownership) {
                $body[] = "        return \$this->canOwn(\$authUser, \${$variable}, '{$ability}');";
            } else {
                $body[] = "        return \$authUser->can('{$ability}');";
            }

            $body[] = '    }';
            $body[] = '';
        }

        $bodyBlock = implode("\n", $body);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Foundation\Auth\User as AuthUser;
use {$modelClass};
use Securyt\Acl\Concerns\RestrictsOwnRecords;

class {$name}Policy
{
    use RestrictsOwnRecords;

{$bodyBlock}
}

PHP;
    }
}
