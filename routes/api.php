<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ThuocController;
use App\Http\Controllers\WaterController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\UserGoalController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ThongBaoController;
use App\Http\Controllers\NhacNhoController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\AdminController;

Route::middleware('admin')->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/accounts', [AdminController::class, 'accounts']);
    Route::get('/accounts/{id}', [AdminController::class, 'accountDetail']);
    Route::put('/accounts/{id}', [AdminController::class, 'updateAccount']);
    Route::put('/accounts/{id}/toggle', [AdminController::class, 'toggleAccount']);
    Route::put('/accounts/{id}/password', [AdminController::class, 'resetPassword']);
    Route::put('/accounts/{id}/mode', [AdminController::class, 'updateUserMode']);
    Route::get('/resources', [AdminController::class, 'resources']);
    Route::post('/foods', [AdminController::class, 'storeFood']);
    Route::put('/foods/{id}', [AdminController::class, 'updateFood']);
    Route::delete('/foods/{id}', [AdminController::class, 'deleteFood']);
    Route::post('/medicines', [AdminController::class, 'storeMedicine']);
    Route::put('/medicines/{id}', [AdminController::class, 'updateMedicine']);
    Route::delete('/medicines/{id}', [AdminController::class, 'deleteMedicine']);
    Route::get('/notifications', [AdminController::class, 'notifications']);
    Route::post('/notifications', [AdminController::class, 'createNotification']);
    Route::put('/notifications/{id}/read', [AdminController::class, 'markNotificationRead']);
    Route::delete('/notifications/{id}', [AdminController::class, 'deleteNotification']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/save-user', [UserController::class, 'saveUser']);

Route::middleware('api.user')->group(function () {
Route::post('/save-onboarding', [UserController::class, 'saveOnboarding']);
Route::get('/users', [UserController::class, 'getUsers']);

Route::get('/home/{id}', [HomeController::class, 'index']);
Route::get('/dashboard/{userId}', [DashboardController::class, 'index']);
Route::get('/profile/{userId}', [UserController::class, 'getProfile']);

Route::post(
    '/update-profile',
    [
        UserController::class,
        'updateProfile'
    ]
);

Route::post(
    '/analyze-health',
    [UserController::class, 'analyzeHealth']
);

Route::get('/health/weight', [HealthController::class, 'weight']);
Route::post('/health/weight', [HealthController::class, 'storeWeight']);

Route::get('/activity', [ActivityController::class, 'index']);
Route::post('/activity', [ActivityController::class, 'store']);
Route::get('/activity/stats', [ActivityController::class, 'stats']);
Route::delete('/activity/{id}', [ActivityController::class, 'destroy']);

Route::get('/goals', [UserGoalController::class, 'show']);
Route::get('/goals/suggestions', [UserGoalController::class, 'suggestions']);
Route::get('/goals/progress', [UserGoalController::class, 'progress']);
Route::get('/goals/history', [UserGoalController::class, 'history']);
Route::post('/goals', [UserGoalController::class, 'store']);
Route::post('/goals/apply-suggestion', [UserGoalController::class, 'applySuggestion']);

Route::get('/thucpham', [FoodController::class, 'index']);
Route::post('/thucpham', [FoodController::class, 'store']);
Route::get('/buaan', [FoodController::class, 'meals']);
Route::post('/buaan', [FoodController::class, 'storeMeal']);
Route::delete('/buaan/detail/{id}', [FoodController::class, 'deleteMealDetail']);
Route::get('/buaan/stats', [FoodController::class, 'stats']);

Route::post('/chat/send', [ChatController::class, 'send']);
Route::get('/chat/history', [ChatController::class, 'history']);
Route::post('/chat/clear', [ChatController::class, 'clear']);

Route::get('/thongbao', [ThongBaoController::class, 'index']);
Route::get('/thongbao/chua-doc', [ThongBaoController::class, 'demChuaDoc']);
Route::post('/thongbao/ghi-nhan', [ThongBaoController::class, 'ghiNhan']);
Route::put('/thongbao/doc-tat', [ThongBaoController::class, 'docTatCa']);
Route::put('/thongbao/{id}/da-doc', [ThongBaoController::class, 'danhDauDaDoc']);

Route::get('/nhac-nho', [NhacNhoController::class, 'index']);
Route::post('/nhac-nho/luu-nhom', [NhacNhoController::class, 'luuNhom']);
Route::put('/nhac-nho/{id}/trang-thai', [NhacNhoController::class, 'doiTrangThai']);
Route::delete('/nhac-nho/{id}', [NhacNhoController::class, 'xoa']);

// ======================================
// THUỐC
// ======================================

Route::post(
    '/them-thuoc',
    [ThuocController::class, 'themThuoc']
);

Route::get(
    '/danh-sach-thuoc/{nguoiDungId}',
    [ThuocController::class, 'layDanhSachThuoc']
);

Route::get('/thuoc/tim-kiem', [ThuocController::class, 'timKiemThuoc']);
Route::get('/thuoc/bao-cao/{nguoiDungId}', [ThuocController::class, 'baoCao']);
Route::put('/thuoc/{id}', [ThuocController::class, 'capNhatThuoc']);
Route::put('/thuoc/{id}/trang-thai', [ThuocController::class, 'capNhatTrangThai']);

Route::put(
    '/da-uong-thuoc/{id}',
    [ThuocController::class, 'danhDauDaUong']
);

Route::delete(
    '/xoa-thuoc/{id}',
    [ThuocController::class, 'xoaThuoc']
);



// ======================================
// Nước
// ======================================

Route::get(
    '/water/{id}/stats',
    [WaterController::class,
     'stats']
);

Route::get(
    '/water/{id}',
    [WaterController::class,
     'getWater']
);

Route::post(
    '/water',
    [WaterController::class,
     'addWater']
);

Route::delete(
    '/water/{id}',
    [WaterController::class,
     'deleteWater']
);

Route::get('/nuoc', [WaterController::class, 'getWaterByQuery']);
Route::post('/nuoc', [WaterController::class, 'addWater']);
});
