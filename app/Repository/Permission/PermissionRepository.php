<?php

namespace App\Repository\Permission;

use App\Models\Permission;
use App\Repository\Base\BaseRepository;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    public function __construct(Permission $model, ResponseServiceInterface $responseService, AuditLogServiceInterface $auditLogService)
    {
        parent::__construct($model, $responseService, $auditLogService);
    }

    public function create(array $attributes): JsonResponse
    {
        $relations = request()->input('relations');

        $attributes['slug'] = Str::slug($attributes['name']);

        $created = $this->model->create($attributes)->fresh();

        $this->auditLogService->insertLog($this->model, 'create', $attributes);

        if ($relations) {
            $relations = explode(',', $relations);

            $created->load($relations);
        }

        return $this->responseService->storeResponse(
            $this->model->model_name,
            $created
        );
    }
}
