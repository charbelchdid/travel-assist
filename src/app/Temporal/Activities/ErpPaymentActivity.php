<?php

namespace App\Temporal\Activities;

use App\Services\Interfaces\ErpServiceInterface;

final class ErpPaymentActivity implements ErpPaymentActivityInterface
{
    public function checkStatus(string $paymentUuid): array
    {
        $paymentUuid = trim($paymentUuid);
        if ($paymentUuid === '') {
            throw new \InvalidArgumentException('paymentUuid is required');
        }

        /** @var ErpServiceInterface $erp */
        $erp = app(ErpServiceInterface::class);

        // Payment API is public upstream, so we don't attach JWT here.
        return $erp->paymentCheckStatus($paymentUuid);
    }
}

