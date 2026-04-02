<?php

namespace App\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'Order.')]
interface OrderActivityInterface
{
    #[ActivityMethod(name: 'process')]
    public function process(string $orderId, array $items): array;
}


