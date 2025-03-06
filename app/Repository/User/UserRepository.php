<?php

namespace App\Repository\User;

use App\Models\User;
use App\Repository\Base\BaseRepository;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model, ResponseServiceInterface $responseService, AuditLogServiceInterface $auditLogService)
    {
        parent::__construct($model, $responseService, $auditLogService);
    }
}
