<?php

namespace App\Repository\RolePermission;

interface RolePermissionRepositoryInterface
{
    public function update(string $roleId, array $attributes);
}
