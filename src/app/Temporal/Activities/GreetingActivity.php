<?php

namespace App\Temporal\Activities;

final class GreetingActivity implements GreetingActivityInterface
{
    public function sayHello(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'world';
        }

        return "Hello, {$name}!";
    }
}


