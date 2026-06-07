<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerActivitySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedReviews();
        $this->seedWishlist();
        $this->seedCarts();
    }

    private function seedReviews(): void
    {
        foreach ($this->reviews() as $review) {
            $accountId = DB::table('accounts')->where('email', $review['email'])->value('id');
            $productId = DB::table('products')->where('slug', $review['product_slug'])->value('id');
            if (! $accountId || ! $productId) {
                continue;
            }

            DB::table('reviews')->updateOrInsert(
                ['account_id' => $accountId, 'product_id' => $productId],
                [
                    'rating' => $review['rating'],
                    'comment' => $review['comment'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedWishlist(): void
    {
        foreach ($this->wishlist() as $item) {
            $accountId = DB::table('accounts')->where('email', $item['email'])->value('id');
            $productId = DB::table('products')->where('slug', $item['product_slug'])->value('id');
            if (! $accountId || ! $productId) {
                continue;
            }

            DB::table('wishlist')->where('account_id', $accountId)->update(['deleted_at' => now()]);
            DB::table('wishlist')->updateOrInsert(
                ['account_id' => $accountId, 'product_id' => $productId],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedCarts(): void
    {
        foreach ($this->carts() as $cartSeed) {
            $accountId = DB::table('accounts')->where('email', $cartSeed['email'])->value('id');
            $variant = DB::table('product_variants')->where('sku', $cartSeed['sku'])->first();
            if (! $accountId || ! $variant) {
                continue;
            }

            $basePrice = (int) DB::table('products')->where('id', $variant->product_id)->value('base_price');
            $price = $basePrice + (int) $variant->extra_price;

            DB::table('cart')->updateOrInsert(
                ['account_id' => $accountId],
                [
                    'total' => $price,
                    'sub_total' => $price,
                    'discount_amount' => 0,
                    'coupon_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $cartId = DB::table('cart')->where('account_id', $accountId)->value('id');
            DB::table('cart_item')->where('cart_id', $cartId)->delete();
            DB::table('cart_item')->insert([
                'cart_id' => $cartId,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'price' => $price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function reviews(): array
    {
        return [
            ['email' => 'tuankiethn@ohgiay.vn', 'product_slug' => 'nike-pegasus-41', 'rating' => 5, 'comment' => 'Form giay om chan, dem em va chay daily run rat on dinh.'],
            ['email' => 'tuankiethn@ohgiay.vn', 'product_slug' => 'nike-air-force-1-07', 'rating' => 5, 'comment' => 'Phoi do de, da ngoai cung cap va di hang ngay rat hop.'],
            ['email' => 'kiet@ohgiay.vn', 'product_slug' => 'nike-p-6000', 'rating' => 4, 'comment' => 'Kieu dang retro dep, upper thoang va di pho kha thoai mai.'],
            ['email' => 'tuankiethn@ohgiay.vn', 'product_slug' => 'adidas-ultraboost-22', 'rating' => 5, 'comment' => 'Boost foam rat em, chay duong nhua hang ngay cuc ky thoai mai.'],
            ['email' => 'kiet@ohgiay.vn', 'product_slug' => 'new-balance-fresh-foam-x-1080v13', 'rating' => 5, 'comment' => 'De giay em nhu buoc tren may, rat xung dang voi gia tien.'],
        ];
    }

    private function wishlist(): array
    {
        return [
            ['email' => 'tuankiethn@ohgiay.vn', 'product_slug' => 'nike-air-force-1-07'],
            ['email' => 'kiet@ohgiay.vn', 'product_slug' => 'adidas-stan-smith'],
        ];
    }

    private function carts(): array
    {
        return [
            ['email' => 'tuankiethn@ohgiay.vn', 'sku' => 'NK-PEG41-42'],
            ['email' => 'kiet@ohgiay.vn', 'sku' => 'ADI-UB22-42'],
        ];
    }
}
