<?php
namespace Guest\Site\Navigation\Link;

use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\ErrorStore;

class Register implements LinkInterface
{
    public function getName()
    {
        return 'Guest Register'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/label';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['label'])) {
            $errorStore->addError('o:navigation', sprintf('Invalid navigation: link without label (%s)', $this->getName())); // @translate
            return false;
        }
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return isset($data['label']) && trim($data['label']) !== ''
            ? $data['label']
            : 'Register'; // @translate
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'],
            'route' => 'site/guest/anonymous',
            'class' => 'register-link',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => \Guest\Controller\Site\AnonymousController::class,
                'action' => 'register',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => isset($data['label']) ? trim($data['label']) : '',
        ];
    }
}
