<?php

namespace App\Repository\User;

use App\Models\User;
use App\Repository\Base\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function getUserByEmail(string $email, $relation = null): User;
}
