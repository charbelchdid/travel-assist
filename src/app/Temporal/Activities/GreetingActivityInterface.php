<?php

namespace App\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'Greeting.')]
interface GreetingActivityInterface
{
    #[ActivityMethod(name: 'sayHello')]
    public function sayHello(string $name): string;
}


