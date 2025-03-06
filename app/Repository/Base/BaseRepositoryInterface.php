<?php

namespace App\Repository\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

interface BaseRepositoryInterface
{
    public function getList(): JsonResponse;

    public function create(array $attributes): JsonResponse;

    public function find(string $id): JsonResponse;

    public function update(array $attributes, $id): JsonResponse;

    public function delete(string $id): JsonResponse;
}
