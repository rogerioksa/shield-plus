<?php

declare(strict_types=1);

namespace Securyt\Acl\Commands;

use Illuminate\Console\Command;
use Securyt\Acl\Support\Syncer;

class AclSyncCommand extends Command
{
    protected $signature = 'acl:sync
        {--panel=* : Painéis a considerar (padrão: todos)}
        {--no-prune : Mantém permissões fora do conjunto canônico}';

    protected $description = 'Sincroniza permissões e grants de papéis a partir do conjunto canônico (discovery do painel + config acl.roles, com overrides do banco).';

    public function handle(): int
    {
        $syncer = new Syncer($this->option('panel') ?: null);

        try {
            $report = $syncer->run(prune: ! $this->option('no-prune'));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->task('Descobrindo conjunto canônico ('.$report->canonical.' permissões)', static fn () => true);

        if ($report->created > 0) {
            $this->components->task('Criando permissões novas ('.$report->created.')', static fn () => true);
        }

        $this->components->info('Grants por papel:');

        foreach ($report->roleCounts as $role => $count) {
            $this->line('  '.str_pad($role, 14).number_format($count));
        }

        if ($report->pruned > 0) {
            $this->components->task('Removendo permissões órfãs ('.$report->pruned.')', static fn () => true);
        }

        $this->components->info('ACL sincronizado ('.count($report->roleCounts).' papéis, '.$report->canonical.' permissões canônicas).');

        return self::SUCCESS;
    }
}