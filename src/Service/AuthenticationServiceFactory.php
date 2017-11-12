<?php
namespace GuestUser\Service;

use GuestUser\Authentication\Adapter\PasswordAdapter;
use GuestUser\Entity\GuestUserToken;
use Omeka\Authentication\Adapter\KeyAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;
use Interop\Container\ContainerInterface;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\Callback;
use Zend\Authentication\Storage\NonPersistent;
use Zend\Authentication\Storage\Session;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Authentication service factory.
 */
class AuthenticationServiceFactory implements FactoryInterface
{
    /**
     * Create the authentication service.
     *
     * @param ContainerInterface $services
     * @return ApiManager
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $status = $services->get('Omeka\Status');

        // Skip auth retrieval entirely if we're installing or migrating.
        if (!$status->isInstalled() ||
            ($status->needsVersionUpdate() && $status->needsMigration())
        ) {
            $storage = new NonPersistent;
            $adapter = new Callback(function () {
                return null;
            });
        } else {
            $userRepository = $entityManager->getRepository(User::class);
            if ($status->isApiRequest()) {
                // Authenticate using key for API requests.
                $keyRepository = $entityManager->getRepository(ApiKey::class);
                $storage = new DoctrineWrapper(new NonPersistent, $userRepository);
                $adapter = new KeyAdapter($keyRepository, $entityManager);
            } else {
                // Authenticate using user/password for all other requests.
                $storage = new DoctrineWrapper(new Session, $userRepository);
                $adapter = new PasswordAdapter($userRepository);
                $adapter->setTokenRepository($entityManager->getRepository(GuestUserToken::class));
            }
        }

        $authService = new AuthenticationService($storage, $adapter);
        return $authService;
    }
}
