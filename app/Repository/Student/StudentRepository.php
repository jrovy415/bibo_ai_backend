<?php

namespace App\Repository\Student;

use App\Models\Student;
use App\Repository\Base\BaseRepository;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;

class StudentRepository extends BaseRepository implements StudentRepositoryInterface
{
    public function __construct(Student $model, ResponseServiceInterface $responseService, AuditLogServiceInterface $auditLogService)
    {
        parent::__construct($model, $responseService, $auditLogService);
    }
}
