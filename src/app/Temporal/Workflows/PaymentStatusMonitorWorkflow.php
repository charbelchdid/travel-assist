<?php

namespace App\Temporal\Workflows;

use App\Temporal\Activities\ErpPaymentActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\Type;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;

/**
 * Demonstrates:
 * - Using a real HTTP-backed service via an Activity (ErpService -> /payment/check-status)
 * - Timers for deterministic polling (no sleep())
 * - Signals + Query to observe progress
 *
 * Note: Workflow code is deterministic; all side-effects (HTTP) happen in Activities.
 */
final class PaymentStatusMonitorWorkflow implements PaymentStatusMonitorWorkflowInterface
{
    /**
     * @var object Activity stub proxy (doesn't implement the interface at runtime)
     */
    private object $erpPayment;

    private string $paymentUuid = '';

    private string $state = 'created';

    /** @var array<string, mixed>|null */
    private ?array $lastStatus = null;

    /** @var array<string, mixed>|null */
    private ?array $lastWebhookPayload = null;

    private int $attempts = 0;

    public function __construct()
    {
        $this->erpPayment = Workflow::newActivityStub(
            ErpPaymentActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout('20 seconds')
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withMaximumAttempts(3)
                )
        );
    }

    #[ReturnType(name: Type::TYPE_ARRAY)]
    public function run(string $paymentUuid, int $pollSeconds = 10, int $maxAttempts = 30): \Generator
    {
        $this->paymentUuid = $paymentUuid;
        $this->state = 'monitoring';

        $pollSeconds = max(1, $pollSeconds);
        $maxAttempts = max(1, $maxAttempts);

        while ($this->attempts < $maxAttempts) {
            $this->attempts++;

            // If we received a webhook signal, verify status immediately (illustration of signals).
            if ($this->lastWebhookPayload !== null) {
                $this->state = 'verifying_after_webhook';
                $this->lastWebhookPayload = null;
            } else {
                $this->state = 'waiting';
                yield Workflow::timer($pollSeconds . ' seconds');
                $this->state = 'polling';
            }

            try {
                /** @var array<string, mixed> $status */
                $status = yield $this->erpPayment->checkStatus($this->paymentUuid);
                $this->lastStatus = $status;
            } catch (\Throwable $e) {
                // Keep the workflow progressing even if ERP payment endpoint is blocked in some environments.
                // Record the error for observability and continue until maxAttempts.
                $this->state = 'poll_failed';
                $this->lastStatus = [
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            if ($this->isTerminal($status)) {
                $this->state = 'completed';
                return [
                    'state' => $this->state,
                    'attempts' => $this->attempts,
                    'payment_uuid' => $this->paymentUuid,
                    'status' => $this->lastStatus,
                ];
            }
        }

        $this->state = 'timed_out';
        return [
            'state' => $this->state,
            'attempts' => $this->attempts,
            'payment_uuid' => $this->paymentUuid,
            'status' => $this->lastStatus,
        ];
    }

    public function webhookReceived(array $payload): void
    {
        // Store payload only for observability; Workflow remains deterministic.
        $this->lastWebhookPayload = $payload;
    }

    public function status(): array
    {
        return [
            'state' => $this->state,
            'attempts' => $this->attempts,
            'payment_uuid' => $this->paymentUuid,
            'last_status' => $this->lastStatus,
            'webhook_payload_received' => $this->lastWebhookPayload !== null,
        ];
    }

    /**
     * @param array<string, mixed> $status
     */
    private function isTerminal(array $status): bool
    {
        $raw = $status['transactionStatus'] ?? $status['transaction_status'] ?? null;
        if (!is_string($raw)) {
            return false;
        }

        $v = strtoupper(trim($raw));

        return in_array($v, [
            'SUCCESS',
            'FAILED',
            'DECLINED',
            'EXPIRED',
            'REFUNDED',
            'VOIDED',
        ], true);
    }
}

