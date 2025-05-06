<?php

namespace App\Repository\User;

use App\Models\User;
use App\Repository\Base\BaseRepository;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model, ResponseServiceInterface $responseService, AuditLogServiceInterface $auditLogService)
    {
        parent::__construct($model, $responseService, $auditLogService);
    }

    public function getUserByEmail(string $email, $relation = null): User
    {
        $user = User::where('email', $email)
            ->when($relation, function (Builder $query) use ($relation) {
                $query->with($relation);
            })->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user_not_found' => 'User not found',
            ]);
        }

        return $user;
    }

    public function create(array $attributes): JsonResponse
    {
        $relations = request()->input('relations');

        $attributes['password'] = Hash::make('secret');

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
