<?php

namespace App\Services\Discount;

class PercentageDiscountStrategy implements DiscountStrategyInterface
{
    public function __construct(private float $percent, private ?float $max = null) {}

    public function calculate(float $subTotal, array $context = []): float
    {
        $d = $subTotal * ($this->percent / 100.0);
        if ($this->max) $d = min($d, $this->max);
        return max(0.0, $d);
    }
}