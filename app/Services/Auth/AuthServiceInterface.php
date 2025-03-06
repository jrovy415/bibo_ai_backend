<?php

namespace App\Services\Auth;

interface AuthServiceInterface
{
    public function login(array $params);
    
    public function verifyClientIdAndSecret();
    
    public function logout();
}
