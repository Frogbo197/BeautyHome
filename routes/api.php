<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NhacNhoController;
use App\Http\Controllers\ThongBaoController;
use App\Http\Controllers\ThuocController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserGoalController;
use App\Http\Controllers\WaterController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

Route::post('/save-user', [UserController::class, 'saveUser']);
Route::post('/save-onboarding', [UserController::class, 'saveOnboarding']);
Route::post('/update-profile', [UserController::class, 'updateProfile']);
Route::post('/analyze-health', [UserController::class, 'analyzeHealth']);
Route::get('/users', [UserController::class, 'getUsers']);
Route::get('/profile/{userId}', [UserController::class, 'getProfile']);
Route::get('/home/{id}', [HomeController::class, 'index']);
Route::get('/dashboard/{userId}', [DashboardController::class, 'index']);

Route::post('/health/analyze', [HealthController::class, 'analyze']);
Route::post('/health/onboarding', [HealthController::class, 'saveOnboarding']);
Route::get('/health/weight', [HealthController::class, 'weight']);
Route::post('/health/weight', [HealthController::class, 'storeWeight']);

Route::get('/water/stats/{id}', [WaterController::class, 'stats']);
Route::get('/water/{id}', [WaterController::class, 'getWater']);
Route::get('/water', [WaterController::class, 'getWaterByQuery']);
Route::post('/water', [WaterController::class, 'addWater']);
Route::delete('/water/{id}', [WaterController::class, 'deleteWater']);

Route::post('/them-thuoc', [ThuocController::class, 'themThuoc']);
Route::get('/danh-sach-thuoc/{nguoiDungId}', [ThuocController::class, 'layDanhSachThuoc']);
Route::put('/da-uong-thuoc/{id}', [ThuocController::class, 'danhDauDaUong']);
Route::put('/thuoc/{id}/trang-thai', [ThuocController::class, 'capNhatTrangThai']);
Route::put('/thuoc/{id}', [ThuocController::class, 'capNhatThuoc']);
Route::patch('/thuoc/{id}', [ThuocController::class, 'capNhatThuoc']);
Route::delete('/thuoc/{id}', [ThuocController::class, 'xoaThuoc']);
Route::delete('/xoa-thuoc/{id}', [ThuocController::class, 'xoaThuoc']);
Route::get('/thuoc/bao-cao/{nguoiDungId}', [ThuocController::class, 'baoCao']);
Route::get('/thuoc/tim-kiem', [ThuocController::class, 'timKiemThuoc']);
Route::get('/danh-muc/thuoc', [AdminController::class, 'getDanhSachThuoc']);

Route::get('/thuc-pham', [FoodController::class, 'index']);
Route::post('/thuc-pham', [FoodController::class, 'store']);
Route::get('/thucpham', [FoodController::class, 'index']);
Route::post('/thucpham', [FoodController::class, 'store']);
Route::get('/buaan', [FoodController::class, 'meals']);
Route::post('/buaan', [FoodController::class, 'storeMeal']);
Route::delete('/buaan/detail/{id}', [FoodController::class, 'deleteMealDetail']);
Route::get('/food/stats', [FoodController::class, 'stats']);

Route::get('/activity', [ActivityController::class, 'index']);
Route::post('/activity', [ActivityController::class, 'store']);
Route::get('/activity/stats', [ActivityController::class, 'stats']);
Route::delete('/activity/{id}', [ActivityController::class, 'destroy']);
Route::get('/danh-muc/van-dong', [AdminController::class, 'getDanhSachHoatDong']);
Route::get('/van-dong', [AdminController::class, 'getDanhSachHoatDong']);

Route::get('/nhac-nho', [NhacNhoController::class, 'index']);
Route::post('/nhac-nho/nhom', [NhacNhoController::class, 'luuNhom']);
Route::put('/nhac-nho/{id}/trang-thai', [NhacNhoController::class, 'doiTrangThai']);
Route::delete('/nhac-nho/{id}', [NhacNhoController::class, 'xoa']);

Route::get('/thong-bao', [ThongBaoController::class, 'index']);
Route::get('/thong-bao/chua-doc', [ThongBaoController::class, 'demChuaDoc']);
Route::post('/thong-bao', [ThongBaoController::class, 'ghiNhan']);
Route::put('/thong-bao/{id}/doc', [ThongBaoController::class, 'danhDauDaDoc']);
Route::put('/thong-bao/doc-tat-ca', [ThongBaoController::class, 'docTatCa']);

Route::get('/goals', [UserGoalController::class, 'show']);
Route::post('/goals', [UserGoalController::class, 'store']);
Route::get('/goals/suggestions', [UserGoalController::class, 'suggestions']);
Route::post('/goals/apply-suggestion', [UserGoalController::class, 'applySuggestion']);
Route::get('/goals/progress', [UserGoalController::class, 'progress']);
Route::get('/goals/history', [UserGoalController::class, 'history']);

Route::prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/accounts', [AdminController::class, 'accounts']);
    Route::get('/accounts/{id}', [AdminController::class, 'accountDetail']);
    Route::match(['put', 'patch'], '/accounts/{id}', [AdminController::class, 'updateAccount']);
    Route::match(['put', 'patch'], '/accounts/{id}/password', [AdminController::class, 'resetPassword']);
    Route::match(['put', 'patch'], '/accounts/{id}/mode', [AdminController::class, 'updateUserMode']);
    Route::match(['put', 'patch'], '/accounts/{id}/toggle', [AdminController::class, 'toggleAccount']);
    Route::get('/nguoi-dung', [AdminController::class, 'getDanhSachNguoiDung']);
    Route::match(['put', 'patch'], '/nguoi-dung/{userId}/block', [AdminController::class, 'toggleBlockUser']);

    Route::get('/system-config', [AdminController::class, 'getSystemConfig']);
    Route::match(['put', 'patch'], '/system-config', [AdminController::class, 'updateSystemConfig']);
    Route::get('/resources', [AdminController::class, 'resources']);

    Route::get('/thuoc', [AdminController::class, 'getDanhSachThuoc']);
    Route::post('/thuoc', [AdminController::class, 'addThuoc']);
    Route::match(['put', 'patch'], '/thuoc/{id}', [AdminController::class, 'updateThuoc']);
    Route::delete('/thuoc/{id}', [AdminController::class, 'xoaThuoc']);
    Route::post('/medicines', [AdminController::class, 'storeMedicine']);
    Route::match(['put', 'patch'], '/medicines/{id}', [AdminController::class, 'updateMedicine']);
    Route::delete('/medicines/{id}', [AdminController::class, 'deleteMedicine']);

    Route::get('/thuc-pham', [AdminController::class, 'getDanhSachThucPham']);
    Route::post('/thuc-pham', [AdminController::class, 'addThucPham']);
    Route::delete('/thuc-pham/{id}', [AdminController::class, 'xoaThucPham']);
    Route::post('/foods', [AdminController::class, 'storeFood']);
    Route::match(['post', 'put', 'patch'], '/foods/{id}', [AdminController::class, 'updateFood']);
    Route::delete('/foods/{id}', [AdminController::class, 'deleteFood']);

    Route::get('/van-dong', [AdminController::class, 'getDanhSachVanDong']);
    Route::post('/van-dong', [AdminController::class, 'addVanDong']);
    Route::delete('/van-dong/{id}', [AdminController::class, 'deleteVanDong']);
    Route::get('/hoat-dong', [AdminController::class, 'getDanhSachHoatDong']);
    Route::post('/hoat-dong', [AdminController::class, 'addHoatDong']);
    Route::delete('/hoat-dong/{id}', [AdminController::class, 'deleteHoatDong']);

    Route::get('/notifications', [AdminController::class, 'notifications']);
    Route::post('/notifications', [AdminController::class, 'createNotification']);
    Route::match(['put', 'patch'], '/notifications/{id}/read', [AdminController::class, 'markNotificationRead']);
    Route::delete('/notifications/{id}', [AdminController::class, 'deleteNotification']);
    Route::match(['put', 'patch'], '/read/{id}', [AdminController::class, 'markAsRead']);

    Route::get('/risk-events', [AdminController::class, 'riskEvents']);
    Route::get('/he-thong-canh-bao', [AdminController::class, 'getHeThongCanhBao']);
    Route::post('/he-thong-canh-bao/quet', [AdminController::class, 'quetHeThongCanhBao']);
    Route::match(['put', 'patch'], '/he-thong-canh-bao/{id}/status', [AdminController::class, 'updateAlertStatus']);
});
