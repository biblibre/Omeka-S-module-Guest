<?php
namespace GuestUser\Site\Navigation\Link;

use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\ErrorStore;

class Logout implements LinkInterface
{
    public function getName()
    {
        return 'Logout'; // @translate
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return 'Logout'; // @translate
    }

    public function getFormTemplate()
    {
        return 'navigation-link-form/login';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['label'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: logout link missing label');
            return false;
        }
        return true;
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'],
            'route' => 'site/resource',
                'class' => 'logoutlink',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => 'guestuser',
                'action' => 'logout',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        $label = isset($data['label']) ? $data['label'] : $sitePage->title();
        return [
            'label' => $label,
        ];
    }
}
