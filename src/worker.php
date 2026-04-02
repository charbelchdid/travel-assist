<?php

declare(strict_types=1);

use App\Temporal\Activities\FlakyActivity;
use App\Temporal\Activities\GreetingActivity;
use App\Temporal\Activities\ErpPaymentActivity;
use App\Temporal\Activities\OrderActivity;
use App\Temporal\Activities\PaymentActivity;
use App\Temporal\Workflows\ChildWorkflow;
use App\Temporal\Workflows\ContinueAsNewWorkflow;
use App\Temporal\Workflows\GreetingWorkflow;
use App\Temporal\Workflows\OrderWorkflow;
use App\Temporal\Workflows\PaymentStatusMonitorWorkflow;
use App\Temporal\Workflows\ParentWorkflow;
use App\Temporal\Workflows\RetryWorkflow;
use App\Temporal\Workflows\SagaWorkflow;
use Temporal\Worker\FeatureFlags;
use Temporal\WorkerFactory;

require __DIR__ . '/vendor/autoload.php';

// Boot Laravel so config(), env(), logging, etc. are available inside Activities.
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Feature flags should be set before SDK initialization.
FeatureFlags::$cancelAbandonedChildWorkflows = true;

$factory = WorkerFactory::create();

/** @var string $taskQueue */
$taskQueue = config('temporal.task_queue', 'laravel-template');

$worker = $factory->newWorker($taskQueue);

// Workflows
$worker->registerWorkflowTypes(
    GreetingWorkflow::class,
    OrderWorkflow::class,
    RetryWorkflow::class,
    ChildWorkflow::class,
    ParentWorkflow::class,
    ContinueAsNewWorkflow::class,
    SagaWorkflow::class,
    PaymentStatusMonitorWorkflow::class,
);

// Activities
$worker->registerActivityImplementations(
    new GreetingActivity(),
    new OrderActivity(),
    new FlakyActivity(),
    new PaymentActivity(),
    new ErpPaymentActivity(),
);

$factory->run();


