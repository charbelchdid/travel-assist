<?php

namespace App\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'ErpPayment.')]
interface ErpPaymentActivityInterface
{
    /**
     * Calls ERP `/payment/check-status?paymentID=<uuid>` via ErpService.
     *
     * @return array<string, mixed>
     */
    #[ActivityMethod(name: 'checkStatus')]
    public function checkStatus(string $paymentUuid): array;
}

