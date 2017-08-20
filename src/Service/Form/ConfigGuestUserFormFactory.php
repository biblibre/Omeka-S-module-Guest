<?php
namespace GuestUser\Service\Form;

use GuestUser\Form\ConfigGuestUserForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigGuestUserFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $translator = $services->get('MvcTranslator');
        $form = new ConfigGuestUserForm;
        $form->setSettings($services->get('Omeka\Settings'));
        $form->setTranslator($translator);
        $form->init();

        return $form;
    }
}
