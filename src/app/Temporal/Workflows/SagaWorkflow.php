<?php

namespace App\Temporal\Workflows;

use App\Temporal\Activities\PaymentActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\Type;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;

/**
 * Demonstrates the Saga pattern (compensations) using Activities.
 */
final class SagaWorkflow implements SagaWorkflowInterface
{
    /**
     * @var object Activity stub proxy (doesn't implement the interface at runtime)
     */
    private object $payment;

    public function __construct()
    {
        $this->payment = Workflow::newActivityStub(
            PaymentActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout('15 seconds')
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(3))
        );
    }

    #[ReturnType(name: Type::TYPE_ARRAY)]
    public function run(string $orderId, int $amountCents): \Generator
    {
        $compensations = [];

        try {
            /** @var string $reservationId */
            $reservationId = yield $this->payment->reserveFunds("pay-{$orderId}", $amountCents);
            $compensations[] = fn () => $this->payment->releaseFunds($reservationId);

            /** @var string $shipmentId */
            $shipmentId = yield $this->payment->createShipment($orderId);
            $compensations[] = fn () => $this->payment->cancelShipment($shipmentId);

            // Example failure trigger: negative amount simulates later validation failure.
            if ($amountCents < 100) {
                throw new \RuntimeException('Simulated failure after creating shipment');
            }

            return [
                'order_id' => $orderId,
                'reservation_id' => $reservationId,
                'shipment_id' => $shipmentId,
                'status' => 'completed',
            ];
        } catch (\Throwable $e) {
            // Run compensations in reverse order.
            for ($i = count($compensations) - 1; $i >= 0; $i--) {
                $comp = $compensations[$i];
                yield Workflow::asyncDetached($comp);
            }

            throw $e;
        }
    }
}


