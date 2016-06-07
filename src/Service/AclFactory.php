<?php
namespace GuestUser\Service;

use Omeka\Permissions\Acl;
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

    /**
     * Add ACL roles.
     *
     * @param Acl $acl
     * @param ServiceLocatorInterface $serviceLocator
     */
    protected function addRoles(Acl $acl, ServiceLocatorInterface $serviceLocator)
    {
        parent::addRoles($acl,$serviceLocator);
        $acl->addRole('guest');
    }




}
