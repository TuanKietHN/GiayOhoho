<?php

namespace App\Services\Discount;

use App\Models\Coupon;
use Illuminate\Support\Carbon;

class DiscountCalculator
{
    public function forCoupon(Coupon $coupon): DiscountStrategyInterface
    {
        if ($coupon->discount_type === 'PERCENTAGE') {
            return new PercentageDiscountStrategy((float) $coupon->discount_value, $coupon->max_discount ? (float) $coupon->max_discount : null);
        }
        return new FixedAmountDiscountStrategy((float) $coupon->discount_value);
    }

    public function seasonal(float $percent, Carbon $start, Carbon $end): DiscountStrategyInterface
    {
        return new SeasonalSaleDiscountStrategy($percent, $start, $end);
    }
}