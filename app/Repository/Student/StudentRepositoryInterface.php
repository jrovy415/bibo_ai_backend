<?php

namespace App\Repository\Student;

use App\Repository\Base\BaseRepositoryInterface;
use Illuminate\Http\JsonResponse;

interface StudentRepositoryInterface extends BaseRepositoryInterface
{
    public function login(array $attributes): array;

    public function logout(): JsonResponse;
}
