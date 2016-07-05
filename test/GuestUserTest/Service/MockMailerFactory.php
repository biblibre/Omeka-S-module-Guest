<?php

namespace GuestUserTest\Service;

use Zend\Mail\Transport\Factory as TransportFactory;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use GuestUserTest\Service\MockMailer;

class MockMailerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $transport = TransportFactory::create([]);
        $viewHelpers = $serviceLocator->get('ViewHelperManager');
        $entityManager = $serviceLocator->get('Omeka\EntityManager');

        return new MockMailer($transport, $viewHelpers, $entityManager, []);
    }
}
