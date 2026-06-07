<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->categories() as $category) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        foreach ($this->surfaces() as $surface) {
            DB::table('surfaces')->updateOrInsert(['code' => $surface['code']], $surface);
        }

        foreach ($this->tags() as $tag) {
            DB::table('tags')->updateOrInsert(['slug' => $tag['slug']], $tag);
        }
    }

    private function categories(): array
    {
        return [
            ['name' => 'Giay Chay Bo', 'slug' => 'giay-chay-bo', 'description' => 'Cac loai giay chuyen dung cho hoat dong chay bo.'],
            ['name' => 'Giay Thoi Trang', 'slug' => 'giay-thoi-trang', 'description' => 'Giay mang phong cach hien dai, phu hop di hoc, di lam va dao pho.'],
            ['name' => 'Giay Da Bong', 'slug' => 'giay-da-bong', 'description' => 'Giay chuyen dung cho bo mon bong da.'],
        ];
    }

    private function surfaces(): array
    {
        return [
            ['code' => 'ROAD', 'name' => 'Duong Nhua', 'description' => 'Chay tren be mat duong bang phang.'],
            ['code' => 'TRAIL', 'name' => 'Duong Mon', 'description' => 'Chay tren dia hinh go ghe.'],
            ['code' => 'TREADMILL', 'name' => 'May Chay Bo', 'description' => 'Su dung trong nha voi may chay bo.'],
        ];
    }

    private function tags(): array
    {
        return [
            ['name' => 'Ho Tro', 'slug' => 'ho-tro'],
            ['name' => 'Em Ai', 'slug' => 'em-ai'],
            ['name' => 'Toc Do', 'slug' => 'toc-do'],
            ['name' => 'Ben Bi', 'slug' => 'ben-bi'],
        ];
    }
}
