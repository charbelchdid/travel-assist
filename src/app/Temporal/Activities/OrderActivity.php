<?php

namespace App\Temporal\Activities;

final class OrderActivity implements OrderActivityInterface
{
    public function process(string $orderId, array $items): array
    {
        // Example activity: in real life you'd call external services (inventory, payment, shipping).
        return [
            'order_id' => $orderId,
            'processed_items' => array_values($items),
            'processed_at' => now()->toIso8601String(),
        ];
    }
}


