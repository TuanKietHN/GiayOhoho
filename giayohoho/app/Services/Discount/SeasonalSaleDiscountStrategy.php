<?php

namespace App\Services\Discount;

use Illuminate\Support\Carbon;

class SeasonalSaleDiscountStrategy implements DiscountStrategyInterface
{
    public function __construct(private float $percent, private Carbon $start, private Carbon $end) {}

    public function calculate(float $subTotal, array $context = []): float
    {
        $now = Carbon::now();
        if ($now->between($this->start, $this->end)) {
            return max(0.0, $subTotal * ($this->percent / 100.0));
        }
        return 0.0;
    }
}