<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function messages(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'conversationId' => 'nullable|string|max:100',
        ]);

        $message = trim($data['message']);
        $conversationId = $data['conversationId'] ?: (string) Str::uuid();
        $products = $this->suggestProducts($message);

        $reply = $products
            ? 'Mình tìm thấy một số mẫu có thể phù hợp. Bạn có thể mở từng sản phẩm để xem size, màu và tồn kho.'
            : 'Mình đã nhận câu hỏi của bạn. Hiện bản Laravel đang dùng bộ tư vấn nội bộ, bạn có thể hỏi về sản phẩm, size, đổi trả hoặc giao hàng.';

        return $this->ok([
            'reply' => $reply,
            'conversationId' => $conversationId,
            'intent' => $products ? 'PRODUCT_SEARCH' : 'GENERAL_SUPPORT',
            'providerUsed' => false,
            'fallbackUsed' => true,
            'products' => $products,
            'quickReplies' => [
                ['label' => 'Tư vấn size', 'message' => 'Tôi cần tư vấn size giày'],
                ['label' => 'Chính sách đổi trả', 'message' => 'Chính sách đổi trả như thế nào?'],
                ['label' => 'Phí giao hàng', 'message' => 'Phí giao hàng được tính ra sao?'],
            ],
            'pendingAction' => null,
        ]);
    }

    private function suggestProducts(string $message): array
    {
        $keyword = collect(preg_split('/\s+/', Str::lower($message)))
            ->filter(fn($part) => Str::length($part) >= 3)
            ->take(5)
            ->values();

        $query = Product::with(['images', 'variants'])
            ->whereNull('deleted_at')
            ->limit(4);

        if ($keyword->isNotEmpty()) {
            $query->where(function ($inner) use ($keyword) {
                foreach ($keyword as $term) {
                    $inner->orWhereRaw('LOWER(name) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(brand) LIKE ?', ['%'.$term.'%']);
                }
            });
        }

        return $query->get()->map(function (Product $product) {
            $primaryImage = $product->images
                ->sortByDesc('is_primary')
                ->sortBy('sort_order')
                ->first();
            $variants = $product->variants->take(6);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand' => $product->brand,
                'price' => (int) $product->base_price,
                'originalPrice' => $product->original_price ? (int) $product->original_price : null,
                'url' => '/products/'.$product->id,
                'imageUrl' => $primaryImage?->image_url,
                'availableSizes' => $variants->pluck('size')->filter()->unique()->values(),
                'colors' => $variants->pluck('color')->filter()->unique()->values(),
                'stockStatus' => $product->variants->sum('stock') > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK',
                'primarySku' => $variants->first()?->sku,
                'avgRating' => null,
                'reviewCount' => 0,
                'variants' => $variants->map(fn($variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'stock' => (int) $variant->stock,
                    'price' => (int) $product->base_price + (int) $variant->extra_price,
                ])->values(),
            ];
        })->values()->all();
    }
}
