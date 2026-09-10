<?php

declare(strict_types=1);

namespace Securyt\Acl\Support;

use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

/**
 * Descobre o conjunto canônico de subjects a partir dos painéis Filament
 * registrados: resources (model único por subject), pages, clusters e widgets.
 */
final class Discovery
{
    /** @var array{resources: array<string, list<string>>, pages: array<string, list<string>>, clusters: array<string, list<string>>, widgets: array<string, list<string>>}|null */
    private ?array $subjects = null;

    /** @var non-empty-list<string> */
    private array $panels;

    /**
     * @param  list<string>|null  $panels
     */
    public function __construct(?array $panels = null)
    {
        $this->panels = $panels ?: array_keys(Filament::getPanels());
    }

    public static function all(): self
    {
        return new self;
    }

    /**
     * @return non-empty-list<string>
     */
    public function panels(): array
    {
        return $this->panels;
    }

    /**
     * @return array{resources: array<string, list<string>>, pages: array<string, list<string>>, clusters: array<string, list<string>>, widgets: array<string, list<string>>}
     */
    public function subjects(): array
    {
        if ($this->subjects !== null) {
            return $this->subjects;
        }

        $resources = $pages = $clusters = $widgets = [];

        foreach ($this->panels as $panelId) {
            $panel = Filament::getPanel($panelId);
            $this->subjectsForPanel($panel, $resources, $pages, $clusters, $widgets);
        }

        ksort($resources);
        ksort($pages);
        ksort($clusters);
        ksort($widgets);

        return $this->subjects = compact('resources', 'pages', 'clusters', 'widgets');
    }

    /**
     * @param  array<string, list<string>>  $resources
     * @param  array<string, list<string>>  $pages
     * @param  array<string, list<string>>  $clusters
     * @param  array<string, list<string>>  $widgets
     */
    private function subjectsForPanel(
        Panel $panel,
        array &$resources,
        array &$pages,
        array &$clusters,
        array &$widgets,
    ): void {
        foreach ($panel->getResources() as $resource) {
            $subject = Acl::normalizeSubject(class_basename($resource::getModel()));
            $resources[$subject] = Acl::methodsForSubject($subject);
        }

        foreach ($panel->getPages() as $page) {
            if (is_a($page, Cluster::class, true) || is_a($page, Dashboard::class, true)) {
                continue;
            }

            $pages[Acl::normalizeSubject(class_basename($page))] = [Acl::viewPrefix()];
        }

        foreach ($panel->getClusters() as $cluster) {
            $clusters[Acl::normalizeSubject(class_basename($cluster))] = [Acl::viewPrefix()];
        }

        foreach ($panel->getWidgets() as $widget) {
            $widgets[Acl::normalizeSubject(class_basename($widget))] = [Acl::viewPrefix()];
        }
    }

    /** @return array<string, list<string>> */
    public function resources(): array
    {
        return $this->subjects()['resources'];
    }

    /** @return array<string, list<string>> */
    public function pages(): array
    {
        return $this->subjects()['pages'];
    }

    /** @return array<string, list<string>> */
    public function clusters(): array
    {
        return $this->subjects()['clusters'];
    }

    /** @return array<string, list<string>> */
    public function widgets(): array
    {
        return $this->subjects()['widgets'];
    }

    /**
     * Mapa subject => classe do modelo (para o gerador de policies).
     *
     * @return array<string, class-string<Model>>
     */
    public function resourceModels(): array
    {
        $models = [];

        foreach ($this->panels as $panelId) {
            $panel = Filament::getPanel($panelId);

            foreach ($panel->getResources() as $resource) {
                $subject = Acl::normalizeSubject(class_basename($resource::getModel()));
                $models[$subject] = $resource::getModel();
            }
        }

        return $models;
    }

    /** @return array<string, list<string>> */
    public function group(string $group): array
    {
        return $this->subjects()[$group] ?? [];
    }

    /** @return list<string> */
    public function allPermissions(): array
    {
        $permissions = [];

        foreach ($this->subjects() as $names) {
            foreach ($names as $subject => $methods) {
                foreach ($methods as $method) {
                    $permissions[] = Acl::permission($method, $subject);
                }
            }
        }

        return array_values(array_unique($permissions));
    }
}
