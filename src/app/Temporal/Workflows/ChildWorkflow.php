<?php

namespace App\Temporal\Workflows;

use Temporal\Workflow\ReturnType;

final class ChildWorkflow implements ChildWorkflowInterface
{
    #[ReturnType(name: ReturnType::TYPE_INT)]
    public function run(int $value): \Generator
    {
        // This method must return a Generator for the Temporal PHP SDK.
        // We don't need to yield any Temporal commands here; keep it deterministic and "no-op yield" free.
        if (false) {
            yield null;
        }

        return $value * 2;
    }
}


