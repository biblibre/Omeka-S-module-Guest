<?php
namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\CreateGuestToken;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CreateGuestTokenFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new CreateGuestToken(
            $services->get('Omeka\EntityManager')
        );
    }
}
