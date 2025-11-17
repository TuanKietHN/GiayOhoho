<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('vi_VN');

        DB::table('roles')->updateOrInsert(['name' => 'guest'], ['description' => 'Khách vãng lai', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->updateOrInsert(['name' => 'customer'], ['description' => 'Khách hàng', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->updateOrInsert(['name' => 'admin'], ['description' => 'Quản trị', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->updateOrInsert(['name' => 'staff'], ['description' => 'Nhân viên', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'username' => 'admin',
                'password' => Hash::make('Admin@123'),
                'phone_number' => '0900000000',
                'birth_of_date' => '1990-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('users')->updateOrInsert(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer User',
                'first_name' => 'Customer',
                'last_name' => 'User',
                'username' => 'customer',
                'password' => Hash::make('Customer@123'),
                'phone_number' => '0901111222',
                'birth_of_date' => '1995-05-05',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $admin = DB::table('users')->where('email','admin@example.com')->first();
        $customer = DB::table('users')->where('email','customer@example.com')->first();
        $roleAdmin = DB::table('roles')->where('name','admin')->first();
        $roleCustomer = DB::table('roles')->where('name','customer')->first();
        if ($admin && $roleAdmin) {
            DB::table('user_roles')->updateOrInsert(['user_id' => $admin->id, 'role_id' => $roleAdmin->id], []);
        }
        if ($customer && $roleCustomer) {
            DB::table('user_roles')->updateOrInsert(['user_id' => $customer->id, 'role_id' => $roleCustomer->id], []);
        }

        DB::table('categories')->updateOrInsert(['slug' => 'footwear'], ['name' => 'Footwear', 'slug' => 'footwear', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('categories')->updateOrInsert(['slug' => 'accessories'], ['name' => 'Accessories', 'slug' => 'accessories', 'created_at' => now(), 'updated_at' => now()]);
        $footwear = DB::table('categories')->where('slug','footwear')->first();
        DB::table('categories')->updateOrInsert(['slug' => 'running-shoes'], ['name' => 'Running Shoes', 'slug' => 'running-shoes', 'parent_id' => $footwear->id ?? null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('categories')->updateOrInsert(['slug' => 'trail-running-shoes'], ['name' => 'Trail Running Shoes', 'slug' => 'trail-running-shoes', 'parent_id' => $footwear->id ?? null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('categories')->updateOrInsert(['slug' => 'walking-shoes'], ['name' => 'Walking Shoes', 'slug' => 'walking-shoes', 'parent_id' => $footwear->id ?? null, 'created_at' => now(), 'updated_at' => now()]);

        $surfaces = [
            ['code' => 'road', 'name' => 'Đường nhựa'],
            ['code' => 'trail', 'name' => 'Đường trail'],
            ['code' => 'treadmill', 'name' => 'Máy chạy bộ'],
            ['code' => 'walking', 'name' => 'Đi bộ'],
            ['code' => 'hiking', 'name' => 'Leo núi'],
        ];
        foreach ($surfaces as $s) { DB::table('surfaces')->updateOrInsert(['code' => $s['code']], $s); }

        $tags = [
            ['name' => 'Race Day', 'slug' => 'race-day'],
            ['name' => 'Daily Trainer', 'slug' => 'daily-trainer'],
            ['name' => 'Carbon Plate', 'slug' => 'carbon-plate'],
            ['name' => 'Wide Fit', 'slug' => 'wide-fit'],
            ['name' => 'Beginner Friendly', 'slug' => 'beginner-friendly'],
        ];
        foreach ($tags as $t) { DB::table('tags')->updateOrInsert(['slug' => $t['slug']], $t); }

        $brands = ['Nike','Adidas','Asics','Saucony','New Balance','Hoka','Brooks'];
        $brandModels = [
            'Nike' => ['Air Zoom Pegasus 41','ZoomX Vaporfly 3','Air Zoom Alphafly 3','React Infinity Run 4'],
            'Adidas' => ['Adizero Boston 13','Adizero Adios 8','Ultraboost Light','Supernova Rise'],
            'Asics' => ['Gel Nimbus 26','Gel Kayano 30','Metaspeed Sky Paris','Novablast 4'],
            'Saucony' => ['Endorphin Speed 4','Endorphin Pro 4','Ride 17','Triumph 22'],
            'New Balance' => ['FuelCell SuperComp Elite v4','Fresh Foam 1080 v13','FuelCell Rebel v4','FuelCell Propel v4'],
            'Hoka' => ['Mach 6','Clifton 9','Rocket X 2','Carbon X 3'],
            'Brooks' => ['Ghost 16','Glycerin 21','Hyperion Elite 4','Adrenaline GTS 23'],
        ];
        $genders = ['male','female','unisex'];
        $sizes = ['EU 40','EU 41','EU 42','EU 43'];
        $catIds = DB::table('categories')->pluck('id')->all();
        $surfaceIdsByCode = DB::table('surfaces')->pluck('id','code')->toArray();
        $tagIdsBySlug = DB::table('tags')->pluck('id','slug')->toArray();
        $tagSlugs = array_keys($tagIdsBySlug);

        for ($i = 0; $i < 20; $i++) {
            $brand = $faker->randomElement($brands);
            $name = $brand.' '.($faker->randomElement($brandModels[$brand]));
            $slug = Str::slug($name).'-'.Str::random(6);
            $productId = DB::table('products')->insertGetId([
                'category_id' => $faker->randomElement($catIds),
                'name' => $name,
                'slug' => $slug,
                'brand' => $brand,
                'gender' => $faker->randomElement($genders),
                'base_price' => $faker->numberBetween(1500000, 4500000),
                'description' => $faker->paragraph(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $pickSurfaces = $faker->randomElements(['road','trail','treadmill','walking','hiking'], $faker->numberBetween(1,3));
            foreach ($pickSurfaces as $code) {
                DB::table('product_surfaces')->updateOrInsert(['product_id' => $productId, 'surface_id' => $surfaceIdsByCode[$code] ?? null], []);
            }

            $pickTags = $faker->randomElements($tagSlugs, $faker->numberBetween(1,3));
            foreach ($pickTags as $slugTag) {
                DB::table('product_tags')->updateOrInsert(['product_id' => $productId, 'tag_id' => $tagIdsBySlug[$slugTag] ?? null], []);
            }

            DB::table('product_specs_shoes')->updateOrInsert(
                ['product_id' => $productId],
                [
                    'cushioning_level' => $faker->randomElement(['low','medium','high','maximum']),
                    'pronation_type' => $faker->randomElement(['neutral','stability','motion_control']),
                    'drop_mm' => $faker->randomFloat(1, 4, 12),
                    'weight_grams' => $faker->numberBetween(200, 350),
                    'is_waterproof' => $faker->boolean(20),
                    'is_reflective' => $faker->boolean(30),
                    'upper_material' => $faker->word(),
                    'midsole_technology' => $faker->word(),
                    'outsole_technology' => $faker->word(),
                    'created_at' => now(),
                ]
            );

            DB::table('product_images')->insert([
                'product_id' => $productId,
                'image_url' => 'https://source.unsplash.com/600x400/?'.urlencode($brand.' shoe'),
                'alt_text' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($j = 0; $j < 4; $j++) {
                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id' => $productId,
                    'size' => $sizes[$j],
                    'color' => $faker->safeColorName(),
                    'sku' => Str::upper(Str::random(10)),
                    'stock' => $faker->numberBetween(0, 50),
                    'extra_price' => $faker->numberBetween(0, 500000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('product_images')->insert([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'image_url' => 'https://source.unsplash.com/600x400/?'.urlencode($brand.' '.$sizes[$j].' shoe'),
                    'alt_text' => $name.' '.$sizes[$j],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($customer) {
                for ($r = 0; $r < $faker->numberBetween(1,3); $r++) {
                    DB::table('reviews')->insert([
                        'user_id' => $customer->id,
                        'product_id' => $productId,
                        'rating' => $faker->numberBetween(3, 5),
                        'comment' => $faker->sentence(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($customer) {
            $wishlistProduct = DB::table('products')->inRandomOrder()->first();
            if ($wishlistProduct) {
                DB::table('wishlist')->updateOrInsert(['user_id' => $customer->id, 'product_id' => $wishlistProduct->id], ['created_at' => now(), 'updated_at' => now()]);
            }
        }

        DB::table('coupons')->updateOrInsert(['code' => 'WELCOME10'], [
            'description' => 'Giảm 10%',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 10,
            'min_purchase' => 500000,
            'max_discount' => 200000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'usage_limit' => 1000,
            'is_active' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('coupons')->updateOrInsert(['code' => 'SALE100K'], [
            'description' => 'Giảm 100K',
            'discount_type' => 'FIXED_AMOUNT',
            'discount_value' => 100000,
            'min_purchase' => 800000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'usage_limit' => 1000,
            'is_active' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        if ($customer) {
            $variant = DB::table('product_variants')->inRandomOrder()->first();
            if ($variant) {
                $price = (int) DB::table('products')->where('id', $variant->product_id)->value('base_price') + (int) $variant->extra_price;
                $cartId = DB::table('cart')->insertGetId([
                    'user_id' => $customer->id,
                    'sub_total' => $price,
                    'discount_amount' => 0,
                    'total' => $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
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
    }
}
