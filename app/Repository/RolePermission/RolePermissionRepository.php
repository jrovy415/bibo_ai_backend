<?php

namespace App\Repository\RolePermission;

use App\Models\Role;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;

class RolePermissionRepository implements RolePermissionRepositoryInterface
{
    protected $model;
    protected $responseService;
    protected $auditLogService;

    public function __construct(Role $model, ResponseServiceInterface $responseService, AuditLogServiceInterface $auditLogService)
    {
        $this->model = $model;
        $this->responseService = $responseService;
        $this->auditLogService = $auditLogService;
    }

    public function update(string $roleId, array $attributes)
    {
        $role = $this->model->findOrFail($roleId);

        $role->permissions()->sync($attributes['permissions']);

        $role->touch();

        $this->auditLogService->insertLog($this->model, 'update', $attributes);

        return $this->responseService->updateResponse(
            'Role Permissions',
            $role->with('permissions')->find($roleId)
        );
    }
}
