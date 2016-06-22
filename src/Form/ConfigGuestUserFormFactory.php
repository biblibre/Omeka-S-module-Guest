<?php
namespace GuestUser\Form;

use Search\Form\Admin\SearchPageForm;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ConfigGuestUserFormFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $elements)
    {
        $serviceLocator = $elements->getServiceLocator();
        $translator = $serviceLocator->get('MvcTranslator');
        xdebug_break();
        $form = new ConfigGuestUserForm;
        $form->setSettings($serviceLocator->get('Omeka\Settings'));
        $form->setTranslator($translator);
        $form->init();


        return $form;
    }
}
