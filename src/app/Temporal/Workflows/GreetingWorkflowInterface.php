<?php

namespace App\Temporal\Workflows;

use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface GreetingWorkflowInterface
{
    #[ReturnType(name: ReturnType::TYPE_STRING)]
    #[WorkflowMethod(name: 'GreetingWorkflow')]
    public function run(string $name): mixed;
}


