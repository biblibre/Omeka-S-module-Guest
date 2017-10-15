<?php
namespace GuestUser\Permissions;

class Acl extends \Omeka\Permissions\Acl
{
    const ROLE_GUEST = 'guest';

    public function addRoleLabel($role, $label)
    {
        if ($role instanceof Role\RoleInterface) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = $role;
        }

        $this->roleLabels[$roleId] = $label;
    }
}
