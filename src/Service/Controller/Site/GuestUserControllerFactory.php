<?php
namespace GuestUser\Service\Controller\Site;

use GuestUser\Controller\Site\GuestUserController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class GuestUserControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $authenticationService = $services->get('Omeka\AuthenticationService');
        $entityManager = $services->get('Omeka\EntityManager');

        $controller = new GuestUserController;
        $controller->setAuthenticationService($authenticationService);
        $controller->setEntityManager($entityManager);
        return $controller;
    }
}
