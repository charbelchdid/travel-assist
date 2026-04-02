<?php

namespace App\Temporal\Workflows;

use Temporal\DataConverter\Type;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface OrderWorkflowInterface
{
    #[ReturnType(name: Type::TYPE_ARRAY)]
    #[WorkflowMethod(name: 'OrderWorkflow')]
    public function run(string $orderId): mixed;

    #[SignalMethod(name: 'addItem')]
    public function addItem(string $item): void;

    #[SignalMethod(name: 'cancel')]
    public function cancel(): void;

    #[QueryMethod(name: 'status')]
    public function status(): array;
}


