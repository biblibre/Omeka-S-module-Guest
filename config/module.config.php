 <?php
return [
        'forms' => [
        'invokables' => [
                         'GuestUser\Form\ConfigGuestUserForm' => 'GuestUser\Form\ConfigRepertoryForm',
        ],

        ],
        'controllers' => [
        'invokables' => [
                         'GuestUser\Controller\GuestUser' => 'GuestUser\Controller\GuestUserController',
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                                   'guestuser' => [
                                                   'type' => 'Segment',
                                                   'options' => [
                                                                 'route' => '/guestuser/:action',

                                                      'defaults' => [
                                                                     '__NAMESPACE__' => 'GuestUser\Controller',
                                                                     'controller' => 'GuestUser',
                                                      ],                                      ],
                ],
                ],
            ],
        ],
    ],

    'entity_manager' => [
        'mapping_classes_paths' => [
            __DIR__ . '/../src/Entity',
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
                                __DIR__ . '/../view/admin/',
                                __DIR__ . '/../view/public/',

        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
             'type' => 'gettext',
             'base_dir' => __DIR__ . '/../language',
             'pattern' => '%s.mo',
             'text_domain' => null,
            ],
        ],
    ],
];
