<?php

namespace App\Temporal\Workflows;

use Temporal\DataConverter\Type;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface SagaWorkflowInterface
{
    #[ReturnType(name: Type::TYPE_ARRAY)]
    #[WorkflowMethod(name: 'SagaWorkflow')]
    public function run(string $orderId, int $amountCents): mixed;
}


