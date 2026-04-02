<?php

namespace App\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'Payment.')]
interface PaymentActivityInterface
{
    #[ActivityMethod(name: 'reserveFunds')]
    public function reserveFunds(string $paymentId, int $amountCents): string;

    #[ActivityMethod(name: 'releaseFunds')]
    public function releaseFunds(string $reservationId): void;

    #[ActivityMethod(name: 'createShipment')]
    public function createShipment(string $orderId): string;

    #[ActivityMethod(name: 'cancelShipment')]
    public function cancelShipment(string $shipmentId): void;
}


