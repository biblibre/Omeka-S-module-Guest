<?php
namespace Guest;

return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'guestWidget' => View\Helper\GuestWidget::class,
            'userBar' => View\Helper\UserBar::class,
            // Required to manage PsrMessage.
            'messages' => View\Helper\Messages::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\AcceptTermsForm::class => Form\AcceptTermsForm::class,
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\EmailForm::class => Form\EmailForm::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Site\AnonymousController::class => Service\Controller\Site\AnonymousControllerFactory::class,
            Controller\Site\GuestController::class => Service\Controller\Site\GuestControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'createGuestToken' => Service\ControllerPlugin\CreateGuestTokenFactory::class,
            'sendEmail' => Service\ControllerPlugin\SendEmailFactory::class,
            'userSites' => Service\ControllerPlugin\UserSitesFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\AuthenticationService' => Service\AuthenticationServiceFactory::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'login' => Site\Navigation\Link\Login::class,
            'loginBoard' => Site\Navigation\Link\LoginBoard::class,
            'logout' => Site\Navigation\Link\Logout::class,
            'register' => Site\Navigation\Link\Register::class,
        ],
    ],
    'navigation' => [
        'site' => [
            [
                'label' => 'User information', // @translate
                'route' => 'site/guest',
                'controller' => Controller\Site\GuestController::class,
                'action' => 'me',
                'useRouteMatch' => true,
                'visible' => false,
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'guest' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/guest',
                            'defaults' => [
                                '__NAMESPACE__' => 'Guest\Controller\Site',
                                'controller' => Controller\Site\GuestController::class,
                                'action' => 'me',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'anonymous' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        // "confirm" must be after "confirm-email" because regex is ungreedy.
                                        'action' => 'login|confirm-email|confirm|forgot-password|stale-token|auth-error|register',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Guest\Controller\Site',
                                        'controller' => Controller\Site\AnonymousController::class,
                                        'controller' => 'AnonymousController',
                                        'action' => 'login',
                                    ],
                                ],
                            ],
                            'guest' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => 'me|logout|update-account|update-email|accept-terms',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Guest\Controller\Site',
                                        'controller' => Controller\Site\GuestController::class,
                                        'action' => 'me',
                                    ],
                                ],
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
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'guest' => [
        'config' => [
            'guest_open' => 'moderate',
            'guest_notify_register' => [],
            'guest_recaptcha' => false,
            'guest_login_text' => 'Login', // @translate
            'guest_register_text' => 'Register', // @translate
            'guest_dashboard_label' => 'My dashboard', // @translate
            'guest_capabilities' => '',
            // From Omeka classic, but not used.
            // TODO Remove option "guest_short_capabilities" or implement it.
            'guest_short_capabilities' => '',
            'guest_message_confirm_email' => '<p>Hi {user_name},</p>
<p>You have registered for an account on {main_title} / {site_title} ({site_url}).</p>
<p>Please confirm your registration by following this link: {token_url}.</p>
<p>If you did not request to join {main_title} please disregard this email.</p>', // @translate
            'guest_message_update_email' => '<p>Hi {user_name},</p>
<p>You have requested to update email on {main_title} / {site_title} ({site_url}).</p>
<p>Please confirm your email by following this link: {token_url}.</p>
<p>If you did not request to update your email on {main_title}, please disregard this email.</p>', // @translate
            'guest_terms_text' => 'I agree the terms and conditions.', // @translate
            'guest_terms_page' => 'terms-and-conditions',
            'guest_terms_redirect' => 'site',
            'guest_terms_request_regex' => '',
            'guest_terms_force_agree' => true,
        ],
        'user_settings' => [
            'guest_agreed_terms' => false,
        ],
    ],
];
