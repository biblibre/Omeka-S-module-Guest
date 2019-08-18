<?php
namespace Guest\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class LoginBoard implements LinkInterface
{
    public function getName()
    {
        return 'Guest Login / My board'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/login-board';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['label-login'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: login link missing label'); // @translate
            return false;
        }
        if (!isset($data['label-board'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: login link missing label'); // @translate
            return false;
        }
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        if ($site->getServiceLocator()->get('Omeka\AuthenticationService')->hasIdentity()) {
            return isset($data['label-board']) && trim($data['label-board']) !== ''
                ? $data['label-board']
                : 'My board'; // @translate
        }

        return isset($data['label-login']) && trim($data['label-login']) !== ''
            ? $data['label-login']
            : 'Login'; // @translate
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        if ($site->getServiceLocator()->get('Omeka\AuthenticationService')->hasIdentity()) {
            return [
                'label' => $data['label-board'],
                'route' => 'site/guest',
                'class' => 'guest-board-link',
                'params' => [
                    'site-slug' => $site->slug(),
                    'controller' => \Guest\Controller\Site\GuestController::class,
                    'action' => 'me',
                ],
            ];
        }

        return [
            'label' => $data['label-login'],
            'route' => 'site/guest/anonymous',
            'class' => 'login-link',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => \Guest\Controller\Site\AnonymousController::class,
                'action' => 'login',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label-login' => isset($data['label-login']) ? trim($data['label-login']) : '',
            'label-board' => isset($data['label-board']) ? trim($data['label-board']) : '',
        ];
    }
}
