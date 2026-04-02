<?php

namespace App\Temporal\Activities;

use Temporal\Activity;

final class FlakyActivity implements FlakyActivityInterface
{
    public function run(int $succeedOnAttempt = 3): string
    {
        $attempt = Activity::getInfo()->attempt;

        if ($attempt < $succeedOnAttempt) {
            throw new \RuntimeException("Flaky activity failing on attempt {$attempt}");
        }

        return "Succeeded on attempt {$attempt}";
    }
}


