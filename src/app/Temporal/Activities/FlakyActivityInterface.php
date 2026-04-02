<?php

namespace App\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'Flaky.')]
interface FlakyActivityInterface
{
    /**
     * Fails until the activity attempt reaches $succeedOnAttempt.
     */
    #[ActivityMethod(name: 'run')]
    public function run(int $succeedOnAttempt = 3): string;
}


