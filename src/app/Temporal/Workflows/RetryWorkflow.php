<?php

namespace App\Temporal\Workflows;

use App\Temporal\Activities\FlakyActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;

/**
 * Demonstrates Activity retries with RetryOptions.
 */
final class RetryWorkflow implements RetryWorkflowInterface
{
    /**
     * @var object Activity stub proxy (doesn't implement the interface at runtime)
     */
    private object $activity;

    public function __construct()
    {
        $this->activity = Workflow::newActivityStub(
            FlakyActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout('5 seconds')
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withInitialInterval('1 second')
                        ->withMaximumAttempts(10)
                )
        );
    }

    #[ReturnType(name: ReturnType::TYPE_STRING)]
    public function run(int $succeedOnAttempt = 3): \Generator
    {
        /** @var string $result */
        $result = yield $this->activity->run($succeedOnAttempt);

        return $result;
    }
}


