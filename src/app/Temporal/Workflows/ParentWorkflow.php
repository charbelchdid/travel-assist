<?php

namespace App\Temporal\Workflows;

use Temporal\DataConverter\Type;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;

/**
 * Demonstrates Child Workflows.
 */
final class ParentWorkflow implements ParentWorkflowInterface
{
    #[ReturnType(name: ReturnType::TYPE_INT)]
    public function run(int $value): \Generator
    {
        // Workaround: avoid stub return-type inference (which can fall back to the PHP return type `Generator`
        // and break decoding). Use an untyped stub and pass an explicit return type for decoding.
        $child = Workflow::newUntypedChildWorkflowStub('ChildWorkflow');

        /** @var int $childResult */
        $childResult = yield $child->execute([$value], Type::TYPE_INT);

        return $childResult + 1;
    }
}


