<?php

namespace App\Support;

final class MoneyBalance
{
    /**
     * Normalize a requested debit against available balance.
     * If the user entered the display-rounded available (common when transferring "all"),
     * clamp to the exact available instead of failing.
     *
     * @throws \InvalidArgumentException
     */
    public static function resolveDebitAmount(float $requested, float $available, string $sourceName): float
    {
        $requested = round($requested, 2);
        $available = round($available, 2);

        if ($requested <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        if ($requested <= $available) {
            return $requested;
        }

        $displayAvailable = round($available, 2);
        $displayRequested = round($requested, 2);

        if ($available > 0 && $displayRequested === $displayAvailable) {
            return $available;
        }

        throw new \InvalidArgumentException(
            'Insufficient balance in '.$sourceName.'. Available: '.number_format($available, 2)
        );
    }
}
