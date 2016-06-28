<?php
namespace GuestUser\Service;

use GuestUser\Permissions\Acl as GuestUserAcl;
use Omeka\Permissions\Assertion\AssertionNegation;
use Omeka\Permissions\Assertion\HasSitePermissionAssertion;
use Omeka\Permissions\Assertion\SiteIsPublicAssertion;
use Omeka\Permissions\Assertion\IsSelfAssertion;
use Omeka\Permissions\Assertion\OwnsEntityAssertion;
use Omeka\Permissions\Assertion\UserIsAdminAssertion;
use Zend\Permissions\Acl\Assertion\AssertionAggregate;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Access control list factory.
 */
class AclFactory extends \Omeka\Service\AclFactory
{

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $acl = new GuestUserAcl;

        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $acl->setAuthenticationService($auth);

        $this->addGuestRoles($acl, $serviceLocator);
        $this->addResources($acl, $serviceLocator);

        $status = $serviceLocator->get('Omeka\Status');
        if (!$status->isInstalled()
            || ($status->needsVersionUpdate() && $status->needsMigration())
        ) {
            $acl->allow();
        } else {
            $this->addRules($acl, $serviceLocator);
        }

        return $acl;
    }


    protected function addGuestRoles($acl,$serviceLocator)
    {
        parent::addRoles($acl,$serviceLocator);
        $acl->addRole('guest');
        $acl->addRoleLabel('guest', 'Guest');
    }
}
