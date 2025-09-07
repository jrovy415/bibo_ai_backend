<?php

namespace App\Services\Auth;

interface AuthServiceInterface
{
    public function login(array $params);

    public function logout();

    public function authUser();
}
