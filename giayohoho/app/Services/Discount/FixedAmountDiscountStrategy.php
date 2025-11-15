<?php

namespace App\Services\Discount;

class FixedAmountDiscountStrategy implements DiscountStrategyInterface
{
    public function __construct(private float $amount) {}

    public function calculate(float $subTotal, array $context = []): float
    {
        return max(0.0, min($this->amount, $subTotal));
    }
}