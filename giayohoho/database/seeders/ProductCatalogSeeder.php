<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $categoryIds = DB::table('categories')->pluck('id', 'slug')->all();
        $surfaceIds = DB::table('surfaces')->pluck('id', 'code')->all();
        $tagIds = DB::table('tags')->pluck('id', 'slug')->all();
        $protectedSlugs = [];

        foreach ($this->catalog() as $product) {
            $protectedSlugs[] = $product['slug'];
            $productId = $this->upsertProduct($product, $categoryIds, $now);
            $this->syncSurfaces($productId, $product['surface_codes'], $surfaceIds);
            $this->syncTags($productId, $product['tag_slugs'], $tagIds);
            $this->syncSpecs($productId, $product['specs']);
            $variantIdsBySku = $this->syncVariants($productId, $product['variants'], $now);
            $this->syncImages($productId, $product['images'], $variantIdsBySku, $now);
        }

        $this->deactivateLegacyFakerProducts($protectedSlugs, $now);
    }

    private function upsertProduct(array $product, array $categoryIds, mixed $now): int
    {
        $payload = [
            'category_id' => $categoryIds[$product['category_slug']] ?? null,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'brand' => $product['brand'],
            'gender' => $product['gender'],
            'base_price' => $product['base_price'],
            'description' => $product['description'],
            'updated_at' => $now,
            'deleted_at' => null,
        ];

        $existingId = DB::table('products')->where('slug', $product['slug'])->value('id');
        if ($existingId) {
            DB::table('products')->where('id', $existingId)->update($payload);
            return (int) $existingId;
        }

        return (int) DB::table('products')->insertGetId($payload + ['created_at' => $now]);
    }

    private function syncSurfaces(int $productId, array $codes, array $surfaceIds): void
    {
        DB::table('product_surfaces')->where('product_id', $productId)->delete();
        foreach ($codes as $code) {
            if (! isset($surfaceIds[$code])) {
                continue;
            }
            DB::table('product_surfaces')->insertOrIgnore([
                'product_id' => $productId,
                'surface_id' => $surfaceIds[$code],
            ]);
        }
    }

    private function syncTags(int $productId, array $slugs, array $tagIds): void
    {
        DB::table('product_tags')->where('product_id', $productId)->delete();
        foreach ($slugs as $slug) {
            if (! isset($tagIds[$slug])) {
                continue;
            }
            DB::table('product_tags')->insertOrIgnore([
                'product_id' => $productId,
                'tag_id' => $tagIds[$slug],
            ]);
        }
    }

    private function syncSpecs(int $productId, array $specs): void
    {
        DB::table('product_specs_shoes')->updateOrInsert(
            ['product_id' => $productId],
            [
                'cushioning_level' => $specs['cushioning_level'],
                'pronation_type' => $specs['pronation_type'],
                'drop_mm' => $specs['drop_mm'],
                'weight_grams' => $specs['weight_grams'],
                'is_waterproof' => $specs['is_waterproof'],
                'is_reflective' => $specs['is_reflective'],
                'upper_material' => $specs['upper_material'],
                'midsole_technology' => $specs['midsole_technology'],
                'outsole_technology' => $specs['outsole_technology'],
                'created_at' => now(),
            ]
        );
    }

    private function syncVariants(int $productId, array $variants, mixed $now): array
    {
        $variantIdsBySku = [];
        $desiredSkus = [];

        foreach ($variants as $variant) {
            $desiredSkus[] = $variant['sku'];
            $payload = [
                'product_id' => $productId,
                'size' => $variant['size'],
                'color' => $variant['color'],
                'sku' => $variant['sku'],
                'stock' => $variant['stock'],
                'extra_price' => $variant['extra_price'],
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $variantId = DB::table('product_variants')->where('sku', $variant['sku'])->value('id');
            if ($variantId) {
                DB::table('product_variants')->where('id', $variantId)->update($payload);
            } else {
                $variantId = DB::table('product_variants')->insertGetId($payload + ['created_at' => $now]);
            }
            $variantIdsBySku[$variant['sku']] = (int) $variantId;
        }

        DB::table('product_variants')
            ->where('product_id', $productId)
            ->whereNotIn('sku', $desiredSkus)
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        return $variantIdsBySku;
    }

    private function syncImages(int $productId, array $images, array $variantIdsBySku, mixed $now): void
    {
        if (! collect($images)->contains(fn (array $image) => (bool) $image['primary']) && count($images) > 0) {
            $images[0]['primary'] = true;
        }

        $desiredUrls = [];
        foreach ($images as $index => $image) {
            $variantId = $image['variant_sku'] ? ($variantIdsBySku[$image['variant_sku']] ?? null) : null;
            $desiredUrls[] = $image['image_url'];
            $contentType = str_ends_with($image['image_url'], '.svg') ? 'image/svg+xml' : null;

            DB::table('product_images')->updateOrInsert(
                [
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'image_url' => $image['image_url'],
                ],
                [
                    'alt_text' => $image['alt_text'],
                    'object_name' => null,
                    'content_type' => $contentType,
                    'size_bytes' => null,
                    'is_primary' => (bool) $image['primary'],
                    'sort_order' => $image['sort_order'] ?: (($index + 1) * 10),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('product_images')
            ->where('product_id', $productId)
            ->whereNotIn('image_url', $desiredUrls)
            ->update(['deleted_at' => $now, 'is_primary' => false, 'updated_at' => $now]);
    }

    private function deactivateLegacyFakerProducts(array $protectedSlugs, mixed $now): void
    {
        $legacyProductIds = DB::table('products')
            ->join('product_images', 'products.id', '=', 'product_images.product_id')
            ->whereNotIn('products.slug', $protectedSlugs)
            ->whereNull('products.deleted_at')
            ->where('product_images.image_url', 'like', '%picsum.photos/seed/%')
            ->distinct()
            ->pluck('products.id');

        if ($legacyProductIds->isEmpty()) {
            return;
        }

        DB::table('products')->whereIn('id', $legacyProductIds)->update(['deleted_at' => $now, 'updated_at' => $now]);
        DB::table('product_variants')->whereIn('product_id', $legacyProductIds)->update(['deleted_at' => $now, 'updated_at' => $now]);
        DB::table('product_images')->whereIn('product_id', $legacyProductIds)->update(['deleted_at' => $now, 'is_primary' => false, 'updated_at' => $now]);
    }

    private function catalog(): array
    {
        return json_decode(<<<'JSON'
[
  {
    "slug": "nike-pegasus-41",
    "name": "Nike Pegasus 41",
    "brand": "Nike",
    "gender": "male",
    "base_price": 3699000,
    "description": "Mau giay daily trainer cua Nike voi ReactX foam va 2 bo dem Air Zoom, phu hop chay duong nhua hang ngay va tap treadmill.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 297,
      "is_waterproof": false,
      "is_reflective": true,
      "upper_material": "Engineered mesh",
      "midsole_technology": "ReactX foam + Air Zoom",
      "outsole_technology": "Waffle-inspired rubber"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/d7df4815-2375-4608-8d2a-1772a7d7ad03/AIR+ZOOM+PEGASUS+41.png",
        "alt_text": "Nike Pegasus 41 Black Cool Grey White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Cool Grey/White",
        "sku": "NK-PEG41-40",
        "stock": 14,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Cool Grey/White",
        "sku": "NK-PEG41-41",
        "stock": 12,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Cool Grey/White",
        "sku": "NK-PEG41-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Cool Grey/White",
        "sku": "NK-PEG41-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Cool Grey/White",
        "sku": "NK-PEG41-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-downshifter-13",
    "name": "Nike Downshifter 13",
    "brand": "Nike",
    "gender": "male",
    "base_price": 2069000,
    "description": "Mau giay chay bo gia de tiep can, upper mesh thoang khi va lop foam mem phu hop cho chay nhe va di hoc hang ngay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 285,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Breathable mesh",
      "midsole_technology": "Soft foam",
      "outsole_technology": "Durable rubber outsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/ac6672b0-33da-49ad-9a6f-412ae3987bcc/NIKE+DOWNSHIFTER+13.png",
        "alt_text": "Nike Downshifter 13 White Wolf Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Wolf Grey",
        "sku": "NK-DOWN13-39",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Wolf Grey",
        "sku": "NK-DOWN13-40",
        "stock": 13,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Wolf Grey",
        "sku": "NK-DOWN13-41",
        "stock": 11,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Wolf Grey",
        "sku": "NK-DOWN13-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Wolf Grey",
        "sku": "NK-DOWN13-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-run-defy",
    "name": "Nike Run Defy",
    "brand": "Nike",
    "gender": "male",
    "base_price": 1699000,
    "description": "Mau giay road running co upper thoang, foam midsole mem va de waffle, phu hop cho nguoi moi bat dau chay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 281,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Breathable mesh",
      "midsole_technology": "Foam midsole",
      "outsole_technology": "Waffle outsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/7e50e539-707f-441a-8952-7e9007ef5e2f/NIKE+RUN+DEFY.png",
        "alt_text": "Nike Run Defy White Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "NK-RUNDEFY-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black",
        "sku": "NK-RUNDEFY-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black",
        "sku": "NK-RUNDEFY-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Black",
        "sku": "NK-RUNDEFY-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-p-6000",
    "name": "Nike P-6000",
    "brand": "Nike",
    "gender": "unisex",
    "base_price": 3199000,
    "description": "Mau sneaker lifestyle lay cam hung tu dong Pegasus nhung nam 2000, upper mesh ket hop synthetic overlays va dem foam di pho rat hop.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 325,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh + synthetic overlays",
      "midsole_technology": "Foam midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/360320d5-93f5-4cd2-a6f4-093608a21100/NIKE+P-6000.png",
        "alt_text": "Nike P-6000 Metallic Silver Flat Pewter",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Metallic Silver/Flat Pewter",
        "sku": "NK-P6000-39",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Metallic Silver/Flat Pewter",
        "sku": "NK-P6000-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Metallic Silver/Flat Pewter",
        "sku": "NK-P6000-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Metallic Silver/Flat Pewter",
        "sku": "NK-P6000-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-v5-rnr",
    "name": "Nike V5 RNR",
    "brand": "Nike",
    "gender": "male",
    "base_price": 2499000,
    "description": "Mau sneaker Y2K co phom day dan nhung nhe, de foam em chan va phu hop di hoc, di pho hoac mac do casual hang ngay.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 9.0,
      "weight_grams": 318,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh with synthetic overlays",
      "midsole_technology": "Chunky foam midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/1ac2e498-d12b-41bf-81a9-10a0ad32d7f5/NIKE+V5+RNR.png",
        "alt_text": "Nike V5 RNR Black Anthracite Summit White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Anthracite/Summit White",
        "sku": "NK-V5RNR-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Anthracite/Summit White",
        "sku": "NK-V5RNR-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Anthracite/Summit White",
        "sku": "NK-V5RNR-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Anthracite/Summit White",
        "sku": "NK-V5RNR-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-air-force-1-07",
    "name": "Nike Air Force 1 '07",
    "brand": "Nike",
    "gender": "male",
    "base_price": 3299000,
    "description": "Mau lifestyle kinh dien cua Nike voi upper da, dem Nike Air va phom de pho de phoi do, phu hop nhu mot san pham ban chay on dinh.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 390,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather upper",
      "midsole_technology": "Nike Air cushioning",
      "outsole_technology": "Pivot-circle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/b7d9211c-26e7-431a-ac24-b0540fb3c00f/AIR+FORCE+1+%2707.png",
        "alt_text": "Nike Air Force 1 07 White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/White",
        "sku": "NK-AF1-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/White",
        "sku": "NK-AF1-40",
        "stock": 11,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/White",
        "sku": "NK-AF1-41",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/White",
        "sku": "NK-AF1-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/White",
        "sku": "NK-AF1-43",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-court-vision-low",
    "name": "Nike Court Vision Low",
    "brand": "Nike",
    "gender": "male",
    "base_price": 2199000,
    "description": "Mau sneaker co ngon ngu thiet ke lay cam hung tu basketball thap nien 80, collar thap, upper sach se va de cao su ben cho di hang ngay.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 350,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Synthetic leather",
      "midsole_technology": "Foam cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/0084df47-cf15-41d5-aab6-984460364e41/NIKE+COURT+VISION+LO.png",
        "alt_text": "Nike Court Vision Low White Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Black",
        "sku": "NK-CVLOW-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "NK-CVLOW-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black",
        "sku": "NK-CVLOW-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black",
        "sku": "NK-CVLOW-42",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-react-infinity-run-4",
    "name": "Nike React Infinity Run 4",
    "brand": "Nike",
    "gender": "male",
    "base_price": 4299000,
    "description": "Mau giay chay bo co de wider ung ho chay an toan, React foam mang lai su on dinh va dem lot xuyen suot.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 9.0,
      "weight_grams": 306,
      "is_waterproof": false,
      "is_reflective": true,
      "upper_material": "Flyknit upper",
      "midsole_technology": "React foam",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/4e8c4f52-7bff-474b-9554-097913d7f977/W+NIKE+REACTX+INFINITY+RUN+4.png",
        "alt_text": "Nike React Infinity Run 4 White Blue",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Blue",
        "sku": "NK-RIR4-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Blue",
        "sku": "NK-RIR4-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Blue",
        "sku": "NK-RIR4-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Blue",
        "sku": "NK-RIR4-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-air-max-90",
    "name": "Nike Air Max 90",
    "brand": "Nike",
    "gender": "unisex",
    "base_price": 3899000,
    "description": "Bien the kinh dien cua Air Max 90 voi de bong Air Sole lo ro rang, upper thoang mat va nguyen khai design mang lai ve dep vintage.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 370,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh + leather",
      "midsole_technology": "Visible Air Sole unit",
      "outsole_technology": "Carbon rubber outsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/738fd7db-dbd2-4e78-b5d8-a712662742ee/NIKE+AIR+MAX+90.png",
        "alt_text": "Nike Air Max 90 White Infrared",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Infrared",
        "sku": "NK-AM90-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Infrared",
        "sku": "NK-AM90-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Infrared",
        "sku": "NK-AM90-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Infrared",
        "sku": "NK-AM90-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Infrared",
        "sku": "NK-AM90-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-mercurial-vapor-15-academy",
    "name": "Nike Mercurial Vapor 15 Academy FG",
    "brand": "Nike",
    "gender": "male",
    "base_price": 2899000,
    "description": "Mau giay da bong toc do cao voi Vaporposite upper va ACC technology dam bao bam bong trong moi dieu kien thoi tiet.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "toc-do",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 198,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Vaporposite+ upper",
      "midsole_technology": "Soleplate nhe",
      "outsole_technology": "FG stud configuration"
    },
    "images": [
      {
        "image_url": "https://www.svsports.com/cdn/shop/files/dj5631-040.jpg?v=1704742683",
        "alt_text": "Nike Mercurial Vapor 15 Academy FG Black Chrome",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Black/Chrome",
        "sku": "NK-MV15A-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/Chrome",
        "sku": "NK-MV15A-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Chrome",
        "sku": "NK-MV15A-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Chrome",
        "sku": "NK-MV15A-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Chrome",
        "sku": "NK-MV15A-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-phantom-gx-2-academy",
    "name": "Nike Phantom GX 2 Academy FG",
    "brand": "Nike",
    "gender": "male",
    "base_price": 3199000,
    "description": "Giay da bong Phantom GX 2 voi upper Gripknit tao luc bam bong chinh xac va do bam dinh tot tren mat co tu nhien.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 210,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Gripknit upper",
      "midsole_technology": "Reactive chassis",
      "outsole_technology": "FG/AG stud"
    },
    "images": [
      {
        "image_url": "https://thirdcoastsoccer.net/cdn/shop/files/hf1609-5.jpg?v=1738257359",
        "alt_text": "Nike Phantom GX 2 Academy FG Blue Void",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Blue Void/White",
        "sku": "NK-PGX2A-39",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Blue Void/White",
        "sku": "NK-PGX2A-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Blue Void/White",
        "sku": "NK-PGX2A-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Blue Void/White",
        "sku": "NK-PGX2A-42",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-air-zoom-structure-25",
    "name": "Nike Air Zoom Structure 25",
    "brand": "Nike",
    "gender": "male",
    "base_price": 3999000,
    "description": "Giay chay bo ho tro cat chinh (stability), Air Zoom va foam midsole cung cap dam lot va dieu chinh tu ban chan khi chay duong dai.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "stability",
      "drop_mm": 10.0,
      "weight_grams": 311,
      "is_waterproof": false,
      "is_reflective": true,
      "upper_material": "Engineered mesh",
      "midsole_technology": "Air Zoom + foam",
      "outsole_technology": "Crash rail outsole"
    },
    "images": [
      {
        "image_url": "https://assets.solesense.com/en/images/products/500/nike-air-zoom-structure-25-white-pure-platinum-dj7883-105_1.jpg",
        "alt_text": "Nike Air Zoom Structure 25 White Pure Platinum",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Pure Platinum",
        "sku": "NK-AZS25-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Pure Platinum",
        "sku": "NK-AZS25-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Pure Platinum",
        "sku": "NK-AZS25-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Pure Platinum",
        "sku": "NK-AZS25-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-dunk-low",
    "name": "Nike Dunk Low",
    "brand": "Nike",
    "gender": "unisex",
    "base_price": 2799000,
    "description": "Mau sneaker kinh dien tu san bong ro chuyen sang duong pho, upper da/da tong hop phan manh va de cupsole kem chong truot.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 344,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather/synthetic leather",
      "midsole_technology": "Foam cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/b1bcbca4-e853-4df7-b329-5be3c61ee057/NIKE+DUNK+LOW+RETRO.png",
        "alt_text": "Nike Dunk Low White Black Panda",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Black",
        "sku": "NK-DUNKLO-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "NK-DUNKLO-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black",
        "sku": "NK-DUNKLO-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black",
        "sku": "NK-DUNKLO-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Black",
        "sku": "NK-DUNKLO-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "nike-free-rn-5-next-nature",
    "name": "Nike Free RN 5.0 Next Nature",
    "brand": "Nike",
    "gender": "female",
    "base_price": 2299000,
    "description": "Mau giay chay nu co de uon minh tu nhien (Free technology), upper sieu thoang mat va trong luong nhe, phu hop voi tap luyen nhe.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 231,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Sustainable mesh",
      "midsole_technology": "Free flex grooves",
      "outsole_technology": "Rubber outsole pods"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Nike-Free-Run-50-Summit-White-Pink-2021-W-Product.jpg",
        "alt_text": "Nike Free RN 5.0 Next Nature Pink White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Pink/White",
        "sku": "NK-FRN5-36",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Pink/White",
        "sku": "NK-FRN5-37",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Pink/White",
        "sku": "NK-FRN5-38",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Pink/White",
        "sku": "NK-FRN5-39",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-ultraboost-22",
    "name": "Adidas Ultraboost 22",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 4299000,
    "description": "Mau giay chay bo cao cap cua Adidas voi Boost midsole dem dan hoi cuc manh, Primeknit+ upper om chan va Continental rubber outsole bam duong.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 308,
      "is_waterproof": false,
      "is_reflective": true,
      "upper_material": "Primeknit+",
      "midsole_technology": "Boost foam",
      "outsole_technology": "Continental rubber"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/adidas-Ultra-Boost-22-Triple-Black-Product.jpg",
        "alt_text": "Adidas Ultraboost 22 Core Black Carbon White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Core Black/Carbon/White",
        "sku": "ADI-UB22-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/Carbon/White",
        "sku": "ADI-UB22-41",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/Carbon/White",
        "sku": "ADI-UB22-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Core Black/Carbon/White",
        "sku": "ADI-UB22-43",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Core Black/Carbon/White",
        "sku": "ADI-UB22-44",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-stan-smith",
    "name": "Adidas Stan Smith",
    "brand": "Adidas",
    "gender": "unisex",
    "base_price": 2699000,
    "description": "Mau sneaker tennis kinh dien co tu 1971, upper da trang sach se voi logo stan smith xanh la, tro thanh bieu tuong thoi trang toan cau.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 320,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Full-grain leather",
      "midsole_technology": "OrthoLite sockliner",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/h_840,f_auto,q_auto,fl_lossy,c_fill,g_auto/7ed0855435194229a525aad6009a0497_9366/Stan_Smith_Shoes_White_FX5502_01_standard.jpg",
        "alt_text": "Adidas Stan Smith White Green",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Green",
        "sku": "ADI-SS-39",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Green",
        "sku": "ADI-SS-40",
        "stock": 12,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Green",
        "sku": "ADI-SS-41",
        "stock": 11,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Green",
        "sku": "ADI-SS-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Green",
        "sku": "ADI-SS-43",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-samba-og",
    "name": "Adidas Samba OG",
    "brand": "Adidas",
    "gender": "unisex",
    "base_price": 3099000,
    "description": "Mau giay da bong trong nha chuyen thanh icon duong pho voi upper da mem, lot cao su xanh quen thuoc va phom than thiet de phoi do.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 310,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather + suede",
      "midsole_technology": "Gum rubber midsole",
      "outsole_technology": "Gum rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/adidas-Samba-OG-Cloud-White-Core-Black-Product.jpg",
        "alt_text": "Adidas Samba OG White Black Gum",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Black/Gum",
        "sku": "ADI-SAMBA-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Black/Gum",
        "sku": "ADI-SAMBA-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black/Gum",
        "sku": "ADI-SAMBA-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black/Gum",
        "sku": "ADI-SAMBA-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Black/Gum",
        "sku": "ADI-SAMBA-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-gazelle",
    "name": "Adidas Gazelle",
    "brand": "Adidas",
    "gender": "unisex",
    "base_price": 2999000,
    "description": "Mau giay kinh dien co tu thap nien 60 voi upper suede mem, three-stripes quen thuoc va phom gon nhẹ de mac thuong ngay.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 290,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede upper",
      "midsole_technology": "Cushioned insole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/698e41ae0196408eb16aa7fb008046ad_9366/Gazelle_Schuh_Blau_BB5478_01_standard.jpg",
        "alt_text": "Adidas Gazelle Bold Blue Gum",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 38",
        "color": "Bold Blue/Gum",
        "sku": "ADI-GAZ-38",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Bold Blue/Gum",
        "sku": "ADI-GAZ-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Bold Blue/Gum",
        "sku": "ADI-GAZ-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Bold Blue/Gum",
        "sku": "ADI-GAZ-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Bold Blue/Gum",
        "sku": "ADI-GAZ-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-supernova-rise",
    "name": "Adidas Supernova Rise",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 3299000,
    "description": "Mau giay chay bo daily co cong nghe DREAMSTRIKE+ foam mang lai su dem em nhung van co luc phan hoi tot cho cac buoi tap hang ngay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 293,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "DREAMSTRIKE+ foam",
      "outsole_technology": "Continental rubber outsole"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/f5c909fdfb324e1e84db196916267bb5_9366/Supernova_Rise_2_Running_Shoes_Pink_JQ7687_01_00_standard.jpg",
        "alt_text": "Adidas Supernova Rise Blue White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Blue/White",
        "sku": "ADI-SNR-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Blue/White",
        "sku": "ADI-SNR-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Blue/White",
        "sku": "ADI-SNR-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Blue/White",
        "sku": "ADI-SNR-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Blue/White",
        "sku": "ADI-SNR-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-predator-accuracy-4",
    "name": "Adidas Predator Accuracy.4 FG",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 2699000,
    "description": "Giay da bong Predator voi Hybrid touch upper dem den su kiem soat bong tuyet voi, studs FG bam dat co tu nhien chac chan.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 220,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Hybrid touch upper",
      "midsole_technology": "Lightweight plate",
      "outsole_technology": "FG studs"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/cd50ab2f2dab4d88b4ef13fdd6cf8116_9366/Predator_Club_Flexible_Ground_Football_Boots_Black_IG7760_22_model.jpg",
        "alt_text": "Adidas Predator Accuracy 4 FG Core Black Solar Red",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Core Black/Solar Red",
        "sku": "ADI-PA4-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Core Black/Solar Red",
        "sku": "ADI-PA4-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/Solar Red",
        "sku": "ADI-PA4-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/Solar Red",
        "sku": "ADI-PA4-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Core Black/Solar Red",
        "sku": "ADI-PA4-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-adizero-adios-8",
    "name": "Adidas Adizero Adios 8",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 4899000,
    "description": "Mau giay racing toc do cao voi LIGHTSTRIKE PRO va ENERGYRODS 2.0, trong luong sieu nhe chi 199g va thich hop thi dau marathon.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "toc-do",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 8.5,
      "weight_grams": 199,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Adizero mesh",
      "midsole_technology": "LIGHTSTRIKE PRO + ENERGYRODS 2.0",
      "outsole_technology": "Continental rubber"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/25e8f3cda2ef475ea36f43bb1c943fc2_9366/Adizero_Adios_9_Running_Shoes_Black_JP6315_01_00_standard.jpg",
        "alt_text": "Adidas Adizero Adios 8 Core Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Core Black/White",
        "sku": "ADI-AA8-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/White",
        "sku": "ADI-AA8-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/White",
        "sku": "ADI-AA8-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Core Black/White",
        "sku": "ADI-AA8-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-campus-00s",
    "name": "Adidas Campus 00s",
    "brand": "Adidas",
    "gender": "unisex",
    "base_price": 2899000,
    "description": "Ban nang cap tu Campus classic, co phom de day hon va upper suede mem, mang lai ve dep retro Y2K dang duoc ua chuong trong thoi trang hien dai.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 335,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede upper",
      "midsole_technology": "Chunky rubber midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/adidas-Campus-00s-Core-Black-Product.jpg",
        "alt_text": "Adidas Campus 00s Core Black Gum",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Core Black/Gum",
        "sku": "ADI-C00S-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Core Black/Gum",
        "sku": "ADI-C00S-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/Gum",
        "sku": "ADI-C00S-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/Gum",
        "sku": "ADI-C00S-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Core Black/Gum",
        "sku": "ADI-C00S-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-x-crazyfast-4-fg",
    "name": "Adidas X Crazyfast.4 FG",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 2499000,
    "description": "Giay da bong toc do voi lightweight upper va FG studs cho bam dinh tot, thiet ke khi dong va tich hop de dem mong, la lua chon ly tuong cho thi dau toc do.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 195,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Synthetic upper",
      "midsole_technology": "Lightweight chassis",
      "outsole_technology": "FG studs"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/ac1b58045b764a599d97fbc51034124c_9366/X_Crazyfast.4_Flexible_Ground_Boots_Black_GY7433_22_model.jpg",
        "alt_text": "Adidas X Crazyfast 4 FG Core Black Solar Red",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Core Black/Solar Red",
        "sku": "ADI-XCF4-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Core Black/Solar Red",
        "sku": "ADI-XCF4-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/Solar Red",
        "sku": "ADI-XCF4-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/Solar Red",
        "sku": "ADI-XCF4-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Core Black/Solar Red",
        "sku": "ADI-XCF4-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-nmd-r1",
    "name": "Adidas NMD R1",
    "brand": "Adidas",
    "gender": "unisex",
    "base_price": 3499000,
    "description": "Mau sneaker tien phong voi Boost foam full-length thoai mai, Primeknit upper det va hai khoi cong nang po noi bat la ky hieu nhan biet.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 315,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Primeknit",
      "midsole_technology": "Full-length Boost foam",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/e046557745d745889ba8ad43016f0b2b_9366/NMD_R1_Refined_Shoes_Black_H02343_01_standard.jpg",
        "alt_text": "Adidas NMD R1 Core Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Core Black/White",
        "sku": "ADI-NMDR1-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Core Black/White",
        "sku": "ADI-NMDR1-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/White",
        "sku": "ADI-NMDR1-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/White",
        "sku": "ADI-NMDR1-42",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-copa-pure-4-fg",
    "name": "Adidas Copa Pure.4 FG",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 2299000,
    "description": "Giay da bong Copa voi upper synthetic mat va feel bong tuyet voi, thanh phan kinh dien cho nguoi yeu thich kiem soat bong chinh xac.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 208,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Synthetic upper",
      "midsole_technology": "Lightweight plate",
      "outsole_technology": "FG studs"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/33a178620b184f7aa1e10c9cb88f7588_9366/Copa_Pure.4_Flexible_Ground_Boots_White_GZ2536_22_model.jpg",
        "alt_text": "Adidas Copa Pure 4 FG Core Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Core Black/White",
        "sku": "ADI-CP4-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Core Black/White",
        "sku": "ADI-CP4-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/White",
        "sku": "ADI-CP4-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/White",
        "sku": "ADI-CP4-42",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Core Black/White",
        "sku": "ADI-CP4-43",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-ultraboost-light",
    "name": "Adidas Ultraboost Light",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 4999000,
    "description": "Phien ban Ultraboost sieu nhe voi LIGHT BOOST foam (30% nhe hon Boost truyen thong), Primeknit+ upper va Continental outsole cho chay marathon.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "toc-do",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 270,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Primeknit+",
      "midsole_technology": "LIGHT BOOST foam",
      "outsole_technology": "Continental rubber"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/695b16885c664d7fa2e4848d3fba2ff5_9366/Ultraboost_Light_Shoes_White_GY9352_HM1.jpg",
        "alt_text": "Adidas Ultraboost Light White Silver Metallic",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Silver Metallic",
        "sku": "ADI-UBL-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Silver Metallic",
        "sku": "ADI-UBL-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Silver Metallic",
        "sku": "ADI-UBL-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Silver Metallic",
        "sku": "ADI-UBL-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "White/Silver Metallic",
        "sku": "ADI-UBL-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-forum-low",
    "name": "Adidas Forum Low",
    "brand": "Adidas",
    "gender": "unisex",
    "base_price": 2799000,
    "description": "Mau giay basketball kinh dien tu thap nien 80 voi upper da cung cap, ankle strap noi bat va de cupsole day, la bieu tuong thoi trang retro.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 360,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather upper",
      "midsole_technology": "Die-cut EVA midsole",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/adidas-Forum-Low-White-Royal-Blue-Product.jpg",
        "alt_text": "Adidas Forum Low White Royal Blue",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Royal Blue",
        "sku": "ADI-FMLO-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Royal Blue",
        "sku": "ADI-FMLO-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Royal Blue",
        "sku": "ADI-FMLO-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Royal Blue",
        "sku": "ADI-FMLO-42",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Royal Blue",
        "sku": "ADI-FMLO-43",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "adidas-terrex-trailmaker-2",
    "name": "Adidas Terrex Trailmaker 2",
    "brand": "Adidas",
    "gender": "male",
    "base_price": 3699000,
    "description": "Giay trail running voi TERREX Grip outsole bam duong mon, upper thoang khi co Continental rubber va ho tro chay tren dia hinh go ghe.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "TRAIL"
    ],
    "tag_slugs": [
      "ho-tro",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 340,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Recycled mesh",
      "midsole_technology": "TERREX foam",
      "outsole_technology": "Continental rubber + TERREX grip"
    },
    "images": [
      {
        "image_url": "https://assets.adidas.com/images/w_600%2Cf_auto%2Cq_auto/22e26b760fda488a89d8d554ea515d12_9366/Terrex_Trailmaker_2_GORE-TEX_Hiking_Shoes_Black_IH0618_HM1.jpg",
        "alt_text": "Adidas Terrex Trailmaker 2 Core Black Wonder Steel",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Core Black/Wonder Steel",
        "sku": "ADI-TTM2-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Core Black/Wonder Steel",
        "sku": "ADI-TTM2-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Core Black/Wonder Steel",
        "sku": "ADI-TTM2-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Core Black/Wonder Steel",
        "sku": "ADI-TTM2-43",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Core Black/Wonder Steel",
        "sku": "ADI-TTM2-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-velocity-nitro-3",
    "name": "Puma Velocity Nitro 3",
    "brand": "Puma",
    "gender": "male",
    "base_price": 3399000,
    "description": "Mau giay chay bo hang ngay voi NITRO foam dem em va phan hoi tot, upper thoang mat va trong luong nhe cho chay daily training hieu qua.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 272,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "NITRO foam",
      "outsole_technology": "PUMAGRIP rubber"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/380080/01/sv01/fnd/PNA/fmt/png/Velocity-NITRO%E2%84%A2-3-Men%27s-Running-Shoes",
        "alt_text": "Puma Velocity Nitro 3 Black Silver",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Silver",
        "sku": "PUM-VN3-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Silver",
        "sku": "PUM-VN3-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Silver",
        "sku": "PUM-VN3-42",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Silver",
        "sku": "PUM-VN3-43",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Silver",
        "sku": "PUM-VN3-44",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-suede-classic",
    "name": "Puma Suede Classic XXI",
    "brand": "Puma",
    "gender": "unisex",
    "base_price": 2199000,
    "description": "Mau giay street classic cua Puma tu thap nien 60 voi upper suede mem, logo Formstrip hai ben va phom de bat song ngan cho di hang ngay.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 310,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede upper",
      "midsole_technology": "Foam cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/374915/01/sv01/fnd/PNA/fmt/png/Suede-Classic-XXI-Sneakers",
        "alt_text": "Puma Suede Classic XXI Navy White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Navy/White",
        "sku": "PUM-SUEDECL-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Navy/White",
        "sku": "PUM-SUEDECL-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Navy/White",
        "sku": "PUM-SUEDECL-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Navy/White",
        "sku": "PUM-SUEDECL-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Navy/White",
        "sku": "PUM-SUEDECL-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-future-7-play-fg",
    "name": "Puma Future 7 Play FG",
    "brand": "Puma",
    "gender": "male",
    "base_price": 2299000,
    "description": "Giay da bong Future 7 voi upper FUZIONFIT+ ket hop va tich hop vua vat trong vat vao chan, FG studs cho bam dat chac chan va toc do cao.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "toc-do",
      "ho-tro"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 210,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "FUZIONFIT+ upper",
      "midsole_technology": "Lightweight chassis",
      "outsole_technology": "FG studs"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/107727/01/sv01/fnd/PNA/fmt/png/Future-7-Play-FG/AG-Football-Boots",
        "alt_text": "Puma Future 7 Play FG Blue White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Blue Glimmer/White",
        "sku": "PUM-FUT7-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Blue Glimmer/White",
        "sku": "PUM-FUT7-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Blue Glimmer/White",
        "sku": "PUM-FUT7-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Blue Glimmer/White",
        "sku": "PUM-FUT7-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Blue Glimmer/White",
        "sku": "PUM-FUT7-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-rs-x-efekt",
    "name": "Puma RS-X Efekt",
    "brand": "Puma",
    "gender": "unisex",
    "base_price": 2899000,
    "description": "Mau sneaker chunky cam hung tu Running System nhung nam 80, de day da chieu, upper multi-layer va accent mau sac dep mat cho phong cach duong pho.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 380,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh + leather overlays",
      "midsole_technology": "RS foam midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/390777/01/sv01/fnd/PNA/fmt/png/RS-X-Efekt-Sneakers",
        "alt_text": "Puma RS-X Efekt White Black Red",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Black/Red",
        "sku": "PUM-RSXE-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Black/Red",
        "sku": "PUM-RSXE-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black/Red",
        "sku": "PUM-RSXE-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black/Red",
        "sku": "PUM-RSXE-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-deviate-nitro-2",
    "name": "Puma Deviate Nitro 2",
    "brand": "Puma",
    "gender": "male",
    "base_price": 4199000,
    "description": "Mau giay chay bo cao cap voi NITROFOAM+ va carbon-infused PWRPLATE, trong luong sieu nhe chi 232g, thich hop cho bán marathon và race day.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "toc-do",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 232,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Knit upper",
      "midsole_technology": "NITROFOAM+ + PWRPLATE",
      "outsole_technology": "PUMAGRIP rubber"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/376807/04/sv01/fnd/PNA/fmt/png/Deviate-NITRO%E2%84%A2-2-Running-Shoes",
        "alt_text": "Puma Deviate Nitro 2 Lime Squeeze Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Lime Squeeze/Black",
        "sku": "PUM-DN2-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Lime Squeeze/Black",
        "sku": "PUM-DN2-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Lime Squeeze/Black",
        "sku": "PUM-DN2-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Lime Squeeze/Black",
        "sku": "PUM-DN2-43",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Lime Squeeze/Black",
        "sku": "PUM-DN2-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-court-rider-2",
    "name": "Puma Court Rider 2.0",
    "brand": "Puma",
    "gender": "male",
    "base_price": 1999000,
    "description": "Mau giay basketball mid-top voi cong nghe ProFoam, upper da tong hop va thiết ke high-top truyen thong, phu hop cho choi bong va mac hang ngay.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 345,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Synthetic leather",
      "midsole_technology": "ProFoam midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/377572/01/sv01/fnd/PNA/fmt/png/Court-Rider-2.0-Basketball-Shoes",
        "alt_text": "Puma Court Rider 2.0 White Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "PUM-CR2-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black",
        "sku": "PUM-CR2-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black",
        "sku": "PUM-CR2-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Black",
        "sku": "PUM-CR2-43",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-king-pro-fg",
    "name": "Puma King Pro FG",
    "brand": "Puma",
    "gender": "male",
    "base_price": 2599000,
    "description": "Giay da bong huyen thoai King voi upper da that mem mem, mat can bong tot nhat, da dung qua nhieu the he tien phong tu Pele den thoi hien dai.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 235,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Kangaroo leather",
      "midsole_technology": "Thin EVA",
      "outsole_technology": "FG studs"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/107862/01/sv01/fnd/PNA/fmt/png/KING-PRO-FG/AG-Football-Boots",
        "alt_text": "Puma King Pro FG Black White Gum",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Black/White/Gum",
        "sku": "PUM-KINGPRO-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White/Gum",
        "sku": "PUM-KINGPRO-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White/Gum",
        "sku": "PUM-KINGPRO-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White/Gum",
        "sku": "PUM-KINGPRO-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White/Gum",
        "sku": "PUM-KINGPRO-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-softride-one4all",
    "name": "Puma Softride One4All",
    "brand": "Puma",
    "gender": "unisex",
    "base_price": 1799000,
    "description": "Mau giay di bo thoai mai voi Softride foam dem em tuyet voi, upper thoang mat va trong luong nhe, la lua chon hoan hao cho di bo ca ngay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 265,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Breathable mesh",
      "midsole_technology": "Softride foam",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/377671/01/sv01/fnd/PNA/fmt/png/Softride-One4all-Men%27s-Running-Shoes",
        "alt_text": "Puma Softride One4All Purple White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 38",
        "color": "Purple/White",
        "sku": "PUM-SR14-38",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Purple/White",
        "sku": "PUM-SR14-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Purple/White",
        "sku": "PUM-SR14-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Purple/White",
        "sku": "PUM-SR14-41",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Purple/White",
        "sku": "PUM-SR14-42",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-mayze-leather",
    "name": "Puma Mayze Leather",
    "brand": "Puma",
    "gender": "female",
    "base_price": 2399000,
    "description": "Mau giay platform nu voi upper da mem, de day giam chan va dong dang thon gon, mang lai phong cach nu tinh va thoi trang cho moi outfit.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 340,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather upper",
      "midsole_technology": "Platform foam midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/381983/01/sv01/fnd/PNA/fmt/png/Mayze-Leather-Women%27s-Sneakers",
        "alt_text": "Puma Mayze Leather White Gold",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "White/Gold",
        "sku": "PUM-MAYZE-36",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "White/Gold",
        "sku": "PUM-MAYZE-37",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/Gold",
        "sku": "PUM-MAYZE-38",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/Gold",
        "sku": "PUM-MAYZE-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Gold",
        "sku": "PUM-MAYZE-40",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-x-ray-speed",
    "name": "Puma X-Ray Speed",
    "brand": "Puma",
    "gender": "unisex",
    "base_price": 2099000,
    "description": "Sneaker chunky multi-layer voi RS heritage cam hung, upper phoi nhieu vat lieu tao chieu sau thiet ke, dem xop day cho phong cach street vui mat.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 365,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh + synthetic overlays",
      "midsole_technology": "RS foam",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/384638/01/sv01/fnd/PNA/fmt/png/X-Ray-Speed-Sneakers",
        "alt_text": "Puma X-Ray Speed White Multi",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Multi",
        "sku": "PUM-XRAY-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Multi",
        "sku": "PUM-XRAY-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Multi",
        "sku": "PUM-XRAY-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Multi",
        "sku": "PUM-XRAY-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-magnify-nitro-2",
    "name": "Puma Magnify Nitro 2",
    "brand": "Puma",
    "gender": "male",
    "base_price": 3799000,
    "description": "Mau giay chay day dem voi NITRO foam day, upper Knit thoang khi va phom rong hon cho nguoi co ban chan rong, dem xuat sac cho chay distance.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 310,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "PWRKNIT upper",
      "midsole_technology": "NITRO foam + geometry",
      "outsole_technology": "PUMAGRIP outsole"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/380079/01/sv01/fnd/PNA/fmt/png/Magnify-NITRO%E2%84%A2-2-Women%27s-Running-Shoes",
        "alt_text": "Puma Magnify Nitro 2 Black Violet Pop",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Violet Pop",
        "sku": "PUM-MN2-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Violet Pop",
        "sku": "PUM-MN2-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Violet Pop",
        "sku": "PUM-MN2-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Violet Pop",
        "sku": "PUM-MN2-43",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Violet Pop",
        "sku": "PUM-MN2-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "puma-ultra-5-play-fg",
    "name": "Puma Ultra 5 Play FG",
    "brand": "Puma",
    "gender": "male",
    "base_price": 1999000,
    "description": "Giay da bong Ultra voi upper sieu nhe va phan hoi nhanh, FG studs cho bam dat va thiet ke toi gian giup chay sprint toc do cao.",
    "category_slug": "giay-da-bong",
    "surface_codes": [],
    "tag_slugs": [
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 185,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Lightweight synthetic",
      "midsole_technology": "Thin EVA plate",
      "outsole_technology": "FG studs"
    },
    "images": [
      {
        "image_url": "https://images.puma.com/image/upload/f_auto,q_auto,b_rgb:fafafa,w_600,h_600/global/107480/01/sv01/fnd/PNA/fmt/png/Ultra-5-Play-FG/AG-Football-Boots",
        "alt_text": "Puma Ultra 5 Play FG White Polo Blue",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Polo Blue",
        "sku": "PUM-U5PFG-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Polo Blue",
        "sku": "PUM-U5PFG-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Polo Blue",
        "sku": "PUM-U5PFG-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Polo Blue",
        "sku": "PUM-U5PFG-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Polo Blue",
        "sku": "PUM-U5PFG-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-chuck-taylor-all-star-ox",
    "name": "Converse Chuck Taylor All Star Low",
    "brand": "Converse",
    "gender": "unisex",
    "base_price": 1799000,
    "description": "Mau giay canvas huyen thoai cua Converse, co phom low-top go phoi mau don gian, de cao su va ngon ngu thiet ke bat bien tu 1917.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 350,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "OrthoLite cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Converse-Chuck-Taylor-All-Star-Ox-Black-M9166-Black-Product.jpg",
        "alt_text": "Converse Chuck Taylor All Star Low Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Black/White",
        "sku": "CON-CTASLO-36",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Black/White",
        "sku": "CON-CTASLO-37",
        "stock": 12,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Black/White",
        "sku": "CON-CTASLO-38",
        "stock": 11,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "CON-CTASLO-39",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "CON-CTASLO-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "CON-CTASLO-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "CON-CTASLO-42",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-chuck-taylor-all-star-hi",
    "name": "Converse Chuck Taylor All Star High",
    "brand": "Converse",
    "gender": "unisex",
    "base_price": 1999000,
    "description": "Bien the high-top kinh dien nhat cua Chuck Taylor, co ankle support tu canvas upper cao va phan ankle patch noi bat, bieu tuong phong cach rock.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 380,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "OrthoLite cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://m.media-amazon.com/images/I/7190HG0KizL.jpg",
        "alt_text": "Converse Chuck Taylor All Star High White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "White/White",
        "sku": "CON-CTASHI-36",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "White/White",
        "sku": "CON-CTASHI-37",
        "stock": 11,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/White",
        "sku": "CON-CTASHI-38",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/White",
        "sku": "CON-CTASHI-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/White",
        "sku": "CON-CTASHI-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/White",
        "sku": "CON-CTASHI-41",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-run-star-hike-ox",
    "name": "Converse Run Star Hike Low",
    "brand": "Converse",
    "gender": "unisex",
    "base_price": 2499000,
    "description": "Mau giay co de nui xieu xep noi bat voi luong cao su trang day, tao phong cach bold va bat mat cho nhung ai yeu thich the hien phong cach rieng.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 400,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "Stacked rubber midsole",
      "outsole_technology": "Chunky rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Converse-Run-Star-Hike-Hi-Black-White-Gum-Product.jpg",
        "alt_text": "Converse Run Star Hike Low White Black Gum",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "White/Black/Gum",
        "sku": "CON-RSHOX-36",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "White/Black/Gum",
        "sku": "CON-RSHOX-37",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/Black/Gum",
        "sku": "CON-RSHOX-38",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/Black/Gum",
        "sku": "CON-RSHOX-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Black/Gum",
        "sku": "CON-RSHOX-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black/Gum",
        "sku": "CON-RSHOX-41",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-pro-leather-low",
    "name": "Converse Pro Leather Low",
    "brand": "Converse",
    "gender": "unisex",
    "base_price": 2299000,
    "description": "Phien ban da tu Chuck Taylor dung cho basketball, da tong hop trang mem hon va de bat song co upper cao su, phong cach clean va sang trong.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 355,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather upper",
      "midsole_technology": "OrthoLite cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Converse-Pro-Leather-Ox-White-Red-Product.jpg",
        "alt_text": "Converse Pro Leather Low White Red",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Red",
        "sku": "CON-PROLO-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Red",
        "sku": "CON-PROLO-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Red",
        "sku": "CON-PROLO-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Red",
        "sku": "CON-PROLO-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Red",
        "sku": "CON-PROLO-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-chuck-70-hi",
    "name": "Converse Chuck 70 High Top",
    "brand": "Converse",
    "gender": "unisex",
    "base_price": 2799000,
    "description": "Phien ban cao cap cua Chuck Taylor voi dat lieu va cong nghe tot hon, canvas day hon, de cao su dày hon va OrthoLite dem tot hon cho su thoai mai.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 365,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Premium canvas",
      "midsole_technology": "OrthoLite insole",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Converse-Chuck-Taylor-All-Star-70-Hi-AT-CX-Black-Egret-Black-Product.jpg",
        "alt_text": "Converse Chuck 70 Hi Egret Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 37",
        "color": "Egret/Black",
        "sku": "CON-C70HI-37",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Egret/Black",
        "sku": "CON-C70HI-38",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Egret/Black",
        "sku": "CON-C70HI-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Egret/Black",
        "sku": "CON-C70HI-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Egret/Black",
        "sku": "CON-C70HI-41",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Egret/Black",
        "sku": "CON-C70HI-42",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-all-star-bb-shift",
    "name": "Converse All Star BB Shift CX",
    "brand": "Converse",
    "gender": "male",
    "base_price": 3299000,
    "description": "Mau giay basketball hien dai cua Converse, upper mid cao hon, dem React foam Ben chac hon va thiet ke lay cam hung tu san bong ro chuyen nghiep.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 390,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas + textile",
      "midsole_technology": "React cushioning",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://m.media-amazon.com/images/I/71R0BL4in3L._AC_SR768,1024_.jpg",
        "alt_text": "Converse All Star BB Shift Black Silver",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Silver",
        "sku": "CON-BBSHIFT-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Silver",
        "sku": "CON-BBSHIFT-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Silver",
        "sku": "CON-BBSHIFT-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Silver",
        "sku": "CON-BBSHIFT-43",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Silver",
        "sku": "CON-BBSHIFT-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-chuck-70-plus",
    "name": "Converse Chuck 70 Plus Hi",
    "brand": "Converse",
    "gender": "unisex",
    "base_price": 3099000,
    "description": "Phien ban Chuck 70 voi de day hon, upper canvas dep day va accent mau noi bat, mang lai dang ve platform cao hon cho nhung ai yeu phong cach bold.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 410,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Premium canvas",
      "midsole_technology": "Platform midsole",
      "outsole_technology": "Chunky rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Converse-Chuck-Taylor-All-Star-70-Hi-Keith-Haring-Egret-Product.jpg",
        "alt_text": "Converse Chuck 70 Plus Hi Pale Putty",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Pale Putty/Black",
        "sku": "CON-C70PL-36",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Pale Putty/Black",
        "sku": "CON-C70PL-37",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Pale Putty/Black",
        "sku": "CON-C70PL-38",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Pale Putty/Black",
        "sku": "CON-C70PL-39",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Pale Putty/Black",
        "sku": "CON-C70PL-40",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-one-star-pro-ox",
    "name": "Converse One Star Pro Low",
    "brand": "Converse",
    "gender": "male",
    "base_price": 2099000,
    "description": "Mau giay skate low-top voi upper da luon, de cupsole cao su va dem OrthoLite, ket hop phong cach street va skateboarding tu thap nien 90.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 345,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather upper",
      "midsole_technology": "OrthoLite cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://m.media-amazon.com/images/I/71yj+FWz-zL._AC_SR768,1024_.jpg",
        "alt_text": "Converse One Star Pro Low White Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Black",
        "sku": "CON-OSPRO-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "CON-OSPRO-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black",
        "sku": "CON-OSPRO-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black",
        "sku": "CON-OSPRO-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Black",
        "sku": "CON-OSPRO-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-jack-purcell",
    "name": "Converse Jack Purcell",
    "brand": "Converse",
    "gender": "unisex",
    "base_price": 2399000,
    "description": "Mau giay tennis kinh dien thiet ke boi Jack Purcell tu 1935, co smile toe cap noi bat, upper canvas hoac da va phong cach toi gian dang yeu.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 330,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas/leather upper",
      "midsole_technology": "OrthoLite cushioning",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://m.media-amazon.com/images/I/617q98fm9CL._AC_SR768,1024_.jpg",
        "alt_text": "Converse Jack Purcell White Navy",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 37",
        "color": "White/Navy",
        "sku": "CON-JP-37",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/Navy",
        "sku": "CON-JP-38",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/Navy",
        "sku": "CON-JP-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Navy",
        "sku": "CON-JP-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Navy",
        "sku": "CON-JP-41",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Navy",
        "sku": "CON-JP-42",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "converse-chuck-taylor-all-star-move-ox",
    "name": "Converse Chuck Taylor All Star Move",
    "brand": "Converse",
    "gender": "female",
    "base_price": 2199000,
    "description": "Phien ban nu voi platform trang de cao su xep noi bat, upper canvas thoang mat va phong cach dep nu tinh dang phu hop nhieu outfit.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 395,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "Platform midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://m.media-amazon.com/images/I/71x7v-A7l1L._SX700_.jpg",
        "alt_text": "Converse Chuck Taylor All Star Move White Multi",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "White/Multi",
        "sku": "CON-CTASM-36",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "White/Multi",
        "sku": "CON-CTASM-37",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/Multi",
        "sku": "CON-CTASM-38",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/Multi",
        "sku": "CON-CTASM-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Multi",
        "sku": "CON-CTASM-40",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-old-skool",
    "name": "Vans Old Skool",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 1999000,
    "description": "Mau giay skateboarding kinh dien cua Vans tu 1977, upper canvas/da tong hop voi sidestripe noi bat, de Waffle bam duong tuyet voi cho skate va duong pho.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 355,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas + leather upper",
      "midsole_technology": "Anaheim original foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Old-Skool-Black-White-Product.jpg",
        "alt_text": "Vans Old Skool Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Black/White",
        "sku": "VAN-OS-36",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Black/White",
        "sku": "VAN-OS-37",
        "stock": 12,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Black/White",
        "sku": "VAN-OS-38",
        "stock": 11,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "VAN-OS-39",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "VAN-OS-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "VAN-OS-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "VAN-OS-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-sk8-hi",
    "name": "Vans Sk8-Hi",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 2199000,
    "description": "Mau giay skateboarding high-top kinh dien voi ankle support tot, upper canvas co ngang va de Waffle bam duong cho skate hoac di pho phong cach.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 375,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas + suede upper",
      "midsole_technology": "Padded collar foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Sk8-Hi-MTE-NASA-Space-Voyager-True-White-Product.jpg",
        "alt_text": "Vans Sk8-Hi True White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "True White",
        "sku": "VAN-SK8HI-36",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "True White",
        "sku": "VAN-SK8HI-37",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "True White",
        "sku": "VAN-SK8HI-38",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "True White",
        "sku": "VAN-SK8HI-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "True White",
        "sku": "VAN-SK8HI-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "True White",
        "sku": "VAN-SK8HI-41",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-authentic",
    "name": "Vans Authentic",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 1599000,
    "description": "Mau giay dat nen cho Vans tu 1966, upper canvas mong nhe va de Waffle phat san cuong, thiet ke toi gian nhat cua Vans va van la bieu tuong thoi trang.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 305,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "Foxing tape foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Authentic-Black-White-Product.jpg",
        "alt_text": "Vans Authentic Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Black/White",
        "sku": "VAN-AUTH-36",
        "stock": 12,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Black/White",
        "sku": "VAN-AUTH-37",
        "stock": 13,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Black/White",
        "sku": "VAN-AUTH-38",
        "stock": 12,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "VAN-AUTH-39",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "VAN-AUTH-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "VAN-AUTH-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "VAN-AUTH-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-slip-on",
    "name": "Vans Slip-On",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 1699000,
    "description": "Mau giay khong day kinh dien cua Vans, de on chân tuc thi nho elasticated gore hai ben, phom dep gon va style toi gian sang trong.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 285,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "Elastic side accents foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Classic-Slip-On-Black-Product.jpg",
        "alt_text": "Vans Slip-On Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Black",
        "sku": "VAN-SLIP-36",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Black",
        "sku": "VAN-SLIP-37",
        "stock": 11,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Black",
        "sku": "VAN-SLIP-38",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Black",
        "sku": "VAN-SLIP-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black",
        "sku": "VAN-SLIP-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black",
        "sku": "VAN-SLIP-41",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-era",
    "name": "Vans Era",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 1699000,
    "description": "Mau giay skateboarding phat trien tu Old Skool, co padded collar dem hon, upper canvas/suede va de Waffle, phu hop cho skate hang ngay va di pho.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 315,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas/suede upper",
      "midsole_technology": "Padded collar foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://m.media-amazon.com/images/I/91rqtYRGtES.jpg",
        "alt_text": "Vans Era Navy White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Navy/White",
        "sku": "VAN-ERA-36",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Navy/White",
        "sku": "VAN-ERA-37",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Navy/White",
        "sku": "VAN-ERA-38",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Navy/White",
        "sku": "VAN-ERA-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Navy/White",
        "sku": "VAN-ERA-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Navy/White",
        "sku": "VAN-ERA-41",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-ua-comfycush-old-skool",
    "name": "Vans ComfyCush Old Skool",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 2299000,
    "description": "Bien the Old Skool nang cap voi UltraCush foam dem em hon nhieu, upper giong he nhu goc nhung thoai mai hon cho di hang ngay nhieu gio.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 340,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas + leather upper",
      "midsole_technology": "UltraCush foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Old-Skool-Black-White-Product.jpg",
        "alt_text": "Vans ComfyCush Old Skool White White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "White/White",
        "sku": "VAN-CCOLD-36",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "White/White",
        "sku": "VAN-CCOLD-37",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/White",
        "sku": "VAN-CCOLD-38",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/White",
        "sku": "VAN-CCOLD-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/White",
        "sku": "VAN-CCOLD-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/White",
        "sku": "VAN-CCOLD-41",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-ultrarange-exo",
    "name": "Vans UltraRange Exo",
    "brand": "Vans",
    "gender": "male",
    "base_price": 2799000,
    "description": "Mau giay outdoor lifestyle voi UltraRange foam dem sieu em, upper thoang mat va de Waffle bien the bam duong tot, phu hop di pho va hoat dong nhe.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 298,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Textile upper",
      "midsole_technology": "UltraRange foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://us.rollersnakes.com/cdn/shop/files/vans-ultrarange-exo-mte-1-shoes-black-marshmallow-01.jpg?v=1746541411",
        "alt_text": "Vans UltraRange Exo Marshmallow Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Marshmallow/Black",
        "sku": "VAN-UREXO-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Marshmallow/Black",
        "sku": "VAN-UREXO-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Marshmallow/Black",
        "sku": "VAN-UREXO-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Marshmallow/Black",
        "sku": "VAN-UREXO-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Marshmallow/Black",
        "sku": "VAN-UREXO-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-knu-skool",
    "name": "Vans Knu Skool",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 2499000,
    "description": "Bien the Old Skool voi de day hon mang lai phong cach chunky dang trending, upper canvas/da tong hop day dan va de foam cao hon tao dang khac biet.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 370,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas + synthetic leather",
      "midsole_technology": "Thick Waffle foam",
      "outsole_technology": "Chunky Waffle outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Knu-Skool-Black-White-Product.jpg",
        "alt_text": "Vans Knu Skool Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Black/White",
        "sku": "VAN-KNUSK-36",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Black/White",
        "sku": "VAN-KNUSK-37",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Black/White",
        "sku": "VAN-KNUSK-38",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "VAN-KNUSK-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "VAN-KNUSK-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "VAN-KNUSK-41",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-half-cab",
    "name": "Vans Half Cab",
    "brand": "Vans",
    "gender": "male",
    "base_price": 2199000,
    "description": "Mau giay skate signature cua Steve Caballero, bien the half-cut tu Cab high-top, upper suede ben và de Waffle cho skateboarding chinh hieu.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 355,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede upper",
      "midsole_technology": "Padded collar foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Skate-Half-Cab-Black-White-Product.jpg",
        "alt_text": "Vans Half Cab Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "VAN-HCAB-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "VAN-HCAB-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "VAN-HCAB-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "VAN-HCAB-42",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "VAN-HCAB-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-sk8-low",
    "name": "Vans Sk8-Low",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 1999000,
    "description": "Bien the low-top cua Sk8-Hi hieu nang, giu lai upper suede/canvas ben nhung col thap hon de linh hoat hon, de Waffle bam duong chuan skate.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 325,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas + suede upper",
      "midsole_technology": "Foam cushioning",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Sk8-Low-Black-White-Product.jpg",
        "alt_text": "Vans Sk8-Low Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "Black/White",
        "sku": "VAN-SK8LO-36",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "Black/White",
        "sku": "VAN-SK8LO-37",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "Black/White",
        "sku": "VAN-SK8LO-38",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "VAN-SK8LO-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "VAN-SK8LO-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "VAN-SK8LO-41",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-ward",
    "name": "Vans Ward",
    "brand": "Vans",
    "gender": "male",
    "base_price": 1799000,
    "description": "Mau giay casual toi gian cua Vans co phom gan giang Old Skool nhung nhe hon, canvas thoang mat va de Waffle kem chong truot.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 320,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "Foam cushioning",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Old-Skool-Black-White-Product.jpg",
        "alt_text": "Vans Ward Navy White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Navy/White",
        "sku": "VAN-WARD-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Navy/White",
        "sku": "VAN-WARD-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Navy/White",
        "sku": "VAN-WARD-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Navy/White",
        "sku": "VAN-WARD-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Navy/White",
        "sku": "VAN-WARD-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "vans-filmore-decon",
    "name": "Vans Filmore Decon",
    "brand": "Vans",
    "gender": "unisex",
    "base_price": 1899000,
    "description": "Mau giay vulcanized thiet ke thoang hon voi upper canvas mi xeo va de Waffle dat nen truyen thong, phu hop cho nguoi yeu style minimize.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 295,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Canvas upper",
      "midsole_technology": "OrthoLite foam",
      "outsole_technology": "Waffle rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Vans-Skate-Half-Cab-Black-White-Product.jpg",
        "alt_text": "Vans Filmore Decon White Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "White/Black",
        "sku": "VAN-FILMD-36",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "White/Black",
        "sku": "VAN-FILMD-37",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/Black",
        "sku": "VAN-FILMD-38",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/Black",
        "sku": "VAN-FILMD-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "VAN-FILMD-40",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-fresh-foam-x-1080v13",
    "name": "New Balance Fresh Foam X 1080v13",
    "brand": "New Balance",
    "gender": "male",
    "base_price": 4999000,
    "description": "Flagship daily trainer cua New Balance voi Fresh Foam X midsole sieu em, upper Hypoknit mang lai su bao boc va chay daily run cao cap.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 299,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Hypoknit upper",
      "midsole_technology": "Fresh Foam X midsole",
      "outsole_technology": "TA Ride rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/m1080k13_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance Fresh Foam X 1080v13 Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "NB-FF1080-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "NB-FF1080-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "NB-FF1080-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "NB-FF1080-43",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/White",
        "sku": "NB-FF1080-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-574",
    "name": "New Balance 574",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 2799000,
    "description": "Mau sneaker lifestyle kinh dien nhat cua New Balance, upper suede/mesh khoa nhau dep mat, encap midsole dem em va logo NB noi bat tren hai ben.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 360,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede + mesh upper",
      "midsole_technology": "ENCAP midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/ml574evg_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance 574 Grey White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Grey/White",
        "sku": "NB-574-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Grey/White",
        "sku": "NB-574-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Grey/White",
        "sku": "NB-574-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Grey/White",
        "sku": "NB-574-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Grey/White",
        "sku": "NB-574-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-990v6",
    "name": "New Balance 990v6",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 6299000,
    "description": "Flagship lifestyle cua New Balance Made in USA, upper suede/mesh premium, ENCAP va Blown rubber midsole, chieu cao 30+ nam lich su doan hang hang.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 377,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede + mesh premium",
      "midsole_technology": "ENCAP + blown rubber midsole",
      "outsole_technology": "Carbon rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/m990gl6_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance 990v6 Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Grey/Silver",
        "sku": "NB-990V6-40",
        "stock": 4,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Grey/Silver",
        "sku": "NB-990V6-41",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Grey/Silver",
        "sku": "NB-990V6-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Grey/Silver",
        "sku": "NB-990V6-43",
        "stock": 4,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Grey/Silver",
        "sku": "NB-990V6-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-530",
    "name": "New Balance 530",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 2499000,
    "description": "Mau sneaker retro 90s voi upper mesh/synthetic bac sang, ABZORB midsole dem em va phom runner the thao, dang duoc ua chuong nho ve dep vintage.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 325,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh + synthetic upper",
      "midsole_technology": "ABZORB midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/mr530cc_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance 530 Silver Munsell White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 38",
        "color": "Silver/White",
        "sku": "NB-530-38",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Silver/White",
        "sku": "NB-530-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Silver/White",
        "sku": "NB-530-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Silver/White",
        "sku": "NB-530-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Silver/White",
        "sku": "NB-530-42",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-fresh-foam-x-860v14",
    "name": "New Balance Fresh Foam X 860v14",
    "brand": "New Balance",
    "gender": "male",
    "base_price": 4299000,
    "description": "Mau giay stability chay bo hang ngay voi Fresh Foam X va medial post chay ung tro, co ban chan thong huong trong (overpronation), ideal cho chay long distance.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "stability",
      "drop_mm": 8.0,
      "weight_grams": 315,
      "is_waterproof": false,
      "is_reflective": true,
      "upper_material": "Engineered mesh",
      "midsole_technology": "Fresh Foam X + medial post",
      "outsole_technology": "Blown rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/m860k14_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance Fresh Foam X 860v14 Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "NB-860V14-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "NB-860V14-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "NB-860V14-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "NB-860V14-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/White",
        "sku": "NB-860V14-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-2002r",
    "name": "New Balance 2002R",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 3999000,
    "description": "Mau giay lifestyle premium voi ABZORB SBS midsole va N-ergy technology, upper suede/mesh tinh te, phong cach dad shoe tinh te voi lich su ky thuat thuyet phuc.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 355,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede + mesh upper",
      "midsole_technology": "ABZORB SBS + N-ergy",
      "outsole_technology": "Carbon rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/m2002rca_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance 2002R Protection Pack Quartz Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Quartz Grey",
        "sku": "NB-2002R-39",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Quartz Grey",
        "sku": "NB-2002R-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Quartz Grey",
        "sku": "NB-2002R-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Quartz Grey",
        "sku": "NB-2002R-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Quartz Grey",
        "sku": "NB-2002R-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-fresh-foam-x-680v8",
    "name": "New Balance Fresh Foam X 680v8",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 2699000,
    "description": "Mau giay chay gia re phu hop cho nguoi moi tap chay voi Fresh Foam X dem em, upper thoang mat va trong luong nhe, la lua chon khoi diem hoan hao.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 283,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh upper",
      "midsole_technology": "Fresh Foam X midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/w680lk8_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance Fresh Foam X 680v8 White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 36",
        "color": "White/Teal",
        "sku": "NB-680V8-36",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 37",
        "color": "White/Teal",
        "sku": "NB-680V8-37",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 38",
        "color": "White/Teal",
        "sku": "NB-680V8-38",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/Teal",
        "sku": "NB-680V8-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Teal",
        "sku": "NB-680V8-40",
        "stock": 6,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-327",
    "name": "New Balance 327",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 2999000,
    "description": "Mau sneaker lay cam hung tu dong chay duong mon nhung nam 70, phom sole dai keu va upper suede/mesh ket hop tao dang ve retro doc dao kho nham.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 340,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede + mesh upper",
      "midsole_technology": "EVA midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/ms327cpg_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance 327 White Navy",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 38",
        "color": "White/Navy",
        "sku": "NB-327-38",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/Navy",
        "sku": "NB-327-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Navy",
        "sku": "NB-327-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Navy",
        "sku": "NB-327-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Navy",
        "sku": "NB-327-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-rc-elite-v3",
    "name": "New Balance RC Elite v3",
    "brand": "New Balance",
    "gender": "male",
    "base_price": 5999000,
    "description": "Mau giay racing marathon toc do cao voi FuelCell foam va carbon fiber plate, trong luong chi 196g va thiet ke toi uu hoa ky thuat chay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 196,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Knit upper",
      "midsole_technology": "FuelCell + carbon fiber plate",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/mrcelct3_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance RC Elite v3 Fuel Cell White Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "NB-RCELITEV3-40",
        "stock": 4,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black",
        "sku": "NB-RCELITEV3-41",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black",
        "sku": "NB-RCELITEV3-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Black",
        "sku": "NB-RCELITEV3-43",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "White/Black",
        "sku": "NB-RCELITEV3-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-550",
    "name": "New Balance 550",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 3299000,
    "description": "Mau giay basketball retro tu 1989 duoc hoi sinh, upper da trang sach se voi logo NB, phom cupsole cao su day va phong cach clean thich hop phoi nhieu outfit.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 365,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Leather upper",
      "midsole_technology": "Die-cut EVA midsole",
      "outsole_technology": "Rubber cupsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/bb550wt1_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance 550 White Sea Salt",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/Sea Salt",
        "sku": "NB-550-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/Sea Salt",
        "sku": "NB-550-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Sea Salt",
        "sku": "NB-550-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Sea Salt",
        "sku": "NB-550-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Sea Salt",
        "sku": "NB-550-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-minimus-trail",
    "name": "New Balance Minimus Trail",
    "brand": "New Balance",
    "gender": "male",
    "base_price": 2999000,
    "description": "Mau giay trail minimalist voi de mong cai thien ban the chan, trong luong sieu nhe, upper thoang mat bam trail bat ki dieu kien.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "TRAIL"
    ],
    "tag_slugs": [
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "low",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 219,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh upper",
      "midsole_technology": "Vibram outsole",
      "outsole_technology": "Vibram trail grip"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/mt10ops_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance Minimus Trail Black Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Grey",
        "sku": "NB-MINTR-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Grey",
        "sku": "NB-MINTR-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Grey",
        "sku": "NB-MINTR-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Grey",
        "sku": "NB-MINTR-43",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Grey",
        "sku": "NB-MINTR-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-1906r",
    "name": "New Balance 1906R",
    "brand": "New Balance",
    "gender": "unisex",
    "base_price": 4299000,
    "description": "Mau sneaker duoc hoi sinh tu mau running 2006 voi ABZORB DTS heel va N-ergy forefoot, phom retro chunky va detail ky thuat kem theo gia tri thiet ke cao.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 378,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh + suede overlays",
      "midsole_technology": "ABZORB DTS + N-ergy",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://nb.scene7.com/is/image/NB/m1906rca_nb_02_i?$pdpflexf2$&fmt=webp&wid=440&hei=440",
        "alt_text": "New Balance 1906R Protection Pack Sea Salt",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Sea Salt/White",
        "sku": "NB-1906R-39",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Sea Salt/White",
        "sku": "NB-1906R-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Sea Salt/White",
        "sku": "NB-1906R-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Sea Salt/White",
        "sku": "NB-1906R-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Sea Salt/White",
        "sku": "NB-1906R-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "new-balance-fresh-foam-x-more-v4",
    "name": "New Balance Fresh Foam X More v4",
    "brand": "New Balance",
    "gender": "male",
    "base_price": 3799000,
    "description": "Mau giay max-cushion voi Fresh Foam X day nhat trong dang, ideal cho chay ultra distance hoac nguoi can de bao ve khop khi chay nhieu km.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 318,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "Fresh Foam X Extra",
      "outsole_technology": "Blown rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/New-Balance-Fresh-Foam-More-V4-Black-Product.jpg",
        "alt_text": "New Balance Fresh Foam X More v4 Black Thunder",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Thunder",
        "sku": "NB-MOREV4-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Thunder",
        "sku": "NB-MOREV4-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Thunder",
        "sku": "NB-MOREV4-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Thunder",
        "sku": "NB-MOREV4-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Thunder",
        "sku": "NB-MOREV4-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-kayano-31",
    "name": "ASICS Gel-Kayano 31",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 4999000,
    "description": "Flagship stability shoe cua ASICS voi GEL technology va FF BLAST+ ECO foam, ung tro chay cat chinh rat tot va dam lot xuat sac cho chay long distance.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "stability",
      "drop_mm": 10.0,
      "weight_grams": 312,
      "is_waterproof": false,
      "is_reflective": true,
      "upper_material": "LITETRUSS engineered mesh",
      "midsole_technology": "FF BLAST+ ECO foam + GEL",
      "outsole_technology": "AHAR rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1011B867_302_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Kayano 31 Black Pure Silver",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Pure Silver",
        "sku": "ASI-GK31-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Pure Silver",
        "sku": "ASI-GK31-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Pure Silver",
        "sku": "ASI-GK31-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Pure Silver",
        "sku": "ASI-GK31-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Pure Silver",
        "sku": "ASI-GK31-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-nimbus-26",
    "name": "ASICS Gel-Nimbus 26",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 5499000,
    "description": "Mau giay max-cushion cua ASICS voi PureGEL technology va FF BLAST+ foam, dem lot xuyen suot hanh trinh chay duong dai khong met moi.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 320,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered knit mesh",
      "midsole_technology": "FF BLAST+ foam + PureGEL",
      "outsole_technology": "AHAR+ rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1011B794_003_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Nimbus 26 Moonrock Smoke Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Moonrock/Smoke Grey",
        "sku": "ASI-GN26-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Moonrock/Smoke Grey",
        "sku": "ASI-GN26-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Moonrock/Smoke Grey",
        "sku": "ASI-GN26-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Moonrock/Smoke Grey",
        "sku": "ASI-GN26-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Moonrock/Smoke Grey",
        "sku": "ASI-GN26-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-cumulus-26",
    "name": "ASICS Gel-Cumulus 26",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 3999000,
    "description": "Daily trainer cua ASICS voi GEL va FF BLAST foam dem lot tot, upper khoang mat va on dinh, la lua chon bang giao trong dang training hang ngay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 292,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "FF BLAST foam + GEL",
      "outsole_technology": "AHAR rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1011B792_001_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Cumulus 26 Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "ASI-GC26-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "ASI-GC26-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "ASI-GC26-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "ASI-GC26-43",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/White",
        "sku": "ASI-GC26-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-lite-lyte-3",
    "name": "ASICS Gel-Lyte III",
    "brand": "ASICS",
    "gender": "unisex",
    "base_price": 3499000,
    "description": "Mau sneaker lifestyle icon cua ASICS voi split tongue noi bat, upper suede/nylon ket hop va GEL cushioning, bien tu san chay bo sang duong pho.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 305,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Suede + nylon upper",
      "midsole_technology": "GEL cushioning",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1201A257_100_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Lyte III White White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "White/White",
        "sku": "ASI-GL3-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/White",
        "sku": "ASI-GL3-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/White",
        "sku": "ASI-GL3-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/White",
        "sku": "ASI-GL3-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/White",
        "sku": "ASI-GL3-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-metaspeed-sky-paris",
    "name": "ASICS Metaspeed Sky+",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 7499000,
    "description": "Mau giay marathon cao cap nhat ASICS voi FLYTEFOAM Blast Turbo va carbon fiber plate, trong luong 171g, thiet ke for cadence runner dat ton thi dau.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 171,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "ASICSFOAM ENGINEERED upper",
      "midsole_technology": "FLYTEFOAM Blast Turbo + carbon plate",
      "outsole_technology": "Rubber compound outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1013A115_100_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Metaspeed Sky+ White Black",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Black",
        "sku": "ASI-MSKY-40",
        "stock": 3,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Black",
        "sku": "ASI-MSKY-41",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Black",
        "sku": "ASI-MSKY-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Black",
        "sku": "ASI-MSKY-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-venture-9",
    "name": "ASICS Gel-Venture 9",
    "brand": "ASICS",
    "gender": "unisex",
    "base_price": 2099000,
    "description": "Mau giay trail chay bo gia re tiep can, GEL cushioning, de ngoai co bat do co ket hop bam duong mon tot, phu hop cho chay nhe tren duong dat.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "TRAIL"
    ],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 310,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh upper",
      "midsole_technology": "GEL cushioning",
      "outsole_technology": "Duomax + Trail rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1011B486_001_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Venture 9 Black Gunmetal",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Black/Gunmetal",
        "sku": "ASI-GV9-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/Gunmetal",
        "sku": "ASI-GV9-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Gunmetal",
        "sku": "ASI-GV9-41",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Gunmetal",
        "sku": "ASI-GV9-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Gunmetal",
        "sku": "ASI-GV9-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gt-2000-13",
    "name": "ASICS GT-2000 13",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 3699000,
    "description": "Mau giay stability chay bo hang ngay, GEL technology kep truoc va sau, FF BLAST foam dem on dinh va ung tro chay flat foot hieu qua.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "stability",
      "drop_mm": 8.0,
      "weight_grams": 288,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "FF BLAST foam + GEL + Litetruss",
      "outsole_technology": "AHAR rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1012B666_400_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS GT-2000 13 Blue Expanse Silver",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Blue/Silver",
        "sku": "ASI-GT2K13-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Blue/Silver",
        "sku": "ASI-GT2K13-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Blue/Silver",
        "sku": "ASI-GT2K13-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Blue/Silver",
        "sku": "ASI-GT2K13-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Blue/Silver",
        "sku": "ASI-GT2K13-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-ds-trainer-28",
    "name": "ASICS Gel-DS Trainer 28",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 4299000,
    "description": "Mau giay racing trainer kep upper, GEL va FF BLAST foam tao lu phan hoi tot va dem em nhe de race ngan va chay toc do cao.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "toc-do",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 248,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "FF BLAST foam + GEL",
      "outsole_technology": "AHAR rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1203A608_001_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-DS Trainer 28 Black Carrier Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Carrier Grey",
        "sku": "ASI-DST28-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Carrier Grey",
        "sku": "ASI-DST28-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Carrier Grey",
        "sku": "ASI-DST28-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Carrier Grey",
        "sku": "ASI-DST28-43",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Carrier Grey",
        "sku": "ASI-DST28-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-1090v2",
    "name": "ASICS Gel-1090v2",
    "brand": "ASICS",
    "gender": "unisex",
    "base_price": 3199000,
    "description": "Mau sneaker lifestyle di gua tu dong running nhung nam 90 voi GEL cushioning, mesh/suede combo thoang mat va phom chunky retro dang duoc yeu thich.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 350,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh + suede overlays",
      "midsole_technology": "GEL cushioning",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1203A224_100_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-1090v2 White White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 38",
        "color": "White/White",
        "sku": "ASI-G1090-38",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "White/White",
        "sku": "ASI-G1090-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "White/White",
        "sku": "ASI-G1090-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/White",
        "sku": "ASI-G1090-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/White",
        "sku": "ASI-G1090-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-excite-10",
    "name": "ASICS Gel-Excite 10",
    "brand": "ASICS",
    "gender": "unisex",
    "base_price": 2299000,
    "description": "Mau giay chay bo gia me phu hop cho nguoi moi bat dau, GEL cushioning, upper thoang mat va trong luong nhe, la nen tang cho cuoc hanh trinh chay bo.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 10.0,
      "weight_grams": 278,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Breathable mesh",
      "midsole_technology": "GEL cushioning + AMPLIFOAM midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1011B600_002_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Excite 10 Blue White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Blue/White",
        "sku": "ASI-GE10-39",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Blue/White",
        "sku": "ASI-GE10-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Blue/White",
        "sku": "ASI-GE10-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Blue/White",
        "sku": "ASI-GE10-42",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Blue/White",
        "sku": "ASI-GE10-43",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-sonoma-7",
    "name": "ASICS Gel-Sonoma 7",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 2799000,
    "description": "Mau giay trail running voi GEL cushioning va AMPLIFOAM, de ngoai co kia trail co bat tren duong mon, upper chong nuoc nhe.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "TRAIL"
    ],
    "tag_slugs": [
      "ho-tro",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 320,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Mesh upper + overlays",
      "midsole_technology": "GEL + AMPLIFOAM",
      "outsole_technology": "Trail rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1011B593_002_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Sonoma 7 Black Carrier Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Grey",
        "sku": "ASI-GS7-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Grey",
        "sku": "ASI-GS7-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Grey",
        "sku": "ASI-GS7-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Grey",
        "sku": "ASI-GS7-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Grey",
        "sku": "ASI-GS7-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-quantum-360-7",
    "name": "ASICS Gel-Quantum 360 7",
    "brand": "ASICS",
    "gender": "unisex",
    "base_price": 4299000,
    "description": "Mau giay lifestyle voi 360-degree GEL cushioning xung quanh de, cung cap dam lot toan dien, upper textile sang trong va phom chunky noi bat.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 390,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Textile upper",
      "midsole_technology": "360° GEL cushioning",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1201A482_020_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Quantum 360 7 Graphite Grey",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 38",
        "color": "Graphite Grey/White",
        "sku": "ASI-GQ360-38",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Graphite Grey/White",
        "sku": "ASI-GQ360-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Graphite Grey/White",
        "sku": "ASI-GQ360-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Graphite Grey/White",
        "sku": "ASI-GQ360-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Graphite Grey/White",
        "sku": "ASI-GQ360-42",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "asics-gel-resolution-9",
    "name": "ASICS Gel-Resolution 9",
    "brand": "ASICS",
    "gender": "male",
    "base_price": 3999000,
    "description": "Mau giay tennis cao cap voi GEL cushioning va AHARPLUS outsole sieu ben, ung tro side-to-side movement va on dinh chuyen dong tren san tennis.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "ho-tro",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 8.0,
      "weight_grams": 339,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "FlyteFoam upper",
      "midsole_technology": "GEL cushioning",
      "outsole_technology": "AHARPLUS rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.asics.com/is/image/asics/1041A453_100_SR_RT_GLB?$zoom$",
        "alt_text": "ASICS Gel-Resolution 9 White Pure Silver",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "White/Silver",
        "sku": "ASI-GR9-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "White/Silver",
        "sku": "ASI-GR9-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "White/Silver",
        "sku": "ASI-GR9-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "White/Silver",
        "sku": "ASI-GR9-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "White/Silver",
        "sku": "ASI-GR9-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-clifton-9",
    "name": "Hoka Clifton 9",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 4499000,
    "description": "Mau daily trainer bieu tuong cua Hoka voi midsole day va nhe, upper khoang khi thoang mat va trong luong chi 243g, mang lai buoc chan bay bong.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "em-ai",
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 243,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "Compression-molded EVA midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://images.stockx.com/images/Hoka-One-One-Clifton-9-Black-White-Product.jpg",
        "alt_text": "Hoka Clifton 9 Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "HOK-CL9-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "HOK-CL9-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "HOK-CL9-42",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "HOK-CL9-43",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/White",
        "sku": "HOK-CL9-44",
        "stock": 5,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-bondi-8",
    "name": "Hoka Bondi 8",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 5299000,
    "description": "Mau giay max-cushion cua Hoka, de EVA day nhat trong dang voi cong nghe Meta-Rocker bao ve khop chan hieu qua, ideal cho chay ultra va di bo ca ngay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 301,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "Full-length EVA midsole + Meta-Rocker",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.nz.hoka.com/products/cd369f11-c99f-4b4e-8d99-de66917329ca/2a025844/1123202-bckrn_bckrn_01.jpg",
        "alt_text": "Hoka Bondi 8 Bellwether Blue Castlerock",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Bellwether Blue/Castlerock",
        "sku": "HOK-BD8-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Bellwether Blue/Castlerock",
        "sku": "HOK-BD8-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Bellwether Blue/Castlerock",
        "sku": "HOK-BD8-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Bellwether Blue/Castlerock",
        "sku": "HOK-BD8-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Bellwether Blue/Castlerock",
        "sku": "HOK-BD8-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-rincon-3",
    "name": "Hoka Rincon 3",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 3799000,
    "description": "Mau giay training toc do nhe nhat cua Hoka, midsole EVA phan hoi tot va trong luong chi 220g, ly tuong cho chay speed workout va race ngắn.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "toc-do",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 220,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Lightweight engineered mesh",
      "midsole_technology": "Single-density EVA midsole",
      "outsole_technology": "Lightweight rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/820316ad-9e0e-4d23-b4ff-dc76e2792c57/2bef7dde/1119395-bwht_bwht_02.jpg",
        "alt_text": "Hoka Rincon 3 Goblin Blue Tofu",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Goblin Blue/Tofu",
        "sku": "HOK-RC3-40",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Goblin Blue/Tofu",
        "sku": "HOK-RC3-41",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Goblin Blue/Tofu",
        "sku": "HOK-RC3-42",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Goblin Blue/Tofu",
        "sku": "HOK-RC3-43",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Goblin Blue/Tofu",
        "sku": "HOK-RC3-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-speedgoat-5",
    "name": "Hoka Speedgoat 5",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 4899000,
    "description": "Mau giay trail running bieu tuong cua Hoka, Vibram Megagrip outsole bam co bat tuyet voi tren moi dia hinh, midsole day va Meta-Rocker bao ve.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "TRAIL"
    ],
    "tag_slugs": [
      "ho-tro",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 4.0,
      "weight_grams": 298,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Breathable mesh",
      "midsole_technology": "Midsole EVA + Meta-Rocker",
      "outsole_technology": "Vibram Megagrip outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/af289e08-1de7-4dd5-8213-7aa36a088fab/75c22602/1123157-wncl_wncl_01.jpg",
        "alt_text": "Hoka Speedgoat 5 Black Phantom",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Phantom",
        "sku": "HOK-SG5-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Phantom",
        "sku": "HOK-SG5-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Phantom",
        "sku": "HOK-SG5-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Phantom",
        "sku": "HOK-SG5-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Phantom",
        "sku": "HOK-SG5-44",
        "stock": 3,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-arahi-7",
    "name": "Hoka Arahi 7",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 4199000,
    "description": "Mau giay stability co nhe nhat cua Hoka voi cong nghe J-Frame ung tro cat chinh, midsole sieu dem va cung co duoc cao nhu nghi.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "stability",
      "drop_mm": 5.0,
      "weight_grams": 252,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "CMEVA midsole + J-Frame",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/7e5d315b-fadd-4a16-b186-30d274f6b7be/6bc023aa/1147851-brks_brks_01.jpg",
        "alt_text": "Hoka Arahi 7 Blue Haze All White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Blue Haze/All White",
        "sku": "HOK-AR7-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Blue Haze/All White",
        "sku": "HOK-AR7-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Blue Haze/All White",
        "sku": "HOK-AR7-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Blue Haze/All White",
        "sku": "HOK-AR7-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Blue Haze/All White",
        "sku": "HOK-AR7-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-transport",
    "name": "Hoka Transport",
    "brand": "Hoka",
    "gender": "unisex",
    "base_price": 3499000,
    "description": "Mau giay hiking lifestyle cua Hoka, mang cong nghe midsole day va Meta-Rocker tu running sang duong pho va hiking nhe, thoai mai di ca ngay.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai",
      "ben-bi"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 315,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Recycled mesh upper",
      "midsole_technology": "EVA midsole + Meta-Rocker",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/dd82621c-36c2-44e9-bc22-11f40f17b958/e76c48fa/1123153-bblc_bblc_01.jpg",
        "alt_text": "Hoka Transport Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "HOK-TRANS-39",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "HOK-TRANS-40",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "HOK-TRANS-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "HOK-TRANS-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "HOK-TRANS-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-mach-6",
    "name": "Hoka Mach 6",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 4299000,
    "description": "Phien ban thu 6 cua Mach - mau training toc do cua Hoka voi CMEVA bien the moi phan hoi tot hon, upper khoang khi mong nhe va trong luong 229g.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "toc-do",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 229,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "CMEVA midsole",
      "outsole_technology": "Zonal rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/7765ead5-cce5-4b47-93aa-1b9fc1bbab4b/d6ddac10/1147790-bblc_bblc_01.jpg",
        "alt_text": "Hoka Mach 6 Shifting Sand Stardust",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Shifting Sand/Stardust",
        "sku": "HOK-MACH6-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Shifting Sand/Stardust",
        "sku": "HOK-MACH6-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Shifting Sand/Stardust",
        "sku": "HOK-MACH6-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Shifting Sand/Stardust",
        "sku": "HOK-MACH6-43",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Shifting Sand/Stardust",
        "sku": "HOK-MACH6-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-kawana-2",
    "name": "Hoka Kawana 2",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 4499000,
    "description": "Mau daily trainer moi cua Hoka co Meta-Rocker geometry va foam midsole phan hoi cao, thich hop cho chay distance trung binh hang ngay.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD",
      "TREADMILL"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 265,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "CMEVA midsole + Meta-Rocker",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/061fec7e-ac1a-4a27-9038-053ce22c6ca5/4143b2cb/1147930-sryb_sryb_01.jpg",
        "alt_text": "Hoka Kawana 2 Virtual/Yam",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Virtual/Yam",
        "sku": "HOK-KAW2-40",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Virtual/Yam",
        "sku": "HOK-KAW2-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Virtual/Yam",
        "sku": "HOK-KAW2-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Virtual/Yam",
        "sku": "HOK-KAW2-43",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Virtual/Yam",
        "sku": "HOK-KAW2-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-skyward-x",
    "name": "Hoka Skyward X",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 6499000,
    "description": "Mau giay marathon cao cap nhat Hoka 2024 voi carbon fiber plate va PEBA foam, tich hop cong nghe cao nhat tu Hoka cho vdv elite.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "toc-do"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 232,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "PEBA foam + carbon plate",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/4e3ab4bd-343c-4c4e-af94-71aa0f8b131a/b63b1bab/1147911-cslp_cslp_01.jpg",
        "alt_text": "Hoka Skyward X Fiesta Dusk Crimson Purple",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Fiesta/Dusk Crimson",
        "sku": "HOK-SKYX-40",
        "stock": 3,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Fiesta/Dusk Crimson",
        "sku": "HOK-SKYX-41",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Fiesta/Dusk Crimson",
        "sku": "HOK-SKYX-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Fiesta/Dusk Crimson",
        "sku": "HOK-SKYX-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-torrent-3",
    "name": "Hoka Torrent 3",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 3699000,
    "description": "Mau giay trail running nhe va linh hoat cua Hoka, Vibram outsole bam co dat chac chan va trong luong nhe cho chay trail vua dem vua nhanh.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "TRAIL"
    ],
    "tag_slugs": [
      "toc-do",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "medium",
      "pronation_type": "neutral",
      "drop_mm": 5.0,
      "weight_grams": 256,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Jacquard mesh",
      "midsole_technology": "CMEVA midsole",
      "outsole_technology": "Vibram outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/bbd33a7f-8e3a-47a9-a940-1eafb12fd10b/5615b653/1127915-ccs_ccs_01.jpg",
        "alt_text": "Hoka Torrent 3 Black Dark Shadow",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/Dark Shadow",
        "sku": "HOK-TOR3-40",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/Dark Shadow",
        "sku": "HOK-TOR3-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/Dark Shadow",
        "sku": "HOK-TOR3-42",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/Dark Shadow",
        "sku": "HOK-TOR3-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/Dark Shadow",
        "sku": "HOK-TOR3-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-gaviota-5",
    "name": "Hoka Gaviota 5",
    "brand": "Hoka",
    "gender": "male",
    "base_price": 4799000,
    "description": "Mau giay max-stability cua Hoka voi H-Frame technology ung tro cat chinh manh me nhat trong lineup, ket hop midsole day dem cho nguoi can bao ve nhieu.",
    "category_slug": "giay-chay-bo",
    "surface_codes": [
      "ROAD"
    ],
    "tag_slugs": [
      "ho-tro",
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "stability",
      "drop_mm": 5.0,
      "weight_grams": 288,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Engineered mesh",
      "midsole_technology": "CMEVA + H-Frame",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/d88f6496-b382-403b-9237-cce9b8057b4c/97d2cabf/1127929-bblc_bblc_01.jpg",
        "alt_text": "Hoka Gaviota 5 Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "HOK-GAV5-40",
        "stock": 5,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "HOK-GAV5-41",
        "stock": 7,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "HOK-GAV5-42",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "HOK-GAV5-43",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 44",
        "color": "Black/White",
        "sku": "HOK-GAV5-44",
        "stock": 4,
        "extra_price": 0
      }
    ]
  },
  {
    "slug": "hoka-ora-recovery-shoe-3",
    "name": "Hoka Ora Recovery Shoe 3",
    "brand": "Hoka",
    "gender": "unisex",
    "base_price": 2999000,
    "description": "Giay phuc hoi sau tap luyen cua Hoka, midsole day va mem cho ban chan nghi ngoi tot nhat, phu hop di trong nha sau buoi chay bo dai.",
    "category_slug": "giay-thoi-trang",
    "surface_codes": [],
    "tag_slugs": [
      "em-ai"
    ],
    "specs": {
      "cushioning_level": "high",
      "pronation_type": "neutral",
      "drop_mm": 0.0,
      "weight_grams": 270,
      "is_waterproof": false,
      "is_reflective": false,
      "upper_material": "Breathable mesh upper",
      "midsole_technology": "Extra-thick EVA midsole",
      "outsole_technology": "Rubber outsole"
    },
    "images": [
      {
        "image_url": "https://media.au.hoka.com/products/ec753a86-e418-4e7e-b280-ca52fbbac2ed/86facba3/1135061-bblc_bblc_1.jpg",
        "alt_text": "Hoka Ora Recovery Shoe 3 Black White",
        "variant_sku": null,
        "primary": false,
        "sort_order": 0
      }
    ],
    "variants": [
      {
        "size": "EU 38",
        "color": "Black/White",
        "sku": "HOK-ORA3-38",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 39",
        "color": "Black/White",
        "sku": "HOK-ORA3-39",
        "stock": 9,
        "extra_price": 0
      },
      {
        "size": "EU 40",
        "color": "Black/White",
        "sku": "HOK-ORA3-40",
        "stock": 10,
        "extra_price": 0
      },
      {
        "size": "EU 41",
        "color": "Black/White",
        "sku": "HOK-ORA3-41",
        "stock": 8,
        "extra_price": 0
      },
      {
        "size": "EU 42",
        "color": "Black/White",
        "sku": "HOK-ORA3-42",
        "stock": 6,
        "extra_price": 0
      },
      {
        "size": "EU 43",
        "color": "Black/White",
        "sku": "HOK-ORA3-43",
        "stock": 4,
        "extra_price": 0
      }
    ]
  }
]
JSON, true, 512, JSON_THROW_ON_ERROR);
    }
}
