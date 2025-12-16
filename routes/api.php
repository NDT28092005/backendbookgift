<?php

use App\Models\User;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\GoogleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\UserPreferenceController;
use App\Http\Controllers\Api\UserAnniversaryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OccasionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PromotionUsageController;
use App\Http\Controllers\Api\GiftOptionController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\GiftPreviewController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SliderController;

// Sliders - Public endpoint (lấy danh sách sliders active)
Route::get('/sliders', [SliderController::class, 'index']);

// Contact form - public endpoint (không cần đăng nhập)
Route::post('/contact', [ContactController::class, 'store']);

// Admin routes for contact messages
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/contact-messages', [ContactController::class, 'index']);
    Route::put('/admin/contact-messages/{id}/read', [ContactController::class, 'markAsRead']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Product chatbot endpoints với auth
    Route::post('/chat/product-chatbot/start', [ChatController::class, 'startProductChat']);
    Route::post('/chat/product-chatbot/save', [ChatController::class, 'saveProductChatMessage']);
    Route::get('/chat/product-chatbot/history/{id}', [ChatController::class, 'getProductChatHistory']);
});

// Product chatbot - public endpoint (có thể thêm auth nếu cần)
Route::post('/gift/preview', [GiftPreviewController::class, 'preview']);
Route::post('/chat/product-advice', [ChatController::class, 'productAdvice']);
Route::get('/ghtk/check/{label}', function($label, App\Services\GHTKService $ghtk) {
    return $ghtk->getOrderStatus($label);
});
Route::get('/orders/{order}/print-label', [OrderController::class, 'printLabel']);
Route::post('/orders/mark-paid', [OrderController::class, 'markPaid']);
Route::post('/orders/sync-tracking-codes', [OrderController::class, 'syncTrackingCodes']);
// Route::post('/ghtk/webhook', [GHTKWebhookController::class, 'updateStatus']);
// Promotions
Route::apiResource('promotions', PromotionController::class);
Route::get('promotions/{promotion}/usages', [PromotionController::class, 'usages']);

Route::prefix('admin')->group(function () {
    // 🔹 Promotion Usage
    Route::get('/promotion-usage', [PromotionUsageController::class, 'index']);
    Route::get('/promotion-usage/export', [PromotionUsageController::class, 'export']);
    Route::get('/promotion-usage/dashboard', [PromotionUsageController::class, 'dashboard']);
    Route::post('/promotion-usage', [PromotionUsageController::class, 'store']);
    Route::get('/promotion-usage/{id}', [PromotionUsageController::class, 'show']);
    Route::put('/promotion-usage/{id}', [PromotionUsageController::class, 'update']);
    Route::delete('/promotion-usage/{id}', [PromotionUsageController::class, 'destroy']);

    // 🔹 Referrals
    Route::get('/referrals', [ReferralController::class, 'index']);
    Route::get('/referrals/export', [ReferralController::class, 'export']);
    Route::get('/referrals/dashboard', [ReferralController::class, 'dashboard']);
    Route::post('/referrals', [ReferralController::class, 'store']);
    Route::get('/referrals/{id}', [ReferralController::class, 'show']);
    Route::put('/referrals/{id}', [ReferralController::class, 'update']);
    Route::delete('/referrals/{id}', [ReferralController::class, 'destroy']);
});

// Public referral endpoints
Route::post('/referrals/validate', [ReferralController::class, 'validateCode']);
Route::middleware('auth:sanctum')->post('/referrals/use', [ReferralController::class, 'use']);

//cart
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/cart/update', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/remove', [CartController::class, 'removeItem']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);
    Route::delete('/cart/clear-cart', [CartController::class, 'clearCart']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
});
// Orders
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/export', [OrderController::class, 'export']);
Route::post('/orders/import', [OrderController::class, 'import']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::post('/orders', [OrderController::class, 'store']);
Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

// Payments
Route::get('/payments', [PaymentController::class, 'index']);
Route::put('/payments/{id}/status', [PaymentController::class, 'updateStatus']);
// Proxy for Google Apps Script to bypass CORS
Route::get('/sheet-proxy', [PaymentController::class, 'sheetProxy']);

Route::prefix('reviews')->group(function () {
    Route::get('/', [ProductReviewController::class, 'index']);
    Route::post('/', [ProductReviewController::class, 'store']);
    Route::get('/{productReview}', [ProductReviewController::class, 'show']);
    Route::put('/{productReview}', [ProductReviewController::class, 'update']);
    Route::delete('/{productReview}', [ProductReviewController::class, 'destroy']);

    // Chặn / bỏ chặn review
    Route::patch('/{productReview}/block', [ProductReviewController::class, 'block']);
    Route::patch('/{productReview}/unblock', [ProductReviewController::class, 'unblock']);
});
// Categories routes - đặt routes đặc biệt TRƯỚC apiResource để tránh conflict
Route::post('categories/import', [CategoryController::class, 'import']);
Route::get('categories/export', [CategoryController::class, 'export']);
Route::delete('categories/delete-all', [CategoryController::class, 'deleteAll']);
Route::apiResource('categories', CategoryController::class);

// Occasions routes - đặt routes đặc biệt TRƯỚC apiResource để tránh conflict
Route::post('occasions/import', [OccasionController::class, 'import']);
Route::get('occasions/export', [OccasionController::class, 'export']);
Route::delete('occasions/delete-all', [OccasionController::class, 'deleteAll']);
Route::apiResource('occasions', OccasionController::class);
Route::apiResource('products', ProductController::class);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::apiResource('product-reviews', ProductReviewController::class);

// Addresses
Route::prefix('users/{user_id}/addresses')->group(function () {
    Route::get('/', [UserAddressController::class, 'index']);
    Route::post('/', [UserAddressController::class, 'store']);
    Route::get('/{id}', [UserAddressController::class, 'show']);
    Route::put('/{id}', [UserAddressController::class, 'update']);
    Route::delete('/{id}', [UserAddressController::class, 'destroy']);
});

// Preferences (1 user only)
Route::prefix('users/{user_id}/preferences')->group(function () {
    Route::get('/', [UserPreferenceController::class, 'index']);
    Route::post('/', [UserPreferenceController::class, 'store']);
    Route::get('/{id}', [UserPreferenceController::class, 'show']);
    Route::put('/{id}', [UserPreferenceController::class, 'update']);
    Route::delete('/{id}', [UserPreferenceController::class, 'destroy']);
});

// Anniversaries
Route::get('/users/{user}/anniversaries', [UserAnniversaryController::class, 'index']);
Route::get('/users/{user}/anniversaries/{anniversary}', [UserAnniversaryController::class, 'show']); // <- Thêm dòng này
Route::post('/users/{user}/anniversaries', [UserAnniversaryController::class, 'store']);
Route::put('/users/{user}/anniversaries/{anniversary}', [UserAnniversaryController::class, 'update']);
Route::delete('/users/{user}/anniversaries/{anniversary}', [UserAnniversaryController::class, 'destroy']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{user}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{user}', [UserController::class, 'update']);
Route::delete('/users/{user}', [UserController::class, 'destroy']);
Route::post('/users/{user}/avatar', [UserController::class, 'updateAvatar']);
    Route::put('/users/{user}/password', [UserController::class, 'updatePassword']);
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index']);
});
Route::middleware('auth:sanctum')->get('/admin/me', [AdminAuthController::class, 'me']);

// Shipping - Public endpoint (for checkout)
Route::post('/shipping/calc', [ShippingController::class, 'calc']);

// Gift Options - Public endpoints (for checkout)
Route::get('/gift-options/wrapping-papers', [GiftOptionController::class, 'getWrappingPapers']);
Route::get('/gift-options/decorative-accessories', [GiftOptionController::class, 'getDecorativeAccessories']);
Route::get('/gift-options/card-types', [GiftOptionController::class, 'getCardTypes']);

// Return Requests - User endpoints
Route::middleware('auth:sanctum')->prefix('returns')->group(function () {
    Route::get('/', [ReturnController::class, 'index']); // Lấy danh sách return requests của user
    Route::post('/', [ReturnController::class, 'store']); // Tạo return request mới
    Route::get('/{id}', [ReturnController::class, 'show']); // Xem chi tiết return request
});

// Return Requests - Admin endpoints
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/returns')->group(function () {
    Route::get('/', [ReturnController::class, 'adminIndex']); // Danh sách tất cả return requests
    Route::get('/{id}', [ReturnController::class, 'show']); // Chi tiết return request
    Route::post('/{id}/approve', [ReturnController::class, 'approve']); // Duyệt yêu cầu
    Route::post('/{id}/reject', [ReturnController::class, 'reject']); // Từ chối yêu cầu
    Route::post('/{id}/received', [ReturnController::class, 'received']); // Xác nhận đã nhận hàng hoàn
    Route::post('/{id}/refund', [ReturnController::class, 'refund']); // Hoàn tiền
});

// Sliders - Admin endpoints
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/sliders')->group(function () {
    Route::get('/', [SliderController::class, 'adminIndex']);
    Route::post('/', [SliderController::class, 'store']);
    Route::get('/{slider}', [SliderController::class, 'show']);
    Route::put('/{slider}', [SliderController::class, 'update']);
    Route::delete('/{slider}', [SliderController::class, 'destroy']);
});

// Gift Options - Admin endpoints
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/gift-options')->group(function () {
    // Wrapping Papers
    Route::get('/wrapping-papers', [GiftOptionController::class, 'indexWrappingPapers']);
    Route::post('/wrapping-papers', [GiftOptionController::class, 'storeWrappingPaper']);
    Route::post('/wrapping-papers/{id}', [GiftOptionController::class, 'updateWrappingPaper']);
    Route::put('/wrapping-papers/{id}', [GiftOptionController::class, 'updateWrappingPaper']);
    Route::delete('/wrapping-papers/{id}', [GiftOptionController::class, 'destroyWrappingPaper']);
    
    // Decorative Accessories
    Route::get('/decorative-accessories', [GiftOptionController::class, 'indexDecorativeAccessories']);
    Route::post('/decorative-accessories', [GiftOptionController::class, 'storeDecorativeAccessory']);
    Route::post('/decorative-accessories/{id}', [GiftOptionController::class, 'updateDecorativeAccessory']);
    Route::put('/decorative-accessories/{id}', [GiftOptionController::class, 'updateDecorativeAccessory']);
    Route::delete('/decorative-accessories/{id}', [GiftOptionController::class, 'destroyDecorativeAccessory']);
    
    // Card Types
    Route::get('/card-types', [GiftOptionController::class, 'indexCardTypes']);
    Route::post('/card-types', [GiftOptionController::class, 'storeCardType']);
    Route::post('/card-types/{id}', [GiftOptionController::class, 'updateCardType']);
    Route::put('/card-types/{id}', [GiftOptionController::class, 'updateCardType']);
    Route::delete('/card-types/{id}', [GiftOptionController::class, 'destroyCardType']);
});

// -------------------------
// Public Routes
// -------------------------
Route::post('/register', [RegisterController::class, 'register']); // Đăng ký email + gửi mail
Route::post('/login', [AuthenticationController::class, 'login']); // Login email/password

// Google OAuth
Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle']); // Redirect user đến Google
Route::post('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']); // Nhận token từ frontend
Route::post('/email/verify', function (Request $request) {
    $request->validate([
        'id' => 'required|integer|exists:users,id',
        'token' => 'required|string',
    ]);

    $user = User::find($request->id);

    // Kiểm tra token
    if (!Hash::check($user->email, $request->token)) {
        return response()->json(['status' => false, 'message' => 'Token xác nhận không hợp lệ'], 400);
    }

    // Đánh dấu verified
    $user->email_verified_at = now();
    $user->save();

    return response()->json(['status' => true, 'message' => 'Email đã được xác nhận']);
});
// -------------------------
// Protected Routes (yêu cầu login)
// -------------------------
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/me', [AuthenticationController::class, 'me']);
    Route::get('/logout', [AuthenticationController::class, 'logout']);
});
