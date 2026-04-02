<?php

namespace App\Temporal\Workflows;

use App\Temporal\Activities\GreetingActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;

final class GreetingWorkflow implements GreetingWorkflowInterface
{
    /**
     * Activity stub proxy. Don't type as GreetingActivityInterface:
     * Temporal returns a proxy object that doesn't implement the interface at runtime.
     *
     * @var object
     */
    private object $activity;

    public function __construct()
    {
        $this->activity = Workflow::newActivityStub(
            GreetingActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout('5 seconds')
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withMaximumAttempts(3)
                )
        );
    }

    #[ReturnType(name: ReturnType::TYPE_STRING)]
    public function run(string $name): \Generator
    {
        /** @var string $result */
        $result = yield $this->activity->sayHello($name);

        return $result;
    }
}


