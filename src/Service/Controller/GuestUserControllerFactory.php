<?php
namespace GuestUser\Service\Controller;

use GuestUser\Controller\GuestUserController;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class GuestUserControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllers)
    {
        $services = $controllers->getServiceLocator();
        $authenticationService = $services->get('Omeka\AuthenticationService');
        $entityManager = $services->get('Omeka\EntityManager');
        $logger = $services->get('Omeka\Logger');

        $controller = new GuestUserController;
        $controller->setAuthenticationService($authenticationService);
        $controller->setEntityManager($entityManager);
        $controller->setLogger($logger);

        return $controller;
    }
}
