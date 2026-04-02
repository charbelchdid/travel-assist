<?php

namespace App\Temporal\Workflows;

use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface ChildWorkflowInterface
{
    #[ReturnType(name: ReturnType::TYPE_INT)]
    #[WorkflowMethod(name: 'ChildWorkflow')]
    public function run(int $value): mixed;
}


