<?php
namespace GuestUser\Permissions;

use Zend\Permissions\Acl\Role\RoleInterface;

class Acl extends \Omeka\Permissions\Acl
{
    const ROLE_GUEST = 'guest';

    public function addRoleLabel($role, $label)
    {
        if ($role instanceof RoleInterface) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = $role;
        }

        $this->roleLabels[$roleId] = $label;
    }
}
