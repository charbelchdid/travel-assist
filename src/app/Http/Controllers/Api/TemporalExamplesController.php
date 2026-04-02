<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Temporal\Workflows\ContinueAsNewWorkflowInterface;
use App\Temporal\Workflows\GreetingWorkflowInterface;
use App\Temporal\Workflows\OrderWorkflowInterface;
use App\Temporal\Workflows\PaymentStatusMonitorWorkflowInterface;
use App\Temporal\Workflows\ParentWorkflowInterface;
use App\Temporal\Workflows\RetryWorkflowInterface;
use App\Temporal\Workflows\SagaWorkflowInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\Type;

class TemporalExamplesController extends Controller
{
    public function __construct(private readonly WorkflowClient $workflowClient)
    {
    }

    public function startGreeting(Request $request)
    {
        $name = (string) $request->input('name', 'world');
        $workflowId = (string) $request->input('workflow_id', 'greeting-' . Str::uuid()->toString());

        $options = WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue((string) config('temporal.task_queue'));

        $workflow = $this->workflowClient->newWorkflowStub(GreetingWorkflowInterface::class, $options);
        $run = $this->workflowClient->start($workflow, $name);

        return ApiResponse::success([
            'message' => 'Greeting workflow started',
            'data' => [
                'workflow_id' => $run->getExecution()->getID(),
                'run_id' => $run->getExecution()->getRunID(),
            ],
        ], 202);
    }

    public function startOrder(Request $request)
    {
        $orderId = (string) $request->input('order_id', 'order-' . Str::uuid()->toString());
        $workflowId = (string) $request->input('workflow_id', 'order-' . Str::uuid()->toString());

        $options = WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue((string) config('temporal.task_queue'));

        $workflow = $this->workflowClient->newWorkflowStub(OrderWorkflowInterface::class, $options);
        $run = $this->workflowClient->start($workflow, $orderId);

        return ApiResponse::success([
            'message' => 'Order workflow started (send signals to add items / cancel, query status anytime)',
            'data' => [
                'workflow_id' => $run->getExecution()->getID(),
                'run_id' => $run->getExecution()->getRunID(),
                'order_id' => $orderId,
            ],
        ], 202);
    }

    public function orderAddItem(string $workflowId, Request $request)
    {
        $item = (string) $request->input('item', '');

        try {
            $stub = $this->workflowClient->newRunningWorkflowStub(OrderWorkflowInterface::class, $workflowId);
            $stub->addItem($item);

            return ApiResponse::success([
                'message' => 'Signal sent: addItem',
                'data' => ['workflow_id' => $workflowId, 'item' => $item],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to signal workflow', 400, ['error' => $e->getMessage()]);
        }
    }

    public function orderCancel(string $workflowId)
    {
        try {
            $stub = $this->workflowClient->newRunningWorkflowStub(OrderWorkflowInterface::class, $workflowId);
            $stub->cancel();

            return ApiResponse::success([
                'message' => 'Signal sent: cancel',
                'data' => ['workflow_id' => $workflowId],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to signal workflow', 400, ['error' => $e->getMessage()]);
        }
    }

    public function orderStatus(string $workflowId)
    {
        try {
            $stub = $this->workflowClient->newUntypedRunningWorkflowStub(
                workflowID: $workflowId,
                workflowType: 'OrderWorkflow',
            );

            $values = $stub->query('status');
            $status = $values?->getValue(0, Type::TYPE_ARRAY);

            return ApiResponse::success([
                'message' => 'Order status',
                'data' => ['workflow_id' => $workflowId, 'status' => $status],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to query workflow', 400, [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    public function startChildWorkflow(Request $request)
    {
        $value = (int) $request->input('value', 41);
        $workflowId = (string) $request->input('workflow_id', 'parent-' . Str::uuid()->toString());

        $options = WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue((string) config('temporal.task_queue'));

        $workflow = $this->workflowClient->newWorkflowStub(ParentWorkflowInterface::class, $options);
        $run = $this->workflowClient->start($workflow, $value);

        return ApiResponse::success([
            'message' => 'Parent workflow started (spawns a child workflow)',
            'data' => [
                'workflow_id' => $run->getExecution()->getID(),
                'run_id' => $run->getExecution()->getRunID(),
                'value' => $value,
            ],
        ], 202);
    }

    public function startRetryWorkflow(Request $request)
    {
        $succeedOnAttempt = (int) $request->input('succeed_on_attempt', 3);
        $workflowId = (string) $request->input('workflow_id', 'retry-' . Str::uuid()->toString());

        $options = WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue((string) config('temporal.task_queue'));

        $workflow = $this->workflowClient->newWorkflowStub(RetryWorkflowInterface::class, $options);
        $run = $this->workflowClient->start($workflow, $succeedOnAttempt);

        return ApiResponse::success([
            'message' => 'Retry workflow started (activity will retry until it succeeds)',
            'data' => [
                'workflow_id' => $run->getExecution()->getID(),
                'run_id' => $run->getExecution()->getRunID(),
                'succeed_on_attempt' => $succeedOnAttempt,
            ],
        ], 202);
    }

    public function startContinueAsNewWorkflow(Request $request)
    {
        $items = $request->input('items', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        $batchSize = (int) $request->input('batch_size', 5);
        $workflowId = (string) $request->input('workflow_id', 'continue-' . Str::uuid()->toString());

        $options = WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue((string) config('temporal.task_queue'));

        $workflow = $this->workflowClient->newWorkflowStub(ContinueAsNewWorkflowInterface::class, $options);
        $run = $this->workflowClient->start($workflow, $items, $batchSize);

        return ApiResponse::success([
            'message' => 'Continue-as-new workflow started',
            'data' => [
                'workflow_id' => $run->getExecution()->getID(),
                'run_id' => $run->getExecution()->getRunID(),
                'items_count' => is_array($items) ? count($items) : null,
                'batch_size' => $batchSize,
            ],
        ], 202);
    }

    public function startSagaWorkflow(Request $request)
    {
        $orderId = (string) $request->input('order_id', 'order-' . Str::uuid()->toString());
        $amountCents = (int) $request->input('amount_cents', 50);
        $workflowId = (string) $request->input('workflow_id', 'saga-' . Str::uuid()->toString());

        $options = WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue((string) config('temporal.task_queue'));

        $workflow = $this->workflowClient->newWorkflowStub(SagaWorkflowInterface::class, $options);
        $run = $this->workflowClient->start($workflow, $orderId, $amountCents);

        return ApiResponse::success([
            'message' => 'Saga workflow started (will run compensations on failure)',
            'data' => [
                'workflow_id' => $run->getExecution()->getID(),
                'run_id' => $run->getExecution()->getRunID(),
                'order_id' => $orderId,
                'amount_cents' => $amountCents,
            ],
        ], 202);
    }

    public function startPaymentStatusMonitor(Request $request)
    {
        $paymentUuid = (string) $request->input('payment_uuid', '');
        $pollSeconds = (int) $request->input('poll_seconds', 10);
        $maxAttempts = (int) $request->input('max_attempts', 30);
        $workflowId = (string) $request->input('workflow_id', 'payment-monitor-' . Str::uuid()->toString());

        $options = WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue((string) config('temporal.task_queue'));

        $workflow = $this->workflowClient->newWorkflowStub(PaymentStatusMonitorWorkflowInterface::class, $options);
        $run = $this->workflowClient->start($workflow, $paymentUuid, $pollSeconds, $maxAttempts);

        return ApiResponse::success([
            'message' => 'Payment status monitor workflow started (polls ERP /payment/check-status and supports webhook signal)',
            'data' => [
                'workflow_id' => $run->getExecution()->getID(),
                'run_id' => $run->getExecution()->getRunID(),
                'payment_uuid' => $paymentUuid,
                'poll_seconds' => $pollSeconds,
                'max_attempts' => $maxAttempts,
            ],
        ], 202);
    }

    public function paymentStatusMonitorWebhook(string $workflowId, Request $request)
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->all();

            $stub = $this->workflowClient->newRunningWorkflowStub(PaymentStatusMonitorWorkflowInterface::class, $workflowId);
            $stub->webhookReceived($payload);

            return ApiResponse::success([
                'message' => 'Signal sent: webhookReceived',
                'data' => ['workflow_id' => $workflowId],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to signal workflow', 400, ['error' => $e->getMessage()]);
        }
    }

    public function paymentStatusMonitorStatus(string $workflowId)
    {
        try {
            $stub = $this->workflowClient->newUntypedRunningWorkflowStub(
                workflowID: $workflowId,
                workflowType: 'PaymentStatusMonitorWorkflow',
            );

            $values = $stub->query('status');
            $status = $values?->getValue(0, Type::TYPE_ARRAY);

            return ApiResponse::success([
                'message' => 'Payment monitor status',
                'data' => ['workflow_id' => $workflowId, 'status' => $status],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to query workflow', 400, [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}


