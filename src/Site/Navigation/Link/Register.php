<?php
namespace GuestUser\Site\Navigation\Link;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\ErrorStore;

class Register implements LinkInterface
{

    public function getName()
    {
        return 'Register'; // @translate
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return 'Register'; // @translate
    }

    public function getFormTemplate()
    {
        return 'navigation-link-form/register';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['label'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: register link missing label');
            return false;
        }

        return true;
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        return [
                'label' => $data['label'],
                'route' => 'site/resource',
                'class'=>'registerlink',
                'params' => [
                             'site-slug' => $site->slug(),
                             'controller' => 'guestuser',
                             'action' => 'register',
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
