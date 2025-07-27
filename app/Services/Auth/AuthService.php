<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repository\User\UserRepositoryInterface;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    private $userRepository;
    private $responseService;
    private $auditLogService;

    public function __construct(UserRepositoryInterface $userRepository, ResponseServiceInterface $responseService, AuditLogServiceInterface $auditLogService)
    {
        $this->userRepository = $userRepository;
        $this->responseService = $responseService;
        $this->auditLogService = $auditLogService;
    }

    public function headers()
    {
        return [
            'Authorization'                    => request()->header('Authorization'),
            'Content-Type'                     => 'application/json',
            'Accept'                           => 'application/json',
            'Access-Control-Allow-Credentials' => true,
        ];
    }

    public function login(array $params, bool $isGoogleAuthenticated = false): array
    {
        $user = $this->userRepository->getUserByEmail($params['email']);

        // if (!$user->is_admin) {
        //     throw ValidationException::withMessages(['login_error' => 'User must be an admin to login.']);
        // }

        if (Hash::check(Arr::get($params, 'password'), $user->password)) {
            $this->auditLogService->loginLog('login', ['email' => $params['email']]);

            return [
                'token' => $user->createToken('UserLogin')->plainTextToken,
                'user'  => $user,
            ];
        }

        throw ValidationException::withMessages([
            'invalid_user_name_or_password' => "Invalid E-mail or Password",
        ]);
    }

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();

        return $this->responseService->resolveResponse('Logout Successful', null);
    }

    public function authUser()
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return $this->responseService->resolveResponse('Unauthenticated', null, 401);
        }

        $user = User::withoutGlobalScopes()->find($authUser->id)->load('role.permissions');

        return $this->responseService->resolveResponse('Authenticated User', $user);
    }
}
