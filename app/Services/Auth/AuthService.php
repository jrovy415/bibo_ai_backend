<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repository\Admin\User\UserRepositoryInterface;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    private mixed $endpoint;

    public function __construct(public UserRepositoryInterface $userRepository, public ResponseServiceInterface $responseService, public AuditLogServiceInterface $auditLogService)
    {
        $this->endpoint = config('aurish.auth_svc_url');
    }

    public function login(array $params, bool $isGoogleAuthenticated = false): array
    {
        $status = $this->verifyClientIdAndSecret();

        if ($status->failed()) {
            throw ValidationException::withMessages([
                'invalid_client' => 'Client secret/ID error',
            ]);
        }

        $user = $this->userRepository->getUserByEmail($params['email'], 'role.permissions');

        if ($user->role?->is_admin !== 1) {
            throw ValidationException::withMessages(['login_error' => 'User must be an admin to login.']);
        }

        if ($user->account_status === User::STATUS_INACTIVE) {
            throw ValidationException::withMessages(['user_inactive' => 'User\'s account is not yet activated.']);
        }

        if (Hash::check(Arr::get($params, 'password'), $user->password)) {
            $this->auditLogService->loginLog('login', ['email' => $params['email']]);

            return [
                'token' => $user->createToken('AboitizAURISH')->plainTextToken,
                'user'  => $user,
            ];
        }

        throw ValidationException::withMessages([
            'invalid_user_name_or_password' => "Invalid E-mail or Password",
        ]);
    }

    public function lguLogin(array $params, bool $isGoogleAuthenticated = false): array
    {
        $status = $this->verifyClientIdAndSecret();

        if ($status->failed()) {
            throw ValidationException::withMessages([
                'invalid_client' => 'Client secret/ID error',
            ]);
        }

        $user = $this->userRepository->getUserByEmail($params['email'], 'role.permissions');

        if ($user->account_status === User::STATUS_INACTIVE) {
            throw ValidationException::withMessages(['user_inactive' => 'User\'s account is not yet activated.']);
        }

        if (Hash::check(Arr::get($params, 'password'), $user->password)) {
            $this->auditLogService->loginLog('login', ['email' => $params['email']]);

            return [
                'token' => $user->createToken('AboitizAURISH')->plainTextToken,
                'user'  => $user,
            ];
        }

        throw ValidationException::withMessages([
            'invalid_user_name_or_password' => "Invalid E-mail or Password",
        ]);
    }

    public function verifyClientIdAndSecret()
    {
        return Http::withHeaders($this->headers())->post($this->endpoint.'/api/v1/clients/verify', [
            'client_id'     => config('aurish.client_id'),
            'client_secret' => config('aurish.client_secret'),
        ]);
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

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();

        return $this->responseService->resolveResponse('Logout Successful', null);
    }

    public function googleLogin($data, $isAdminLogin = false)
    {
        $status = $this->verifyClientIdAndSecret();

        if ($status->failed()) {
            throw ValidationException::withMessages([
                'invalid_client' => 'Client secret/ID error',
            ]);
        }

        $client = new \Google_Client([
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        $response = $client->verifyIdToken($data['credential']);

        $user = $this->userRepository->getUserByEmail($response['email'], 'role.permissions');

        if ( !$user->role?->is_admin && $isAdminLogin) {
            throw ValidationException::withMessages(['login_error' => 'User must be an admin to login.']);
        }

        if ($user->account_status === User::STATUS_INACTIVE) {
            throw ValidationException::withMessages(['user_inactive' => 'User\'s account is not yet activated.']);
        }

        $this->auditLogService->loginLog('login', ['login' => 'Google Signin.', 'email' => $response['email']]);

        return [
            'token' => $user->createToken('AboitizAURISH')->plainTextToken,
            'user'  => $user,
        ];
    }
}
