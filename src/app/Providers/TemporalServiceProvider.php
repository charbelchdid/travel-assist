<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;

class TemporalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/temporal.php'),
            'temporal'
        );

        $this->app->singleton(ServiceClient::class, function () {
            /** @var string $address */
            $address = config('temporal.address');
            return ServiceClient::create($address);
        });

        $this->app->singleton(WorkflowClient::class, function ($app) {
            /** @var string $namespace */
            $namespace = config('temporal.namespace');

            $clientOptions = (new ClientOptions())->withNamespace($namespace);

            /** @var ServiceClient $serviceClient */
            $serviceClient = $app->make(ServiceClient::class);

            return WorkflowClient::create($serviceClient, $clientOptions);
        });
    }
}


