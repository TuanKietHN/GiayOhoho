<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $ordersByStatus = OrderDetail::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->mapWithKeys(fn($total, $status) => [strtoupper($status) => (int) $total]);

        return $this->ok([
            'totalOrders' => OrderDetail::count(),
            'totalAccounts' => User::count(),
            'totalProducts' => Product::count(),
            'totalRevenue' => (int) OrderDetail::whereIn('status', ['PAID', 'SHIPPING', 'DONE'])->sum('total'),
            'ordersByStatus' => $ordersByStatus,
            'lowStockItems' => ProductVariant::with('product')
                ->where('stock', '<=', 5)
                ->orderBy('stock')
                ->limit(10)
                ->get()
                ->map(fn(ProductVariant $variant) => [
                    'variantId' => $variant->id,
                    'sku' => $variant->sku,
                    'productName' => $variant->product?->name,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'stock' => (int) $variant->stock,
                ])->values(),
        ]);
    }
}
