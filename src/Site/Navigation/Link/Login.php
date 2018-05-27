<?php
namespace GuestUser\Site\Navigation\Link;

use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\ErrorStore;

class Login implements LinkInterface
{
    public function getName()
    {
        return 'Login'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/login';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['label'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: login link missing label');
            return false;
        }
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return isset($data['label']) && '' !== trim($data['label'])
            ? $data['label'] : $this->getName();
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'],
            'route' => 'site/resource',
            'class' => 'loginlink',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => 'guest-user',
                'action' => 'login',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'],
        ];
    }
}
