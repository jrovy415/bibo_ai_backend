<?php

namespace App\Providers;

use App\Repository\Base\BaseRepository;
use App\Repository\Base\BaseRepositoryInterface;
use App\Repository\User\UserRepository;
use App\Repository\User\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BaseRepositoryInterface::class, BaseRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void {}
}
