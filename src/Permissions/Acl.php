<?php
namespace GuestUser\Permissions;

use Zend\Authentication\AuthenticationServiceInterface;
use Zend\Permissions\Acl\Acl as ZendAcl;

class Acl extends \Omeka\Permissions\Acl
{

    public function addRoleLabel($role,$label)
    {
        if ($role instanceof Role\RoleInterface) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = $role;
        }

        $this->roleLabels[$roleId] = $label;
    }


}