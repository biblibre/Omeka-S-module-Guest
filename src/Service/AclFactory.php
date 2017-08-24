<?php
namespace GuestUser\Service;

use GuestUser\Permissions\Acl as GuestUserAcl;
use Interop\Container\ContainerInterface;

/**
 * Access control list factory.
 */
class AclFactory extends \Omeka\Service\AclFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $acl = new GuestUserAcl;

        $auth = $services->get('Omeka\AuthenticationService');
        $acl->setAuthenticationService($auth);

        $this->addGuestRoles($acl, $services);
        $this->addResources($acl, $services);

        $status = $services->get('Omeka\Status');
        if (!$status->isInstalled()
            || ($status->needsVersionUpdate() && $status->needsMigration())
        ) {
            $acl->allow();
        } else {
            $this->addRules($acl, $services);
        }

        return $acl;
    }

    protected function addGuestRoles($acl, $serviceLocator)
    {
        parent::addRoles($acl, $serviceLocator);
        $acl->addRole('guest');
        $acl->addRoleLabel('guest', 'Guest');
    }
}
