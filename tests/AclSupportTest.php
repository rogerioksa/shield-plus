<?php

/**
 * Testes unitários do pacote securyt/acl, independentes do app.
 *
 * Rodam via `composer test:package` (bootstrap = vendor da aplicação);
 * cobrem nomeação e DSL de grants do Support\Acl (que tem fallback de config
 * para fora do container). Testes de integração (Discovery/Filament/matriz)
 * vivem em tests/Feature/Acl da aplicação.
 */

namespace Securyt\Acl\Tests;

use PHPUnit\Framework\TestCase;
use Securyt\Acl\Support\Acl;

final class AclSupportTest extends TestCase
{
    public function test_naming_defaults(): void
    {
        $this->assertSame('View:Lead', Acl::viewPermission('lead'));
        $this->assertSame('Create:Deal', Acl::permission('create', 'deal'));
        $this->assertSame('Create:Leads', Acl::permission('create', 'leads'));
        $this->assertSame('UpdateAny:Imovel', Acl::permission('updateAny', 'imovel'));
        $this->assertSame('ViewAny', Acl::normalizeMethod('view-any'));
    }

    public function test_subject_normalization(): void
    {
        $this->assertSame('CrmOverviewWidget', Acl::normalizeSubject('crm_overview_widget'));
        $this->assertSame('FunnelStage', Acl::normalizeSubject('funnel-stage'));
    }

    public function test_resource_methods_and_overrides(): void
    {
        $methods = Acl::resourceMethods();

        $this->assertContains('ViewAny', $methods);
        $this->assertContains('Delete', $methods);
        $this->assertContains('View', $methods);

        $this->assertSame(['ViewAny', 'View', 'Create', 'Update', 'Delete'], Acl::methodsForSubject('Role'));
    }

    public function test_expand_grant_all_returns_full_method_set(): void
    {
        $this->assertSame(Acl::resourceMethods(), Acl::expandGrant('*'));
        $this->assertSame(Acl::resourceMethods(), Acl::expandGrant(['*']));
    }

    public function test_expand_grant_scopes(): void
    {
        $this->assertSame(['ViewAny', 'View'], Acl::expandGrant('read'));
        $this->assertSame(['ViewAny', 'View', 'Create', 'Update'], Acl::expandGrant('crud'));
        $this->assertSame(['ViewAny', 'View', 'Create', 'Update', 'Delete'], Acl::expandGrant('manage'));

        $this->assertNotContains('DeleteAny', Acl::expandGrant('manage'));
        $this->assertSame(Acl::resourceMethods(), Acl::expandGrant('full'));
    }

    public function test_expand_grant_accepts_explicit_methods(): void
    {
        $this->assertSame(['Create'], Acl::expandGrant(['create']));
        $this->assertSame(['View', 'Update'], Acl::expandGrant(['view', 'update']));
    }

    public function test_permissions_for_subject_grant(): void
    {
        $this->assertSame(
            ['ViewAny:Lead', 'View:Lead'],
            Acl::permissionsForSubjectGrant('lead', 'read'),
        );

        $this->assertSame(
            ['ViewAny:Lead', 'View:Lead', 'Create:Lead', 'Update:Lead'],
            Acl::permissionsForSubjectGrant('Lead', 'crud'),
        );
    }

    public function test_merge_role_overrides_replaces_only_override_roles(): void
    {
        $config = [
            'admin' => '*',
            'gerente' => ['pages' => ['DealPipeline']],
        ];

        $overrides = [
            'gerente' => ['pages' => ['DealPipeline', 'ShieldPlusRolesPage']],
            'corretor_extra' => ['read'],
        ];

        $merged = Acl::mergeRoleOverrides($config, $overrides);

        $this->assertSame('*', $merged['admin']);
        $this->assertSame(['pages' => ['DealPipeline', 'ShieldPlusRolesPage']], $merged['gerente']);
        $this->assertSame(['read'], $merged['corretor_extra']);
    }

    public function test_merge_role_overrides_with_no_overrides_is_identity(): void
    {
        $config = ['admin' => '*'];

        $this->assertSame($config, Acl::mergeRoleOverrides($config, []));
    }
}
