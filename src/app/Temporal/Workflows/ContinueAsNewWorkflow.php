<?php

namespace App\Temporal\Workflows;

use Temporal\Workflow;
use Temporal\DataConverter\Type;
use Temporal\Workflow\ReturnType;

/**
 * Demonstrates Continue-As-New.
 */
final class ContinueAsNewWorkflow implements ContinueAsNewWorkflowInterface
{
    #[ReturnType(name: Type::TYPE_ARRAY)]
    public function run(array $items, int $batchSize = 5): \Generator
    {
        $batchSize = max(1, $batchSize);

        /** @var list<int> $items */
        $items = array_values($items);

        $processed = array_slice($items, 0, $batchSize);
        $remaining = array_slice($items, $batchSize);

        // Simulate work deterministically (timer, not sleep()).
        yield Workflow::timer('1 second');

        if (count($remaining) > 0) {
            // Signature: continueAsNew(string $type, array $args = [], ?ContinueAsNewOptions $options = null)
            // So workflow args must be passed as an array (not as positional PHP args).
            return yield Workflow::continueAsNew('ContinueAsNewWorkflow', [$remaining, $batchSize]);
        }

        return [
            'processed' => $processed,
            'remaining' => [],
        ];
    }
}


