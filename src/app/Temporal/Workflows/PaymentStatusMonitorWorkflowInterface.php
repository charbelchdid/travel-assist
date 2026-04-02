<?php

namespace App\Temporal\Workflows;

use Temporal\DataConverter\Type;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface PaymentStatusMonitorWorkflowInterface
{
    #[ReturnType(name: Type::TYPE_ARRAY)]
    #[WorkflowMethod(name: 'PaymentStatusMonitorWorkflow')]
    public function run(string $paymentUuid, int $pollSeconds = 10, int $maxAttempts = 30): mixed;

    /**
     * Signal to indicate a provider webhook was received (payload is optional, used for illustration).
     *
     * @param array<string, mixed> $payload
     */
    #[SignalMethod(name: 'webhookReceived')]
    public function webhookReceived(array $payload): void;

    #[QueryMethod(name: 'status')]
    public function status(): array;
}

