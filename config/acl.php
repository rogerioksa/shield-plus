<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Convenção de nomeação
    |--------------------------------------------------------------------------
    | case: 'pascal'|'camel' — forma dos métodos dentro do nome da permissão.
    | separator: delimita método e subject (ex.: "ViewAny:Lead").
    | view_prefix: prefixo das permissões de pages/clusters/widgets.
    */
    'case' => 'pascal',
    'separator' => ':',
    'view_prefix' => 'View',

    // Guard usado nas permissões e papéis (padrão spatie).
    'guard' => 'web',

    // Papel que recebe o conjunto canônico inteiro.
    'super_admin' => 'super_admin',

    /*
    |--------------------------------------------------------------------------
    | Conjunto canônico de recursos
    |--------------------------------------------------------------------------
    | resource_methods: métodos de recurso (a ordem define a exibição).
    | subject_methods_overrides: redução por subject (ex.: Role nunca usa
    | Replicate/Reorder no domínio atual).
    */
    'resource_methods' => [
        'ViewAny', 'View', 'Create', 'Update', 'Delete',
        'DeleteAny', 'Restore', 'ForceDelete', 'ForceDeleteAny', 'RestoreAny',
    ],

    'subject_methods_overrides' => [
        'Role' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Escopos (DSL de grants)
    |--------------------------------------------------------------------------
    | Mapa nome-de-escopo => métodos. 'full' => null significa "todos os
    | métodos do subject" (respeitando subject_methods_overrides).
    */
    'scopes' => [
        'read' => ['ViewAny', 'View'],
        'crud' => ['ViewAny', 'View', 'Create', 'Update'],
        'manage' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
        'full' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership por dados
    |--------------------------------------------------------------------------
    | restricted_roles: papéis limitados aos próprios registros (visibleTo no
    | query scope e canOwn nas policies) — config-driven.
    | supervision_roles: papéis que enxergam todos os registros (isSupervisor()).
    | owner_column_default / owner_columns: coluna de responsável, genérica ou
    | por modelo (ex.: Appointment usa 'user_id').
    */
    'restricted_roles' => [],
    'supervision_roles' => [],
    'owner_column_default' => 'corretor_id',
    'owner_columns' => [],

    /*
    |--------------------------------------------------------------------------
    | Gerador de policies (acl:policy:generate)
    |--------------------------------------------------------------------------
    | path: diretório das policies (relativo a base_path() ou absoluto).
    | methods: métodos gerados (default = os do conjunto canônico).
    | ownership: usar canOwn() nos métodos de registro (view/update/delete/
    | restore/forceDelete).
    | single_parameter_methods: métodos que não recebem a instância do modelo.
    */
    'policies' => [
        'path' => 'app/Policies',
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete',
            'deleteAny', 'restore', 'forceDelete', 'restoreAny', 'forceDeleteAny',
        ],
        'ownership' => true,
        'single_parameter_methods' => ['viewAny', 'create', 'deleteAny', 'forceDeleteAny', 'restoreAny'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Grants por papel
    |--------------------------------------------------------------------------
    | Chave = papel; valor = ['*'] (conjunto canônico inteiro) ou um mapa
    | subject => grant. Grant = escopo ('crud'), lista de escopos/métodos
    | (['read', 'create']) ou lista crua de métodos. Grupos 'pages',
    | 'clusters' e 'widgets' aceitam '*' ou lista de names de subjects.
    */
    'roles' => [],
];
