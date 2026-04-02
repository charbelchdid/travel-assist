<?php

namespace App\Temporal\Workflows;

use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface ParentWorkflowInterface
{
    #[ReturnType(name: ReturnType::TYPE_INT)]
    #[WorkflowMethod(name: 'ParentWorkflow')]
    public function run(int $value): mixed;
}


