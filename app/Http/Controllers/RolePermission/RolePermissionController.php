<?php

namespace App\Http\Controllers\RolePermission;

use App\Http\Controllers\Controller;
use App\Repository\RolePermission\RolePermissionRepositoryInterface;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    private $modelRepository;

    public function __construct(RolePermissionRepositoryInterface $modelRepository)
    {
        $this->modelRepository = $modelRepository;
    }

    public function update(Request $request, string $roleId)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        return $this->modelRepository->update($roleId, $validated);
    }
}
