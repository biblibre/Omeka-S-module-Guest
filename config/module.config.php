<?php
namespace GuestUser;

return [
    'view_helpers' => [
        'invokables' => [
            'guestUserWidget' => View\Helper\GuestUserWidget::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            'GuestUser\Form\ConfigForm' => Service\Form\ConfigGuestUserFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'GuestUser\Controller\GuestUser' => Service\Controller\GuestUserControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\AuthenticationService' => Service\AuthenticationServiceFactory::class,
            'Omeka\Acl' => Service\AclFactory::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'register' => Site\Navigation\Link\Register::class,
            'login' => Site\Navigation\Link\Login::class,
            'logout' => Site\Navigation\Link\Logout::class,
        ],
    ],
    'navigation' => [
        'site' => [
            [
                'label' => 'User information',
                'route' => '/guestuser/login',
                'resource' => Controller\GuestUserController::class,
                'visible' => true,
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
                            ],
                        ],
                    ],
                ],
            ],
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
