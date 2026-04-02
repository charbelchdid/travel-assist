<?php

namespace App\Temporal\Workflows;

use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RetryWorkflowInterface
{
    #[ReturnType(name: ReturnType::TYPE_STRING)]
    #[WorkflowMethod(name: 'RetryWorkflow')]
    public function run(int $succeedOnAttempt = 3): mixed;
}


