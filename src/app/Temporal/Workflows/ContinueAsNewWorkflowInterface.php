<?php

namespace App\Temporal\Workflows;

use Temporal\DataConverter\Type;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface ContinueAsNewWorkflowInterface
{
    /**
     * Processes $items in batches of $batchSize and uses Continue-As-New to avoid huge history.
     *
     * @param list<int> $items
     */
    #[ReturnType(name: Type::TYPE_ARRAY)]
    #[WorkflowMethod(name: 'ContinueAsNewWorkflow')]
    public function run(array $items, int $batchSize = 5): mixed;
}


