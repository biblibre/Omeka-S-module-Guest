<?php
namespace GuestUser\Service\Controller;

use GuestUser\Controller\GuestUserController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class GuestUserControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
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
