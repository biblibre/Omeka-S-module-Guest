 <?php
return [
        'forms' => [
        'invokables' => [
                         'GuestUser\Form\ConfigGuestUserForm' => 'GuestUser\Form\ConfigRepertoryForm',
        ],

        ],
        'controllers' => [
        'invokables' => [
                         'ArchiveRepertory\Controller\DownloadController' => 'ArchiveRepertory\Controller\DownloadController',
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
                                __DIR__ . '/../view/admin/',

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
