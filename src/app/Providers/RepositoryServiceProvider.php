<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Repositories
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\ProductRepository;

// Services
use App\Services\Interfaces\AuthenticationServiceInterface;
use App\Services\AuthenticationService;
use App\Services\Interfaces\UserServiceInterface;
use App\Services\UserService;
use App\Services\Interfaces\ErpServiceInterface;
use App\Services\ErpService;
use App\Services\Interfaces\ProductServiceInterface;
use App\Services\ProductService;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interfaces to Implementations
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);

        // Bind Service Interfaces to Implementations
        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(ErpServiceInterface::class, ErpService::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
