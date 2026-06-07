<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\PolicyController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\GhnWebhookController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\ExportController as AdminExportController;
use App\Http\Controllers\Api\Admin\ShippingController as AdminShippingController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ProductSizeGuideController as AdminProductSizeGuideController;
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toDateTimeString(),
    ]);
});

// Danh sách sản phẩm
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/by-slug/{slug}', [ProductController::class, 'showBySlug']);

// Chi tiết sản phẩm
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ReviewController::class, 'listByProduct']);
Route::get('/products/{id}/similar', [ProductController::class, 'similar']);
Route::get('/categories', [CategoryController::class, 'listCategories']);
Route::get('/categories/{slug}', [CategoryController::class, 'getCategoryBySlug']);
Route::get('/surfaces', [CategoryController::class, 'listSurfaces']);
Route::get('/tags', [CategoryController::class, 'listTags']);
Route::get('/shipping/ghn/provinces', [ShippingController::class, 'provinces']);
Route::get('/shipping/ghn/districts', [ShippingController::class, 'districts']);
Route::get('/shipping/ghn/wards', [ShippingController::class, 'wards']);
Route::get('/policies', [PolicyController::class, 'index']);
Route::post('/contact/requests', [ContactController::class, 'submit'])->middleware('throttle:5,1');
Route::post('/chatbot/messages', [ChatbotController::class, 'messages'])->middleware('throttle:30,1');

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/google', [AuthController::class, 'google'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/setup-password', [AuthController::class, 'setupPassword']);
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::delete('/sessions/{deviceFingerprint}', [AuthController::class, 'revokeSession']);
        Route::delete('/sessions', [AuthController::class, 'revokeSessions']);

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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::post('/wishlist/items', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{id}', [WishlistController::class, 'destroy']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    Route::get('/cart', [CartController::class, 'get']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{id}', [CartController::class, 'updateItem']);
    Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
    Route::patch('/cart/items/{id}/variant', [CartController::class, 'updateItemVariant']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
    Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon']);
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);

    Route::post('/orders', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'listOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    Route::post('/shipping/quotes', [ShippingController::class, 'quote']);

    Route::post('/payments', [PaymentController::class, 'init']);
    Route::get('/orders/{orderId}/payment', [PaymentController::class, 'byOrder']);
    Route::get('/payments/payos/return-status', [PaymentController::class, 'payosReturnStatus']);
    Route::post('/payments/{paymentId}/cancel', [PaymentController::class, 'cancel']);
});
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users/{id}/roles', [AdminUserController::class, 'setRoles']);
    Route::get('/accounts', [AdminUserController::class, 'index']);
    Route::patch('/accounts/{id}/status', [AdminUserController::class, 'status']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::post('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);
    Route::patch('/categories/{id}/restore', [AdminCategoryController::class, 'restore']);
    Route::get('/categories/{id}/products', [AdminCategoryController::class, 'products']);
    Route::post('/categories/{id}/products/{productId}', [AdminCategoryController::class, 'addProduct']);
    Route::delete('/categories/{id}/products/{productId}', [AdminCategoryController::class, 'removeProduct']);

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/{id}', [AdminProductController::class, 'show']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::patch('/products/{id}/sale-price', [AdminProductController::class, 'updateSalePrice']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
    Route::patch('/products/{id}/restore', [AdminProductController::class, 'restore']);
    Route::post('/products/{id}/variants', [AdminProductController::class, 'storeVariant']);
    Route::put('/products/{id}/variants/{variantId}', [AdminProductController::class, 'updateVariant']);
    Route::delete('/products/{id}/variants/{variantId}', [AdminProductController::class, 'destroyVariant']);
    Route::patch('/products/{id}/variants/{variantId}/restore', [AdminProductController::class, 'restoreVariant']);
    Route::get('/products/{id}/images', [AdminProductController::class, 'images']);
    Route::post('/products/{id}/images', [AdminProductController::class, 'uploadImage']);
    Route::patch('/products/{id}/images/reorder', [AdminProductController::class, 'reorderImages']);
    Route::patch('/products/{id}/images/{imageId}', [AdminProductController::class, 'updateImage']);
    Route::delete('/products/{id}/images/{imageId}', [AdminProductController::class, 'destroyImage']);
    Route::patch('/products/{id}/images/{imageId}/restore', [AdminProductController::class, 'restoreImage']);
    Route::patch('/products/{id}/images/{imageId}/primary', [AdminProductController::class, 'setPrimaryImage']);
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

    Route::get('/size-guides', [AdminProductSizeGuideController::class, 'index']);
    Route::get('/size-guides/{id}', [AdminProductSizeGuideController::class, 'show']);
    Route::post('/size-guides', [AdminProductSizeGuideController::class, 'store']);
    Route::put('/size-guides/{id}', [AdminProductSizeGuideController::class, 'update']);
    Route::delete('/size-guides/{id}', [AdminProductSizeGuideController::class, 'destroy']);

    Route::get('/shipping/ghn/stores', [AdminShippingController::class, 'stores']);
    Route::post('/shipping/ghn/stores', [AdminShippingController::class, 'createStore']);
    Route::get('/orders/{orderId}/shipping', [AdminShippingController::class, 'show']);
    Route::post('/orders/{orderId}/shipping/ghn/preview', [AdminShippingController::class, 'preview']);
    Route::post('/orders/{orderId}/shipping/ghn/create', [AdminShippingController::class, 'create']);
    Route::post('/orders/{orderId}/shipping/ghn/sync', [AdminShippingController::class, 'sync']);
    Route::post('/orders/{orderId}/shipping/ghn/cancel', [AdminShippingController::class, 'cancel']);
    Route::post('/orders/{orderId}/shipping/ghn/delivery-again', [AdminShippingController::class, 'deliveryAgain']);
    Route::post('/orders/{orderId}/shipping/ghn/return', [AdminShippingController::class, 'returnOrder']);
    Route::post('/orders/{orderId}/shipping/ghn/update-cod', [AdminShippingController::class, 'updateCod']);
    Route::get('/orders/{orderId}/shipping/ghn/print-token', [AdminShippingController::class, 'printToken']);

    Route::get('/export/products.csv', [AdminExportController::class, 'productsCsv']);
    Route::get('/export/orders.csv', [AdminExportController::class, 'ordersCsv']);
});

// Payment callbacks (tuỳ provider sẽ ký số):
Route::post('/payments/callback', [PaymentController::class, 'callback']);
Route::post('/payments/webhooks/payos', [PaymentController::class, 'payosWebhook']);
Route::post('/shipping/ghn/webhooks/order-status', [GhnWebhookController::class, 'orderStatus']);
Route::post('/shipping/ghn/webhooks/ticket', [GhnWebhookController::class, 'ticket']);
