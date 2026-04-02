<?php

namespace App\Temporal\Activities;

use Illuminate\Support\Str;

final class PaymentActivity implements PaymentActivityInterface
{
    public function reserveFunds(string $paymentId, int $amountCents): string
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('amountCents must be > 0');
        }

        return "reservation_" . Str::uuid()->toString();
    }

    public function releaseFunds(string $reservationId): void
    {
        // no-op example compensation
    }

    public function createShipment(string $orderId): string
    {
        if (trim($orderId) === '') {
            throw new \InvalidArgumentException('orderId is required');
        }

        return "shipment_" . Str::uuid()->toString();
    }

    public function cancelShipment(string $shipmentId): void
    {
        // no-op example compensation
    }
}


