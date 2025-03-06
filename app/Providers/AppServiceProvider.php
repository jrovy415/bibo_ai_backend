<?php

namespace App\Providers;

use App\Services\AuditLog\AuditLogService;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseService;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ResponseServiceInterface::class, ResponseService::class);
        $this->app->singleton(AuditLogServiceInterface::class, AuditLogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
