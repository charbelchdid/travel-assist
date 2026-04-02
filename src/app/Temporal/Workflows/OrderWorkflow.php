<?php

namespace App\Temporal\Workflows;

use App\Temporal\Activities\OrderActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\Type;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;

/**
 * Demonstrates:
 * - Signals (addItem/cancel)
 * - Queries (status)
 * - Timers / await
 * - Activities (process order)
 */
final class OrderWorkflow implements OrderWorkflowInterface
{
    /** @var list<string> */
    private array $items = [];

    private bool $canceled = false;

    private string $state = 'created';

    private ?array $result = null;

    /**
     * @var object Activity stub proxy (doesn't implement the interface at runtime)
     */
    private object $activity;

    public function __construct()
    {
        $this->activity = Workflow::newActivityStub(
            OrderActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout('30 seconds')
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(5))
        );
    }

    #[ReturnType(name: Type::TYPE_ARRAY)]
    public function run(string $orderId): \Generator
    {
        $this->state = 'waiting_for_items';

        // Wait until we have at least one item OR we're canceled.
        yield Workflow::await(fn () => count($this->items) > 0 || $this->canceled);

        if ($this->canceled) {
            $this->state = 'canceled';
            return [
                'order_id' => $orderId,
                'state' => $this->state,
            ];
        }

        // Short timer to demonstrate deterministic waiting.
        $this->state = 'cooldown';
        yield Workflow::timer('3 seconds');

        $this->state = 'processing';

        /** @var array $result */
        $result = yield $this->activity->process($orderId, $this->items);

        $this->result = $result;
        $this->state = 'completed';

        return $result + ['state' => $this->state];
    }

    public function addItem(string $item): void
    {
        if ($this->canceled) {
            return;
        }

        $item = trim($item);
        if ($item === '') {
            return;
        }

        $this->items[] = $item;
    }

    public function cancel(): void
    {
        $this->canceled = true;
    }

    public function status(): array
    {
        return [
            'state' => $this->state,
            'canceled' => $this->canceled,
            'items' => $this->items,
            'result' => $this->result,
        ];
    }
}


