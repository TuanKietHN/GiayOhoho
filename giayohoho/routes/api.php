<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VariantController;
use App\Http\Controllers\Api\CategoryPublicController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\ExportController as AdminExportController;
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toDateTimeString(),
    ]);
});

// Danh sách sản phẩm
Route::get('/products', [ProductController::class, 'index']);

// Chi tiết sản phẩm
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ReviewController::class, 'listByProduct']);
Route::get('/products/{id}/similar', [ProductController::class, 'similar']);
Route::get('/variants/{id}', [VariantController::class, 'show']);
Route::get('/categories', [CategoryPublicController::class, 'index']);
// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{id}', [WishlistController::class, 'destroy']);

        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::put('/reviews/{id}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

        Route::get('/cart', [CartController::class, 'get']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
        Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon']);

        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'listOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });
});
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users/{id}/roles', [AdminUserController::class, 'setRoles']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::post('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);

    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
    Route::post('/products/bulk-assign-category', [AdminProductController::class, 'bulkAssignCategory']);

    Route::get('/products/{id}/variants', [AdminProductVariantController::class, 'index']);
    Route::post('/variants', [AdminProductVariantController::class, 'store']);
    Route::put('/variants/{id}', [AdminProductVariantController::class, 'update']);
    Route::delete('/variants/{id}', [AdminProductVariantController::class, 'destroy']);
    Route::post('/variants/{id}/adjust-stock', [AdminProductVariantController::class, 'adjustStock']);
    Route::get('/variants/low-stock', [AdminProductVariantController::class, 'lowStock']);

    Route::get('/coupons', [AdminCouponController::class, 'index']);
    Route::post('/coupons', [AdminCouponController::class, 'store']);
    Route::put('/coupons/{id}', [AdminCouponController::class, 'update']);
    Route::delete('/coupons/{id}', [AdminCouponController::class, 'destroy']);
    Route::get('/coupons/{id}/stats', [AdminCouponController::class, 'stats']);

    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);

    Route::get('/export/products.csv', [AdminExportController::class, 'productsCsv']);
    Route::get('/export/orders.csv', [AdminExportController::class, 'ordersCsv']);
});

// Payment callbacks (tuỳ provider sẽ ký số):
Route::post('/payments/callback', [PaymentController::class, 'callback']);
