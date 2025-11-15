<?php

namespace App\Services\Discount;

interface DiscountStrategyInterface
{
    public function calculate(float $subTotal, array $context = []): float;
}