<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthServiceInterface
{
    public function login(array $params);

    public function logout();

    public function authUser();
}
