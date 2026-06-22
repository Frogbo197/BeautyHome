<?php

namespace App\Http\Controllers;

use App\Models\HeThongCanhBao;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('admin')->check() && $this->isAdminAccount(Auth::guard('admin')->user())) {
            return redirect()->route('admin.dashboard');
        }

        return response()
            ->view('admin.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);
        $email = trim((string) $credentials['email']);

        $throttleKey = $this->adminLoginThrottleKey($request);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Đăng nhập quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ]);
        }

        $ok = Auth::guard('admin')->attempt([
            'Email' => $email,
            'password' => $credentials['password'],
        ]);

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['email' => 'Email hoặc mật khẩu Admin không chính xác.'])
                ->onlyInput('email');
        }

        $admin = Auth::guard('admin')->user();
        if (! $this->isAdminAccount($admin)) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['email' => 'Tài khoản này không có quyền Admin.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        if (Schema::hasTable('taikhoan') && Schema::hasColumn('taikhoan', 'LanDangNhapCuoi')) {
            DB::table('taikhoan')
                ->where('ID', (int) ($admin->ID ?? 0))
                ->update(['LanDangNhapCuoi' => now('Asia/Ho_Chi_Minh')]);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with('status', 'Bạn đã đăng xuất khỏi trang quản trị.');
    }

    public function page()
    {
        return view('admin.dashboard');
    }

    public function stats(Request $request)
    {
        $period = $this->dashboardPeriod($request);

        $today = now('Asia/Ho_Chi_Minh');
        $accountStats = $this->accountAggregateStats($period);
        $notificationStats = $this->notificationAggregateStats($today, $period);
        $alerts = $this->healthRiskAlerts();
        $openAlerts = collect($alerts)->where('is_read', false)->values()->all();

        $payload = [
            'success' => true,
            'generated_at' => $today->toDateTimeString(),
            'period' => $period,
            'overview' => $this->dashboardOverview($accountStats, $notificationStats, $openAlerts, $period),
            'badges' => [
                'users_unread' => $accountStats['unreviewed'],
                'alerts_unread' => count($openAlerts),
                'notifications_unread' => $notificationStats['unread'],
            ],
            'features' => $this->featureStats($period),
            'weekly' => $this->weeklyStats($today),
            'alerts' => $openAlerts,
            'notifications' => [
                'total' => $notificationStats['total'],
                'unread' => $notificationStats['unread'],
                'read' => $notificationStats['read'],
                'today' => $notificationStats['today'],
                'by_type' => $this->notificationTypes(),
                'recent' => $this->recentNotifications(),
            ],
        ];

        return response()->json($payload);
    }

    public function accounts(Request $request)
    {
        if (! Schema::hasTable('taikhoan')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 1],
            ]);
        }

        $perPage = min(max($request->integer('per_page', 20), 5), 50);
        $query = DB::table('taikhoan as t');
        $hasBlockedColumn = Schema::hasColumn('taikhoan', 'is_blocked');
        $hasActiveColumn = Schema::hasColumn('taikhoan', 'TrangThaiHoatDong');

        if (Schema::hasTable('hosonguoidung')) {
            $query->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 't.ID');
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));
            if (mb_strlen($keyword) >= 2) {
                $like = str_contains($keyword, '@') ? "{$keyword}%" : "%{$keyword}%";
                $query->where(function ($inner) use ($keyword, $like) {
                    $inner->where('t.Email', 'like', $like);
                    if (is_numeric($keyword)) {
                        $inner->orWhere('t.ID', (int) $keyword);
                    }
                    if (Schema::hasTable('hosonguoidung') && Schema::hasColumn('hosonguoidung', 'Ten')) {
                        $inner->orWhere('h.Ten', 'like', "%{$keyword}%");
                    }
                });
            }
        }

        $status = $request->query('status');
        if ($status !== null && $status !== '' && ($hasBlockedColumn || $hasActiveColumn)) {
            $status = strtolower((string) $status);
            if (in_array($status, ['locked', 'blocked', 'inactive'], true)) {
                $query->where(function ($inner) use ($hasBlockedColumn, $hasActiveColumn) {
                    if ($hasBlockedColumn) {
                        $inner->where('t.is_blocked', true);
                    }
                    if ($hasActiveColumn) {
                        $hasBlockedColumn
                            ? $inner->orWhere('t.TrangThaiHoatDong', 0)
                            : $inner->where('t.TrangThaiHoatDong', 0);
                    }
                });
            } elseif (in_array($status, ['active', 'unblocked'], true)) {
                if ($hasBlockedColumn) {
                    $query->where('t.is_blocked', false);
                }
                if ($hasActiveColumn) {
                    $query->where('t.TrangThaiHoatDong', 1);
                }
            }
        }

        $select = [
            't.ID',
            't.Email',
            $hasBlockedColumn
                ? 't.is_blocked'
                : DB::raw('0 as is_blocked'),
            $hasActiveColumn
                ? 't.TrangThaiHoatDong'
                : DB::raw('1 as TrangThaiHoatDong'),
            Schema::hasColumn('taikhoan', 'LanDangNhapCuoi')
                ? 't.LanDangNhapCuoi'
                : DB::raw('NULL as LanDangNhapCuoi'),
            Schema::hasColumn('taikhoan', 'NgayTao')
                ? 't.NgayTao'
                : DB::raw('NULL as NgayTao'),
        ];

        foreach (['Ten', 'GioiTinh', 'NgaySinh', 'ChieuCao', 'CanNang', 'AnhDaiDien'] as $column) {
            $select[] = Schema::hasTable('hosonguoidung') && Schema::hasColumn('hosonguoidung', $column)
                ? "h.{$column}"
                : DB::raw("NULL as {$column}");
        }

        $accounts = $query
            ->select($select)
            ->orderByDesc('t.ID')
            ->paginate($perPage);

        $ids = collect($accounts->items())->pluck('ID')->map(fn ($id) => (int) $id)->all();
        $statsByUser = $this->preloadAccountStats($ids);

        $data = collect($accounts->items())
            ->map(fn ($account) => $this->formatAccount($account, $statsByUser[(int) $account->ID] ?? null))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $accounts->total(),
                'page' => $accounts->currentPage(),
                'per_page' => $accounts->perPage(),
                'last_page' => $accounts->lastPage(),
            ],
        ]);
    }

    public function getDanhSachNguoiDung(Request $request)
    {
        return $this->accounts($request);
    }

    public function toggleBlockUser(Request $request, int $userId)
    {
        if (! Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng tài khoản'], 404);
        }

        $hasBlockedColumn = Schema::hasColumn('taikhoan', 'is_blocked');
        $hasActiveColumn = Schema::hasColumn('taikhoan', 'TrangThaiHoatDong');
        if (! $hasBlockedColumn && ! $hasActiveColumn) {
            return response()->json([
                'success' => false,
                'message' => 'Thiếu cột trạng thái tài khoản. Vui lòng chạy migration is_blocked.',
            ], 422);
        }

        $account = DB::table('taikhoan')->where('ID', $userId)->first();
        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        $blocked = $request->has('is_blocked')
            ? $request->boolean('is_blocked')
            : ($request->has('locked')
                ? $request->boolean('locked')
                : ! $this->accountIsBlocked($account));

        $payload = [];
        if ($hasBlockedColumn) {
            $payload['is_blocked'] = $blocked;
        }
        if ($hasActiveColumn) {
            $payload['TrangThaiHoatDong'] = $blocked ? 0 : 1;
        }

        DB::transaction(function () use ($userId, $blocked, $payload) {
            DB::table('taikhoan')->where('ID', $userId)->update($payload);
            Log::warning('admin.account.block_toggle', [
                'account_id' => $userId,
                'is_blocked' => $blocked,
            ]);
            Cache::forget('admin:stats:v2');
        });

        $fresh = $this->accountQuery()
            ->where('t.ID', $userId)
            ->first($this->accountSelect());

        return response()->json([
            'success' => true,
            'message' => $blocked ? 'Đã khóa tài khoản người dùng' : 'Đã mở khóa tài khoản người dùng',
            'account' => $this->formatAccount($fresh),
        ]);
    }

    public function toggleAccount(Request $request, int $id)
    {
        return $this->toggleBlockUser($request, $id);

        if (! Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng tài khoản'], 404);
        }

        if (! Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
            return response()->json(['success' => false, 'message' => 'Thiếu cột trạng thái tài khoản'], 422);
        }

        $account = DB::table('taikhoan')->where('ID', $id)->first();
        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        $locked = $request->has('locked')
            ? $request->boolean('locked')
            : ((int) $account->TrangThaiHoatDong === 1);

        DB::transaction(function () use ($id, $locked) {
            DB::table('taikhoan')
                ->where('ID', $id)
                ->update(['TrangThaiHoatDong' => $locked ? 0 : 1]);
            Log::warning('admin.account.toggle', ['account_id' => $id, 'locked' => $locked]);
            Cache::forget('admin:stats:v2');
        });

        $freshQuery = DB::table('taikhoan as t');
        if (Schema::hasTable('hosonguoidung')) {
            $freshQuery->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 't.ID');
        }

        $freshSelect = [
            't.ID',
            't.Email',
            't.TrangThaiHoatDong',
            Schema::hasColumn('taikhoan', 'LanDangNhapCuoi')
                ? 't.LanDangNhapCuoi'
                : DB::raw('NULL as LanDangNhapCuoi'),
            Schema::hasColumn('taikhoan', 'NgayTao')
                ? 't.NgayTao'
                : DB::raw('NULL as NgayTao'),
        ];

        foreach (['Ten', 'GioiTinh', 'NgaySinh', 'ChieuCao', 'CanNang', 'AnhDaiDien'] as $column) {
            $freshSelect[] = Schema::hasTable('hosonguoidung') && Schema::hasColumn('hosonguoidung', $column)
                ? "h.{$column}"
                : DB::raw("NULL as {$column}");
        }

        return response()->json([
            'success' => true,
            'message' => $locked ? 'Đã khóa tài khoản' : 'Đã mở tài khoản',
            'account' => $this->formatAccount($freshQuery->where('t.ID', $id)->first($freshSelect)),
        ]);
    }

    public function accountDetail(int $id)
    {
        if (! Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng tài khoản'], 404);
        }

        $account = $this->accountQuery()
            ->where('t.ID', $id)
            ->first($this->accountSelect());

        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        $reviewed = $this->markUserAsReviewed($id);
        $account = $this->accountQuery()
            ->where('t.ID', $id)
            ->first($this->accountSelect());

        return response()->json([
            'success' => true,
            'account' => $this->formatAccount($account),
            'reviewed' => $reviewed,
            'health' => [
                'profile' => $this->firstUserRow('hososuckhoe', $id),
                'latest_index' => $this->latestUserRow('chisosuckhoe', $id, 'ID'),
                'latest_score' => $this->latestUserRow('diemsuckhoe', $id, 'ID'),
                'preferences' => $this->userPreferences($id),
                'goals' => $this->userGoals($id),
            ],
            'recent' => [
                'meals' => $this->recentMeals($id),
                'water' => $this->recentRows('theodoinuoc', $id, 'ID', 6),
                'medicines' => $this->recentMedicines($id),
                'activities' => $this->recentActivities($id),
                'notifications' => $this->recentRows('thongbao', $id, 'ID', 8),
            ],
        ]);
    }

    public function updateAccount(Request $request, int $id)
    {
        if (! Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chua co bang tai khoan'], 404);
        }

        $account = DB::table('taikhoan')->where('ID', $id)->first();
        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay tai khoan'], 404);
        }

        $data = $request->validate([
            'email' => 'nullable|email|max:255',
            'name' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'height' => 'nullable|numeric|min:0|max:300',
            'weight' => 'nullable|numeric|min:0|max:500',
            'avatar' => 'nullable|string',
            'active' => 'nullable|boolean',
            'is_blocked' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($data, $request, $id, $account) {
            if (! empty($data['email']) && $data['email'] !== $account->Email) {
                $exists = DB::table('taikhoan')
                    ->where('Email', $data['email'])
                    ->where('ID', '<>', $id)
                    ->exists();

                if ($exists) {
                    abort(response()->json(['success' => false, 'message' => 'Email da ton tai'], 422));
                }

                DB::table('taikhoan')->where('ID', $id)->update(['Email' => $data['email']]);
            }

            $accountUpdate = [];
            if (array_key_exists('active', $data)) {
                $blocked = ! $request->boolean('active');
                if (Schema::hasColumn('taikhoan', 'is_blocked')) {
                    $accountUpdate['is_blocked'] = $blocked;
                }
                if (Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
                    $accountUpdate['TrangThaiHoatDong'] = $blocked ? 0 : 1;
                }
            }

            if (array_key_exists('is_blocked', $data)) {
                $blocked = $request->boolean('is_blocked');
                if (Schema::hasColumn('taikhoan', 'is_blocked')) {
                    $accountUpdate['is_blocked'] = $blocked;
                }
                if (Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
                    $accountUpdate['TrangThaiHoatDong'] = $blocked ? 0 : 1;
                }
            }

            if (! empty($accountUpdate)) {
                DB::table('taikhoan')->where('ID', $id)->update($accountUpdate);
            }

            if (Schema::hasTable('hosonguoidung')) {
                $profile = [];
                foreach ([
                    'Ten' => 'name',
                    'GioiTinh' => 'gender',
                    'NgaySinh' => 'birthday',
                    'ChieuCao' => 'height',
                    'CanNang' => 'weight',
                    'AnhDaiDien' => 'avatar',
                ] as $column => $key) {
                    if (Schema::hasColumn('hosonguoidung', $column) && array_key_exists($key, $data)) {
                        $profile[$column] = $data[$key];
                    }
                }

                if (! empty($profile)) {
                    if (Schema::hasColumn('hosonguoidung', 'NgayCapNhat')) {
                        $profile['NgayCapNhat'] = now('Asia/Ho_Chi_Minh');
                    }

                    DB::table('hosonguoidung')->updateOrInsert(
                        ['NguoiDungID' => $id],
                        $profile
                    );
                }
            }

            Log::warning('admin.account.update', ['account_id' => $id]);
            Cache::forget('admin:stats:v2');
        });

        $fresh = $this->accountQuery()
            ->where('t.ID', $id)
            ->first($this->accountSelect());

        return response()->json([
            'success' => true,
            'message' => 'Da cap nhat tai khoan',
            'account' => $this->formatAccount($fresh),
        ]);
    }

    public function resetPassword(Request $request, int $id)
    {
        if (! Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng tài khoản'], 404);
        }

        $data = $request->validate([
            'password' => 'required|string|min:6|max:100',
        ]);

        $updated = DB::transaction(function () use ($id, $data) {
            $updated = DB::table('taikhoan')
                ->where('ID', $id)
                ->update(['MatKhauHash' => Hash::make($data['password'])]);
            if ($updated) {
                Log::warning('admin.account.reset_password', ['account_id' => $id]);
            }

            return $updated;
        });

        if (! $updated) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Đã đặt lại mật khẩu']);
    }

    public function updateUserMode(Request $request, int $id)
    {
        if (! Schema::hasTable('taikhoan') || ! DB::table('taikhoan')->where('ID', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay tai khoan'], 404);
        }

        $data = $request->validate([
            'goals' => 'nullable|string|max:1000',
            'activity_level' => 'nullable|string|max:100',
            'diet_mode' => 'nullable|string|max:100',
            'water_goal' => 'nullable|numeric|min:0|max:20000',
        ]);

        DB::transaction(function () use ($data, $id) {
            if (Schema::hasTable('sothichnguoidung')) {
                DB::table('sothichnguoidung')->updateOrInsert(
                    ['NguoiDung' => $id],
                    [
                        'MucTieu' => $data['goals'] ?? null,
                        'MucDoVanDong' => $data['activity_level'] ?? null,
                        'CheDoAn' => $data['diet_mode'] ?? null,
                    ]
                );
            }

            if (Schema::hasTable('muctieusuckhoe')) {
                $goals = collect(explode(',', $data['goals'] ?? ''))
                    ->map(fn ($goal) => trim($goal))
                    ->filter()
                    ->values();

                if ($goals->isNotEmpty()) {
                    DB::table('muctieusuckhoe')->where('NguoiDungID', $id)->delete();
                    foreach ($goals as $goal) {
                        DB::table('muctieusuckhoe')->insert($this->onlyExistingColumns('muctieusuckhoe', [
                            'NguoiDungID' => $id,
                            'TenMucTieu' => $goal,
                            'TrangThai' => 'DangThucHien',
                            'NgayBatDau' => now('Asia/Ho_Chi_Minh')->toDateString(),
                        ]));
                    }
                }

                if (array_key_exists('water_goal', $data) && $data['water_goal'] !== null && $data['water_goal'] !== '') {
                    DB::table('muctieusuckhoe')->updateOrInsert(
                        [
                            'NguoiDungID' => $id,
                            'LoaiMucTieu' => 'Nuoc',
                        ],
                        $this->onlyExistingColumns('muctieusuckhoe', [
                            'NguoiDungID' => $id,
                            'TenMucTieu' => 'Uong nuoc',
                            'GiaTriMucTieu' => (float) $data['water_goal'],
                            'NgayBatDau' => now('Asia/Ho_Chi_Minh')->toDateString(),
                            'TrangThai' => 'DangThucHien',
                            'LoaiMucTieu' => 'Nuoc',
                            'DonViDo' => 'ml',
                        ])
                    );
                }
            }

            if (Schema::hasTable('user_goals') && array_key_exists('water_goal', $data) && $data['water_goal'] !== null && $data['water_goal'] !== '') {
                DB::table('user_goals')->updateOrInsert(
                    [
                        'NguoiDungID' => $id,
                        'Loai' => 'UongNuoc',
                    ],
                    $this->onlyExistingColumns('user_goals', [
                        'NguoiDungID' => $id,
                        'Loai' => 'UongNuoc',
                        'GiaTri' => (float) $data['water_goal'],
                        'DonVi' => 'ml',
                        'ChuKyLap' => 'HangNgay',
                        'BatNhac' => 1,
                        'NgayTrongTuan' => '1,2,3,4,5,6,7',
                        'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
                        'NgayTao' => now('Asia/Ho_Chi_Minh'),
                    ])
                );
            }

            Log::warning('admin.account.mode_update', ['account_id' => $id]);
            Cache::forget('admin:stats:v2');
        });

        return response()->json([
            'success' => true,
            'message' => 'Da cap nhat che do nguoi dung',
            'preferences' => $this->userPreferences($id),
            'goals' => $this->userGoals($id),
        ]);
    }

    public function storeFood(Request $request)
    {
        return $this->addThucPham($request);
    }

    public function getSystemConfig()
    {
        return response()->json([
            'success' => true,
            'data' => $this->systemConfigValues(),
            'meta' => [
                'storage' => Schema::hasTable('system_configs') ? 'database' : 'defaults',
                'table_ready' => Schema::hasTable('system_configs'),
            ],
        ]);
    }

    public function updateSystemConfig(Request $request)
    {
        if (! Schema::hasTable('system_configs')) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa có bảng system_configs. Vui lòng chạy migration trước khi lưu cấu hình.',
            ], 422);
        }

        $data = $this->validateJson($request, [
            'nguong_sut_can' => ['sometimes', 'required', 'numeric', 'min:0.1', 'max:100'],
            'so_ngay_theo_doi' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'nuoc_toi_thieu' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000'],
            'che_do_bao_tri' => ['sometimes', 'required', 'boolean'],
            'so_ngay_xoa_log' => ['sometimes', 'required', 'integer', 'min:1', 'max:3650'],
        ]);

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có cấu hình nào được gửi lên.',
            ], 422);
        }

        $definitions = $this->systemConfigDefinitions();
        $adminId = (int) (Auth::guard('admin')->id() ?: $request->header('X-Admin-User-Id', 0));
        $now = now('Asia/Ho_Chi_Minh');

        DB::transaction(function () use ($data, $definitions, $adminId, $now) {
            foreach ($data as $key => $value) {
                if (! isset($definitions[$key])) {
                    continue;
                }

                $type = $definitions[$key]['type'];
                $payload = [
                    'value' => $this->stringifySystemConfigValue($value, $type),
                    'type' => $type,
                    'config_group' => $definitions[$key]['group'],
                    'updated_by' => $adminId ?: null,
                    'updated_at' => $now,
                ];

                $exists = DB::table('system_configs')->where('key', $key)->exists();
                $exists
                    ? DB::table('system_configs')->where('key', $key)->update($payload)
                    : DB::table('system_configs')->insert(['key' => $key, 'created_at' => $now, ...$payload]);
            }
        });

        Cache::forget('admin:system_config');
        Cache::forget('admin:stats:v2');
        Cache::forget('admin:stats:v3');

        $config = $this->systemConfigValues();
        $prunedLogs = isset($data['so_ngay_xoa_log'])
            ? $this->pruneOldSystemLogs((int) $config['so_ngay_xoa_log'])
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu cấu hình hệ thống.',
            'data' => $config,
            'pruned_logs' => $prunedLogs,
        ]);
    }

    public function addThucPham(Request $request)
    {
        if (! Schema::hasTable('thucpham')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thực phẩm'], 404);
        }

        $data = $this->validatedFood($request);
        if ($this->foodMasterExists($data['ten_thuc_pham'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu này đã tồn tại trên hệ thống, vui lòng không nhập trùng!',
            ], 409);
        }

        $newImage = null;
        try {
            $newImage = $this->prepareMasterImageValue($request, 'thucpham');
            if ($this->imageInputWasProvided($request)) {
                $data['hinh_anh'] = $newImage;
            }

            $id = DB::table('thucpham')->insertGetId($this->foodPayload($data));
            $food = DB::table('thucpham')->where('ID', $id)->first();
            Cache::forget('admin:stats:v2');
        } catch (\Throwable $e) {
            $this->deleteStoredImage($newImage);
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Thêm thực phẩm thành công!',
            'data' => $this->formatFoodMaster($food),
        ], 201);
    }

    public function getDanhSachThucPham(Request $request)
    {
        if (! Schema::hasTable('thucpham')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thực phẩm'], 404);
        }

        $perPage = min(max($request->integer('per_page', $request->integer('limit', 30)), 5), 100);
        $query = DB::table('thucpham');
        $this->applyActiveMasterScope($query, 'thucpham');

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $this->whereLikeAny($query, 'thucpham', ['ten_thuc_pham', 'Ten', 'loai_thuc_pham', 'LoaiThucPham', 'thanh_phan', 'Keywords'], $keyword);
        }

        if ($request->filled('loai_thuc_pham')) {
            $this->whereEqualsAny($query, 'thucpham', ['loai_thuc_pham', 'LoaiThucPham'], (string) $request->query('loai_thuc_pham'));
        }

        $page = $query->orderBy($this->firstExistingColumn('thucpham', ['ten_thuc_pham', 'Ten', 'ID']) ?? 'ID')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn ($row) => $this->formatFoodMaster($row))->values(),
            'meta' => $this->paginationMeta($page),
        ]);
    }

    public function updateFood(Request $request, int $id)
    {
        if (! Schema::hasTable('thucpham')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thực phẩm'], 404);
        }

        $data = $this->validatedFood($request, false);
        $query = DB::table('thucpham')->where('ID', $id);
        $oldRow = $query->first();
        if (! $oldRow) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thực phẩm'], 404);
        }

        $oldImage = $this->rowValue($oldRow, ['hinh_anh', 'HinhAnh'], null);
        $newImage = null;
        $imageProvided = $this->imageInputWasProvided($request);

        try {
            if ($imageProvided) {
                $newImage = $this->prepareMasterImageValue($request, 'thucpham');
                $data['hinh_anh'] = $newImage;
            }

            $query->update($this->foodPayload($data, true));
            if ($imageProvided && $this->imageValueChanged($oldImage, $newImage)) {
                $this->deleteStoredImage($oldImage);
            }
            Cache::forget('admin:stats:v2');
        } catch (\Throwable $e) {
            if ($imageProvided && $this->imageValueChanged($oldImage, $newImage)) {
                $this->deleteStoredImage($newImage);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thực phẩm thành công!',
            'data' => $this->formatFoodMaster(DB::table('thucpham')->where('ID', $id)->first()),
        ]);
    }

    public function deleteFood(int $id)
    {
        return $this->xoaThucPham($id);
    }

    public function xoaThucPham(int $id)
    {
        return $this->softDeleteMasterRow('thucpham', $id, 'admin.food.soft_delete', 'Đã xóa mềm thực phẩm.');
    }

    public function storeMedicine(Request $request)
    {
        return $this->addThuoc($request);
    }

    public function addThuoc(Request $request)
    {
        if (! Schema::hasTable('thuoc')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thuốc'], 404);
        }

        $data = $this->validatedMedicine($request);
        if ($this->medicineMasterExists($data['ten_thuoc'], $data['hoat_chat'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu này đã tồn tại trên hệ thống, vui lòng không nhập trùng!',
            ], 409);
        }

        $newImage = null;
        try {
            $newImage = $this->prepareMasterImageValue($request, 'thuoc');
            if ($this->imageInputWasProvided($request)) {
                $data['hinh_anh'] = $newImage;
            }

            $id = DB::table('thuoc')->insertGetId($this->medicinePayload($data));
            $medicine = DB::table('thuoc')->where('ID', $id)->first();
            Cache::forget('admin:stats:v2');
        } catch (\Throwable $e) {
            $this->deleteStoredImage($newImage);
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Thêm thuốc thành công!',
            'data' => $this->formatMedicineMaster($medicine),
        ], 201);
    }

    public function getDanhSachThuoc(Request $request)
    {
        if (! Schema::hasTable('thuoc')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thuốc'], 404);
        }

        $perPage = min(max($request->integer('per_page', $request->integer('limit', 30)), 5), 100);
        $query = DB::table('thuoc');
        $this->applyActiveMasterScope($query, 'thuoc');

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $this->whereLikeAny($query, 'thuoc', ['ten_thuoc', 'TenThuoc', 'hoat_chat', 'HoatChat'], $keyword);
        }

        if ($request->filled('hoat_chat')) {
            $this->whereEqualsAny($query, 'thuoc', ['hoat_chat', 'HoatChat'], (string) $request->query('hoat_chat'));
        }

        $page = $query->orderBy($this->firstExistingColumn('thuoc', ['ten_thuoc', 'TenThuoc', 'ID']) ?? 'ID')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn ($row) => $this->formatMedicineMaster($row))->values(),
            'meta' => $this->paginationMeta($page),
        ]);
    }

    public function updateMedicine(Request $request, int $id)
    {
        if (! Schema::hasTable('thuoc')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thuốc'], 404);
        }

        $data = $this->validatedMedicine($request, false);
        $query = DB::table('thuoc')->where('ID', $id);
        $oldRow = $query->first();
        if (! $oldRow) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thuốc'], 404);
        }

        $oldImage = $this->rowValue($oldRow, ['hinh_anh', 'HinhAnh', 'IconThuoc'], null);
        $newImage = null;
        $imageProvided = $this->imageInputWasProvided($request);

        try {
            if ($imageProvided) {
                $newImage = $this->prepareMasterImageValue($request, 'thuoc');
                $data['hinh_anh'] = $newImage;
            }

            $query->update($this->medicinePayload($data, true));
            if ($imageProvided && $this->imageValueChanged($oldImage, $newImage)) {
                $this->deleteStoredImage($oldImage);
            }
            Cache::forget('admin:stats:v2');
        } catch (\Throwable $e) {
            if ($imageProvided && $this->imageValueChanged($oldImage, $newImage)) {
                $this->deleteStoredImage($newImage);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thuốc thành công!',
            'data' => $this->formatMedicineMaster(DB::table('thuoc')->where('ID', $id)->first()),
        ]);
    }

    public function deleteMedicine(int $id)
    {
        return $this->xoaThuoc($id);
    }

    public function updateThuoc(Request $request, int $id)
    {
        return $this->updateMedicine($request, $id);
    }

    public function xoaThuoc(int $id)
    {
        return $this->softDeleteMasterRow('thuoc', $id, 'admin.medicine.soft_delete', 'Đã xóa mềm thuốc.');
    }

    public function addVanDong(Request $request)
    {
        if (! Schema::hasTable('hoatdong')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng vận động'], 404);
        }

        $data = $this->validatedActivity($request);
        if ($this->activityMasterExists($data['ten_van_dong'], (float) $data['chi_so_met'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu này đã tồn tại trên hệ thống, vui lòng không nhập trùng!',
            ], 409);
        }

        $id = DB::table('hoatdong')->insertGetId($this->activityPayload($data));
        $activity = DB::table('hoatdong')->where('ID', $id)->first();
        Cache::forget('admin:stats:v2');

        return response()->json([
            'success' => true,
            'message' => 'Thêm vận động thành công!',
            'data' => $this->formatActivityMaster($activity),
        ], 201);
    }

    public function getDanhSachVanDong(Request $request)
    {
        if (! Schema::hasTable('hoatdong')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng vận động'], 404);
        }

        $perPage = min(max($request->integer('per_page', $request->integer('limit', 30)), 5), 100);
        $query = DB::table('hoatdong');
        $this->applyActiveMasterScope($query, 'hoatdong');

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $this->whereLikeAny($query, 'hoatdong', ['ten_van_dong', 'TenHoatDong', 'mo_ta', 'MoTa'], $keyword);
        }

        $page = $query->orderBy($this->firstExistingColumn('hoatdong', ['ten_van_dong', 'TenHoatDong', 'ID']) ?? 'ID')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn ($row) => $this->formatActivityMaster($row))->values(),
            'meta' => $this->paginationMeta($page),
        ]);
    }

    public function deleteVanDong(int $id)
    {
        return $this->softDeleteMasterRow('hoatdong', $id, 'admin.activity_master.soft_delete', 'Đã xóa mềm vận động.');
    }

    public function addHoatDong(Request $request)
    {
        if (! Schema::hasTable('hoat_dongs')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thư viện hoạt động chuẩn.'], 404);
        }

        $data = $this->validatedHoatDong($request);
        $exists = DB::table('hoat_dongs')
            ->where('ten_hoat_dong', $data['ten_hoat_dong'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu này đã tồn tại trên hệ thống, vui lòng không nhập trùng!',
            ], 409);
        }

        $newImage = null;
        $now = now('Asia/Ho_Chi_Minh');
        try {
            $newImage = $this->prepareMasterImageValue($request, 'hoat_dongs');
            if ($this->imageInputWasProvided($request)) {
                $data['hinh_anh'] = $newImage;
            }

            $payload = [
                'ten_hoat_dong' => $data['ten_hoat_dong'],
                'mo_ta' => $data['mo_ta'] ?? null,
                'chi_so_met' => (float) $data['chi_so_met'],
                'hinh_anh' => $data['hinh_anh'] ?? null,
                'hinh_anh_icon' => $data['hinh_anh'] ?? ($data['hinh_anh_icon'] ?? null),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $id = DB::table('hoat_dongs')->insertGetId($this->onlyExistingColumns('hoat_dongs', $payload));
        } catch (\Throwable $e) {
            $this->deleteStoredImage($newImage);
            throw $e;
        }

        Cache::forget('admin:stats:v2');

        return response()->json([
            'success' => true,
            'message' => 'Thêm hoạt động vào thư viện chuẩn thành công!',
            'data' => $this->formatHoatDongMaster(DB::table('hoat_dongs')->where('id', $id)->first()),
        ], 201);
    }

    public function getDanhSachHoatDong(Request $request)
    {
        if (! Schema::hasTable('hoat_dongs')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thư viện hoạt động chuẩn.'], 404);
        }

        $query = DB::table('hoat_dongs')
            ->where('is_active', true);

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $query->where(function ($inner) use ($keyword) {
                $inner->where('ten_hoat_dong', 'like', "%{$keyword}%")
                    ->orWhere('mo_ta', 'like', "%{$keyword}%");
            });
        }

        $rows = $query
            ->orderBy('ten_hoat_dong')
            ->get()
            ->map(fn ($row) => $this->formatHoatDongMaster($row))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function deleteHoatDong(int $id)
    {
        if (! Schema::hasTable('hoat_dongs')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thư viện hoạt động chuẩn.'], 404);
        }

        $row = DB::table('hoat_dongs')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy hoạt động.'], 404);
        }

        DB::transaction(function () use ($id, $row) {
            DB::table('hoat_dongs')->where('id', $id)->update([
                'is_active' => false,
                'updated_at' => now('Asia/Ho_Chi_Minh'),
            ]);
            $this->deleteStoredImage($this->rowValue($row, ['hinh_anh', 'hinh_anh_icon'], null));
            Cache::forget('admin:stats:v2');
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa mềm hoạt động khỏi thư viện chuẩn.',
        ]);
    }

    public function createNotification(Request $request)
    {
        if (! Schema::hasTable('thongbao')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thông báo'], 404);
        }

        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:taikhoan,ID',
            'send_all' => 'nullable|boolean',
            'type' => 'nullable|string|max:100',
            'content' => 'required|string|max:2000',
        ]);

        if (! $request->boolean('send_all') && empty($data['user_id'])) {
            return response()->json(['success' => false, 'message' => 'Chon nguoi nhan thong bao'], 422);
        }

        $now = now('Asia/Ho_Chi_Minh');
        $sent = DB::transaction(function () use ($request, $data, $now) {
            $sent = 0;
            $makeRow = fn ($userId) => [
                'NguoiDungID' => $userId,
                'LoaiThongBao' => $data['type'] ?? 'HeThong',
                'ThoiGian' => $now,
                'NoiDung' => $data['content'],
                'TrangThaiGui' => 'DaGui',
                'DaDoc' => 0,
            ];

            if ($request->boolean('send_all')) {
                if (Schema::hasTable('taikhoan')) {
                    DB::table('taikhoan')->select('ID')->orderBy('ID')->chunk(500, function ($users) use (&$sent, $makeRow) {
                        $rows = $users->map(fn ($user) => $makeRow($user->ID))->all();
                        if (! empty($rows)) {
                            DB::table('thongbao')->insert($rows);
                            $sent += count($rows);
                        }
                    });
                }
            } else {
                DB::table('thongbao')->insert([$makeRow((int) $data['user_id'])]);
                $sent = 1;
            }

            Log::warning('admin.notification.create', ['send_all' => $request->boolean('send_all'), 'sent' => $sent, 'type' => $data['type'] ?? 'HeThong']);
            Cache::forget('admin:stats:v2');

            return $sent;
        });

        return response()->json(['success' => true, 'message' => 'Da gui '.$sent.' thong bao'], 201);
    }

    public function notifications(Request $request)
    {
        if (! Schema::hasTable('thongbao')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'per_page' => 20, 'last_page' => 1],
            ]);
        }

        $perPage = min(max($request->integer('per_page', 20), 5), 80);
        $query = DB::table('thongbao as tb');

        if ($request->filled('read') && Schema::hasColumn('thongbao', 'DaDoc')) {
            $query->where('tb.DaDoc', $request->boolean('read') ? 1 : 0);
        }
        if ($request->filled('type') && Schema::hasColumn('thongbao', 'LoaiThongBao')) {
            $query->where('tb.LoaiThongBao', $request->query('type'));
        }
        if ($request->filled('q') && Schema::hasColumn('thongbao', 'NoiDung')) {
            $keyword = trim((string) $request->query('q'));
            $query->where('tb.NoiDung', 'like', "%{$keyword}%");
        }

        $page = $query
            ->orderByDesc(Schema::hasColumn('thongbao', 'ThoiGian') ? 'tb.ThoiGian' : 'tb.ID')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->values(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function markNotificationRead(int $id)
    {
        if (! Schema::hasTable('thongbao')) {
            return response()->json(['success' => false, 'message' => 'Chua co bang thong bao'], 404);
        }

        $changed = $this->markNotificationRowRead($id);
        Cache::forget('admin:stats:v2');
        Cache::forget('admin:stats:v3');

        return response()->json([
            'success' => true,
            'message' => 'Đã đánh dấu thông báo là đã đọc.',
            'changed' => $changed,
            'meta' => ['unread' => $this->notificationUnreadCount()],
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $type = Str::lower((string) $request->input('type', 'notification'));
        $changed = 0;

        if (Str::startsWith($id, 'he_thong_canh_bao:')) {
            $changed = $this->markHeThongCanhBaoReviewed((int) Str::after($id, 'he_thong_canh_bao:'));
        } elseif (in_array($type, ['he_thong_canh_bao', 'system_alert'], true)) {
            $changed = $this->markHeThongCanhBaoReviewed((int) $id);
        } elseif (in_array($type, ['notification', 'thongbao'], true)) {
            $changed = $this->markNotificationRowRead((int) $id);
        } elseif (in_array($type, ['risk_event', 'risk-event'], true)) {
            $changed = $this->markRiskEventReviewed((int) $id);
        } elseif (in_array($type, ['user', 'account'], true)) {
            $result = $this->markUserAsReviewed((int) $id);
            $changed = (int) ($result['changed'] ?? 0);
        } else {
            $changed = $this->storeAlertReview((string) $id, [
                'AlertType' => $type ?: 'generated_alert',
                'NguoiDungID' => $request->integer('user_id') ?: null,
                'Title' => $request->input('title'),
            ]);
        }

        Cache::forget('admin:stats:v2');
        Cache::forget('admin:stats:v3');

        return response()->json([
            'success' => true,
            'message' => 'Da ghi nhan trang thai da xem/da xu ly.',
            'changed' => $changed,
        ]);
    }

    public function updateAlertStatus(Request $request, int $id)
    {
        if (! Schema::hasTable('he_thong_canh_baos')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng cảnh báo hệ thống.'], 404);
        }

        $data = $this->validateJson($request, [
            'status' => ['required', 'in:pending,reviewed'],
        ]);

        $alert = DB::table('he_thong_canh_baos')->where('id', $id)->first();
        if (! $alert) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy cảnh báo.'], 404);
        }

        DB::transaction(function () use ($id, $data, $alert) {
            DB::table('he_thong_canh_baos')->where('id', $id)->update([
                'status' => $data['status'],
                'updated_at' => now('Asia/Ho_Chi_Minh'),
            ]);

            if ($data['status'] === 'reviewed') {
                $this->storeAlertReview('he_thong_canh_bao:'.$id, [
                    'AlertType' => (string) ($alert->loai_canh_bao ?? 'he_thong_canh_bao'),
                    'NguoiDungID' => (int) ($alert->user_id ?? 0) ?: null,
                    'Title' => $this->alertTypeLabel((string) ($alert->loai_canh_bao ?? '')),
                ]);
            }

            Cache::forget('admin:stats:v2');
            Cache::forget('admin:stats:v3');
        });

        $freshQuery = DB::table('he_thong_canh_baos as c');
        $freshSelect = ['c.*'];
        if (Schema::hasTable('taikhoan')) {
            $freshQuery->leftJoin('taikhoan as t', 't.ID', '=', 'c.user_id');
            $freshSelect[] = 't.Email';
        } else {
            $freshSelect[] = DB::raw('NULL as Email');
        }
        if (Schema::hasTable('hosonguoidung')) {
            $freshQuery->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 'c.user_id');
            $freshSelect[] = 'h.Ten';
        } else {
            $freshSelect[] = DB::raw('NULL as Ten');
        }

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'reviewed' ? 'Đã xác nhận xử lý cảnh báo.' : 'Đã chuyển cảnh báo về trạng thái chờ xử lý.',
            'data' => $this->formatHeThongCanhBao($freshQuery
                ->where('c.id', $id)
                ->first($freshSelect)),
        ]);
    }

    public function deleteNotification(int $id)
    {
        if (! Schema::hasTable('thongbao')) {
            return response()->json(['success' => false, 'message' => 'Chua co bang thong bao'], 404);
        }

        $deleted = DB::table('thongbao')->where('ID', $id)->delete();
        Cache::forget('admin:stats:v2');
        Cache::forget('admin:stats:v3');

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa thông báo.',
            'changed' => $deleted,
            'meta' => ['unread' => $this->notificationUnreadCount()],
        ]);
    }

    public function riskEvents(Request $request)
    {
        if (! Schema::hasTable('risk_events')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'per_page' => 20, 'last_page' => 1],
            ]);
        }

        $perPage = min(max($request->integer('per_page', 20), 5), 80);
        $query = DB::table('risk_events as r');
        if (Schema::hasTable('taikhoan')) {
            $query->leftJoin('taikhoan as t', 't.ID', '=', 'r.NguoiDungID');
        }
        if (Schema::hasTable('hosonguoidung')) {
            $query->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 'r.NguoiDungID');
        }
        if ($request->filled('severity')) {
            $query->where('r.Severity', $request->query('severity'));
        }
        if ($request->filled('status')) {
            $query->where('r.Status', $request->query('status'));
        }

        $page = $query
            ->orderByDesc('r.LastDetectedAt')
            ->orderByDesc('r.ID')
            ->paginate($perPage, [
                'r.*',
                Schema::hasTable('taikhoan') ? 't.Email' : DB::raw('NULL as Email'),
                Schema::hasTable('hosonguoidung') ? 'h.Ten' : DB::raw('NULL as Ten'),
            ]);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn ($row) => [
                'id' => (int) $row->ID,
                'user_id' => (int) $row->NguoiDungID,
                'user' => $row->Ten ?: $row->Email ?: ('Nguoi dung #'.$row->NguoiDungID),
                'rule' => $row->RuleCode,
                'category' => $row->Category,
                'severity' => $row->Severity,
                'score' => (int) $row->RiskScore,
                'title' => $row->Title,
                'message' => $row->Message,
                'action' => $row->Action,
                'status' => $row->Status,
                'occurrence_count' => (int) $row->OccurrenceCount,
                'last_detected_at' => $row->LastDetectedAt,
            ])->values(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function quetHeThongCanhBao(Request $request)
    {
        if (! Schema::hasTable('he_thong_canh_baos')) {
            return response()->json([
                'success' => false,
                'message' => 'Chua co bang he_thong_canh_baos. Vui long chay migration truoc khi quet canh bao.',
            ], 422);
        }

        try {
            $alerts = collect([
                ...$this->scanWeightAbnormalAlerts(),
                ...$this->scanOvereatingAlerts(),
                ...$this->scanStarvationAlerts(),
                ...$this->scanLowWaterAlerts(),
                ...$this->scanDrugAbuseAlerts(),
            ]);

            Cache::forget('admin:stats:v2');

            return response()->json([
                'success' => true,
                'message' => 'Da quet du lieu y te bat thuong thanh cong.',
                'created_count' => $alerts->where('created', true)->count(),
                'updated_count' => $alerts->where('updated', true)->count(),
                'skipped_count' => $alerts->where('skipped', true)->count(),
                'data' => $alerts->values(),
            ]);
        } catch (\Throwable $e) {
            Log::error('admin.smart_alert.scan_failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Khong the quet canh bao y te. Vui long xem log server.',
            ], 500);
        }
    }

    public function getHeThongCanhBao(Request $request)
    {
        if (! Schema::hasTable('he_thong_canh_baos')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'per_page' => 20, 'last_page' => 1],
            ]);
        }

        $perPage = min(max($request->integer('per_page', 20), 5), 80);
        $query = HeThongCanhBao::query()
            ->with([
                'user' => fn ($userQuery) => $userQuery->select('ID', 'Email'),
                'user.profile' => fn ($profileQuery) => $profileQuery->select('ID', 'NguoiDungID', 'Ten', 'AnhDaiDien'),
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('loai_canh_bao')) {
            $query->where('loai_canh_bao', $request->query('loai_canh_bao'));
        }
        if ($request->filled('muc_do_nguy_hiem')) {
            $query->where('muc_do_nguy_hiem', $request->query('muc_do_nguy_hiem'));
        }
        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));
            $like = "%{$keyword}%";
            $query->where(function ($inner) use ($like) {
                $inner->where('loai_canh_bao', 'like', $like)
                    ->orWhere('noi_dung_chi_tiet', 'like', $like)
                    ->orWhereHas('user', function ($userQuery) use ($like) {
                        $userQuery->where('Email', 'like', $like)
                            ->orWhereHas('profile', fn ($profileQuery) => $profileQuery->where('Ten', 'like', $like));
                    });
            });
        }

        $page = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN muc_do_nguy_hiem = 'High' THEN 0 ELSE 1 END")
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->paginate($perPage);


        return response()->json([
            'success' => true,
            'data' => $page->getCollection()
                ->map(fn (HeThongCanhBao $alert) => $this->formatHeThongCanhBaoModel($alert))
                ->values(),
            'meta' => $this->paginationMeta($page),
        ]);
    }

    public function resources(Request $request)
    {
        $type = $request->query('type', 'foods');
        $perPage = min(max($request->integer('per_page', $request->integer('limit', 20)), 5), 80);

        $resource = match ($type) {
            'medicines' => $this->resourceRows('thuoc', ['ID', 'ten_thuoc', 'TenThuoc', 'hoat_chat', 'HoatChat', 'ham_luong_goc', 'lieu_luong_goc', 'LieuLuong', 'mo_ta', 'MoTa', 'tac_dung_phu', 'TacDungPhu', 'canh_bao_ghi_chu', 'CanhBao', 'nhom_thuoc', 'NhomThuoc', 'hinh_anh', 'IconThuoc'], 'ID', $perPage, $request),
            'reminders' => $this->resourceRows('nhacnho', ['ID', 'NguoiDungID', 'LoaiDoiTuong', 'DoiTuongId', 'ThoiGian', 'LapLai', 'NgayTrongTuan', 'TrangThai'], 'ID', $perPage, $request),
            'scores' => $this->resourceRows('diemsuckhoe', ['ID', 'NguoiDungID', 'Diem', 'NgayTinh', 'NhanXetAI'], 'ID', $perPage, $request),
            'foods' => $this->resourceRows('thucpham', ['ID', 'Ten', 'Calo', 'Protein', 'Carb', 'ChatBeo', 'DonVi', 'KhoiLuongGram', 'LoaiThucPham', 'Keywords'], 'ID', $perPage, $request),
            default => ['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'per_page' => $perPage, 'last_page' => 1]],
        };

        return response()->json([
            'success' => true,
            'type' => $type,
            ...$resource,
        ]);
    }

    private function dashboardPeriod(Request $request): array
    {
        $timezone = 'Asia/Ho_Chi_Minh';
        $from = null;
        $to = null;
        $label = 'Toan bo du lieu';

        if ($request->filled('date')) {
            $from = \Illuminate\Support\Carbon::parse($request->query('date'), $timezone)->startOfDay();
            $to = $from->copy()->endOfDay();
            $label = 'Ngay '.$from->format('d/m/Y');
        } elseif ($request->filled('month')) {
            $from = \Illuminate\Support\Carbon::parse($request->query('month').'-01', $timezone)->startOfMonth();
            $to = $from->copy()->endOfMonth();
            $label = 'Thang '.$from->format('m/Y');
        } elseif ($request->filled('year')) {
            $from = now($timezone)->setDate((int) $request->query('year'), 1, 1)->startOfYear();
            $to = $from->copy()->endOfYear();
            $label = 'Nam '.$from->format('Y');
        } elseif ($request->filled('from') || $request->filled('to')) {
            $from = $request->filled('from') ? \Illuminate\Support\Carbon::parse($request->query('from'), $timezone)->startOfDay() : null;
            $to = $request->filled('to') ? \Illuminate\Support\Carbon::parse($request->query('to'), $timezone)->endOfDay() : null;
            $label = trim(($from ? $from->format('d/m/Y') : 'Tu dau').' - '.($to ? $to->format('d/m/Y') : 'den nay'));
        }

        return [
            'from' => $from?->toDateTimeString(),
            'to' => $to?->toDateTimeString(),
            'label' => $label,
        ];
    }

    private function dashboardOverview(array $accountStats, array $notificationStats, array $alerts, array $period): array
    {
        return [
            [
                'label' => 'Tai khoan',
                'value' => $accountStats['total'],
                'note' => $accountStats['active'].' dang hoat dong',
                'tone' => 'mint',
                ...$this->dashboardTarget('users', ['status' => 'all'], $period),
            ],
            [
                'label' => 'Thong bao chua doc',
                'value' => $notificationStats['unread'],
                'note' => $notificationStats['read'].' thong bao da doc',
                'tone' => 'lavender',
                ...$this->dashboardTarget('notifications', ['read' => 'unread'], $period),
            ],
            [
                'label' => 'Thuc pham',
                'value' => $this->countTable('thucpham'),
                'note' => 'Kho du lieu dinh duong',
                'tone' => 'peach',
                ...$this->dashboardTarget('foods', [], $period),
            ],
            [
                'label' => 'Thuoc',
                'value' => $this->countTable('thuoc'),
                'note' => 'Danh muc thuoc hien co',
                'tone' => 'mint',
                ...$this->dashboardTarget('medicines', [], $period),
            ],
            [
                'label' => 'Hoat dong',
                'value' => $this->countTable(Schema::hasTable('activity_logs') ? 'activity_logs' : 'lichhoatdong'),
                'note' => 'Lich su van dong',
                'tone' => 'blue',
                ...$this->dashboardTarget('activities', [], $period),
            ],
            [
                'label' => 'Ca can theo doi',
                'value' => count($alerts),
                'note' => 'Chi dem cac ca chua duoc Admin xac nhan',
                'tone' => count($alerts) ? 'rose' : 'mint',
                ...$this->dashboardTarget('alerts', ['status' => 'open'], $period),
            ],
        ];
    }

    private function dashboardTarget(string $view, array $filters = [], array $period = []): array
    {
        $query = array_filter([
            ...$filters,
            'from' => $period['from'] ?? null,
            'to' => $period['to'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'view' => $view,
            'filters' => $query,
            'url' => route('admin.dashboard').'#'.$view,
        ];
    }

    private function applyDateWindow($query, string $table, ?string $column, array $period): void
    {
        $schemaColumn = $column && str_contains($column, '.') ? Str::afterLast($column, '.') : $column;
        if (! $column || ! $schemaColumn || ! Schema::hasColumn($table, $schemaColumn)) {
            return;
        }

        if (! empty($period['from'])) {
            $query->where($column, '>=', $period['from']);
        }
        if (! empty($period['to'])) {
            $query->where($column, '<=', $period['to']);
        }
    }

    private function accountAggregateStats(array $period = []): array
    {
        if (! Schema::hasTable('taikhoan')) {
            return ['total' => 0, 'active' => 0, 'locked' => 0, 'unreviewed' => 0];
        }

        $query = DB::table('taikhoan');
        $this->applyDateWindow($query, 'taikhoan', 'NgayTao', $period);

        $hasBlockedColumn = Schema::hasColumn('taikhoan', 'is_blocked');
        $hasActiveColumn = Schema::hasColumn('taikhoan', 'TrangThaiHoatDong');
        $activeSql = $hasBlockedColumn
            ? 'SUM(CASE WHEN is_blocked = 0 THEN 1 ELSE 0 END) as active'
            : ($hasActiveColumn
                ? 'SUM(CASE WHEN TrangThaiHoatDong = 1 THEN 1 ELSE 0 END) as active'
                : 'COUNT(*) as active');
        $lockedSql = $hasBlockedColumn
            ? 'SUM(CASE WHEN is_blocked = 1 THEN 1 ELSE 0 END) as locked'
            : ($hasActiveColumn
                ? 'SUM(CASE WHEN TrangThaiHoatDong = 0 THEN 1 ELSE 0 END) as locked'
                : '0 as locked');

        $row = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw($activeSql)
            ->selectRaw($lockedSql)
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'locked' => (int) ($row->locked ?? 0),
            'unreviewed' => $this->unreviewedAccountCount($period),
        ];
    }

    private function notificationAggregateStats($today, array $period = []): array
    {
        if (! Schema::hasTable('thongbao')) {
            return ['total' => 0, 'unread' => 0, 'read' => 0, 'today' => 0];
        }

        $query = DB::table('thongbao');
        $this->applyDateWindow($query, 'thongbao', 'ThoiGian', $period);

        $row = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(Schema::hasColumn('thongbao', 'DaDoc')
                ? 'SUM(CASE WHEN DaDoc = 0 THEN 1 ELSE 0 END) as unread'
                : '0 as unread')
            ->selectRaw(Schema::hasColumn('thongbao', 'ThoiGian')
                ? 'SUM(CASE WHEN DATE(ThoiGian) = ? THEN 1 ELSE 0 END) as today'
                : '0 as today', [$today->toDateString()])
            ->first();

        $total = (int) ($row->total ?? 0);
        $unread = (int) ($row->unread ?? 0);

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => max(0, $total - $unread),
            'today' => (int) ($row->today ?? 0),
        ];
    }

    private function featureStats(array $period = []): array
    {
        return [
            [
                'label' => 'Dinh dưỡng',
                'value' => $this->countTable('buaan', null, 'Ngay', $period),
                'note' => $this->countTable('chitietbuaan').' món đã ghi nhận',
            ],
            [
                'label' => 'Kho thực phẩm',
                'value' => $this->countTable('thucpham'),
                'note' => 'Dữ liệu món ăn và macro',
            ],
            [
                'label' => 'Uống nước',
                'value' => $this->countTable('theodoinuoc', null, 'Ngay', $period),
                'note' => $this->sumColumn('theodoinuoc', 'LuongNuoc').' ml đã ghi nhận',
            ],
            [
                'label' => 'Thuốc',
                'value' => $this->countTable('lichdungthuoc', null, 'ThoiGian', $period),
                'note' => $this->countTable('thuoc').' loại thuốc',
            ],
            [
                'label' => 'Vận động',
                'value' => $this->countTable(Schema::hasTable('activity_logs') ? 'activity_logs' : 'lichhoatdong'),
                'note' => 'Lịch tập và nhật ký hoạt động',
            ],
            [
                'label' => 'Chat AI',
                'value' => $this->countTable('chat_history'),
                'note' => 'Lượt hội thoại đã lưu',
            ],
            [
                'label' => 'Ho so suc khoe',
                'value' => $this->countTable('hososuckhoe'),
                'note' => $this->countTable('chisosuckhoe').' ban ghi chi so suc khoe',
            ],
            [
                'label' => 'Thong bao',
                'value' => $this->countTable('thongbao', null, 'ThoiGian', $period),
                'note' => $this->countTable('nhacnho').' cau hinh nhac nho',
            ],
            [
                'label' => 'Canh bao bat thuong',
                'value' => $this->countTable('risk_events', function ($query) {
                    if (Schema::hasColumn('risk_events', 'Status')) {
                        $query->where('Status', 'open');
                    }
                }, 'LastDetectedAt', $period),
                'note' => $this->countTable('risk_rules').' rule dang quan ly',
            ],
        ];
    }

    private function accountQuery()
    {
        $query = DB::table('taikhoan as t');

        if (Schema::hasTable('hosonguoidung')) {
            $query->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 't.ID');
        }

        return $query;
    }

    private function accountSelect(): array
    {
        $select = [
            't.ID',
            't.Email',
            Schema::hasColumn('taikhoan', 'is_blocked')
                ? 't.is_blocked'
                : DB::raw('0 as is_blocked'),
            Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')
                ? 't.TrangThaiHoatDong'
                : DB::raw('1 as TrangThaiHoatDong'),
            Schema::hasColumn('taikhoan', 'LanDangNhapCuoi')
                ? 't.LanDangNhapCuoi'
                : DB::raw('NULL as LanDangNhapCuoi'),
            Schema::hasColumn('taikhoan', 'NgayTao')
                ? 't.NgayTao'
                : DB::raw('NULL as NgayTao'),
        ];

        foreach (['Ten', 'GioiTinh', 'NgaySinh', 'ChieuCao', 'CanNang', 'AnhDaiDien'] as $column) {
            $select[] = Schema::hasTable('hosonguoidung') && Schema::hasColumn('hosonguoidung', $column)
                ? "h.{$column}"
                : DB::raw("NULL as {$column}");
        }

        return $select;
    }

    private function firstUserRow(string $table, int $userId)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'NguoiDungID')) {
            return null;
        }

        return DB::table($table)->where('NguoiDungID', $userId)->first();
    }

    private function latestUserRow(string $table, int $userId, string $orderColumn)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'NguoiDungID')) {
            return null;
        }

        $query = DB::table($table)->where('NguoiDungID', $userId);
        if (Schema::hasColumn($table, $orderColumn)) {
            $query->orderByDesc($orderColumn);
        }

        return $query->first();
    }

    private function recentRows(string $table, int $userId, string $orderColumn, int $limit)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'NguoiDungID')) {
            return [];
        }

        $query = DB::table($table)->where('NguoiDungID', $userId);
        if (Schema::hasColumn($table, $orderColumn)) {
            $query->orderByDesc($orderColumn);
        }

        return $query->limit($limit)->get();
    }

    private function recentMeals(int $userId)
    {
        if (! Schema::hasTable('buaan')) {
            return [];
        }

        $query = DB::table('buaan as b')->where('b.NguoiDungID', $userId);
        if (Schema::hasTable('chitietbuaan')) {
            $query->leftJoin('chitietbuaan as c', 'c.BuaAnID', '=', 'b.ID')
                ->selectRaw('b.ID, b.Ngay, b.LoaiBuaAn, COUNT(c.ID) as SoMon, COALESCE(SUM(c.TongCalo), 0) as TongCalo')
                ->groupBy('b.ID', 'b.Ngay', 'b.LoaiBuaAn');
        } else {
            $query->select('b.ID', 'b.Ngay', 'b.LoaiBuaAn');
        }

        return $query->orderByDesc('b.ID')->limit(8)->get();
    }

    private function recentMedicines(int $userId)
    {
        if (! Schema::hasTable('lichdungthuoc')) {
            return [];
        }

        $query = DB::table('lichdungthuoc as l')
            ->where('l.NguoiDungID', $userId);

        if (Schema::hasTable('thuoc')) {
            $query->leftJoin('thuoc as t', 't.ID', '=', 'l.ThuocID')
                ->select('l.ID', 'l.ThoiGian', 'l.TrangThai', 'l.LieuLuong', 'l.DonVi', 'l.GhiChu', 't.TenThuoc');
        } else {
            $query->select('l.*');
        }

        return $query->orderByDesc('l.ID')->limit(8)->get();
    }

    private function recentActivities(int $userId)
    {
        if (Schema::hasTable('activity_logs')) {
            return $this->recentRows('activity_logs', $userId, 'ID', 8);
        }

        if (! Schema::hasTable('lichhoatdong')) {
            return [];
        }

        $query = DB::table('lichhoatdong as l')
            ->where('l.NguoiDungID', $userId);

        if (Schema::hasTable('hoatdong')) {
            $query->leftJoin('hoatdong as h', 'h.ID', '=', 'l.HoatDongID')
                ->leftJoin('chitiethoatdong as c', 'c.LichHoatDongID', '=', 'l.ID')
                ->select('l.ID', 'l.ThoiGianBatDau', 'l.ThoiGianKetThuc', 'l.TrangThai', 'h.TenHoatDong', 'c.SoBuoc', 'c.CaloDot', 'c.QuangDuong');
        } else {
            $query->select('l.*');
        }

        return $query->orderByDesc('l.ID')->limit(8)->get();
    }

    private function resourceRows(string $table, array $columns, string $orderColumn, int $perPage, ?Request $request = null)
    {
        if (! Schema::hasTable($table)) {
            return ['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'per_page' => $perPage, 'last_page' => 1]];
        }

        $select = collect($columns)
            ->map(fn ($column) => Schema::hasColumn($table, $column) ? $column : DB::raw("NULL as {$column}"))
            ->all();

        $query = DB::table($table)->select($select);
        if (in_array($table, ['thuoc', 'thucpham', 'hoatdong'], true)) {
            $this->applyActiveMasterScope($query, $table);
        }
        if ($request) {
            $this->applyResourceFilters($query, $table, $request);
        }
        if (Schema::hasColumn($table, $orderColumn)) {
            $query->orderByDesc($orderColumn);
        }

        $page = $query->paginate($perPage);

        return [
            'data' => collect($page->items())->values(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
            ],
        ];
    }

    private function applyResourceFilters($query, string $table, Request $request): void
    {
        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            if ($table === 'thuoc' && Schema::hasColumn($table, 'TenThuoc')) {
                $query->where(function ($inner) use ($keyword, $table) {
                    $inner->where('TenThuoc', 'like', "%{$keyword}%");
                    if (Schema::hasColumn($table, 'HoatChat')) {
                        $inner->orWhere('HoatChat', 'like', "%{$keyword}%");
                    }
                });
            } elseif ($table === 'thucpham' && Schema::hasColumn($table, 'Ten')) {
                $query->where(function ($inner) use ($keyword, $table) {
                    $inner->where('Ten', 'like', "%{$keyword}%");
                    if (Schema::hasColumn($table, 'Keywords')) {
                        $inner->orWhere('Keywords', 'like', "%{$keyword}%");
                    }
                });
            }
        }

        foreach ([
            'status' => 'TrangThai',
            'group' => 'NhomThuoc',
            'active_ingredient' => 'HoatChat',
            'food_type' => 'LoaiThucPham',
        ] as $param => $column) {
            if ($param === 'status' && in_array($table, ['thuoc', 'thucpham'], true)) {
                continue;
            }
            if ($request->filled($param) && Schema::hasColumn($table, $column)) {
                $query->where($column, $request->query($param));
            }
        }
    }

    private function userPreferences(int $userId)
    {
        if (! Schema::hasTable('sothichnguoidung')) {
            return null;
        }

        return DB::table('sothichnguoidung')->where('NguoiDung', $userId)->first();
    }

    private function userGoals(int $userId)
    {
        $goals = collect();

        if (Schema::hasTable('muctieusuckhoe')) {
            $goals = $goals->merge(DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->orderByDesc('ID')
                ->limit(12)
                ->get());
        }

        if (Schema::hasTable('user_goals')) {
            $goals = $goals->merge(DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->orderByDesc('ID')
                ->limit(12)
                ->get()
                ->map(function ($goal) {
                    $goal->TenMucTieu = $goal->Loai ?? null;
                    $goal->LoaiMucTieu = $goal->Loai ?? null;
                    $goal->GiaTriMucTieu = $goal->GiaTri ?? null;
                    $goal->DonViDo = $goal->DonVi ?? null;

                    return $goal;
                }));
        }

        return $goals->take(12)->values();
    }

    private function adminLoginThrottleKey(Request $request): string
    {
        return 'admin-login:'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    private function systemConfigDefinitions(): array
    {
        return [
            'nguong_sut_can' => ['default' => 5.0, 'type' => 'float', 'group' => 'health_alerts'],
            'so_ngay_theo_doi' => ['default' => 30, 'type' => 'integer', 'group' => 'health_alerts'],
            'nuoc_toi_thieu' => ['default' => 2000, 'type' => 'integer', 'group' => 'health_alerts'],
            'che_do_bao_tri' => ['default' => false, 'type' => 'boolean', 'group' => 'system'],
            'so_ngay_xoa_log' => ['default' => 30, 'type' => 'integer', 'group' => 'system'],
        ];
    }

    private function systemConfigValues(): array
    {
        return Cache::remember('admin:system_config', 300, function () {
            $definitions = $this->systemConfigDefinitions();
            $values = collect($definitions)
                ->map(fn ($definition) => $definition['default'])
                ->all();

            if (! Schema::hasTable('system_configs')) {
                return $values;
            }

            DB::table('system_configs')
                ->whereIn('key', array_keys($definitions))
                ->get(['key', 'value', 'type'])
                ->each(function ($row) use (&$values, $definitions) {
                    $key = (string) $row->key;
                    $type = (string) ($row->type ?: ($definitions[$key]['type'] ?? 'string'));
                    $values[$key] = $this->castSystemConfigValue($row->value, $type, $definitions[$key]['default'] ?? null);
                });

            return $values;
        });
    }

    private function castSystemConfigValue($value, string $type, mixed $fallback = null): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $fallback,
            'integer' => is_numeric($value) ? (int) $value : (int) $fallback,
            'float' => is_numeric($value) ? (float) $value : (float) $fallback,
            default => $value ?? $fallback,
        };
    }

    private function stringifySystemConfigValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) (int) $value,
            'float' => rtrim(rtrim(sprintf('%.4F', (float) $value), '0'), '.'),
            default => (string) $value,
        };
    }

    private function pruneOldSystemLogs(int $retentionDays): int
    {
        $retentionDays = max(1, $retentionDays);
        $cutoff = now('Asia/Ho_Chi_Minh')->subDays($retentionDays);
        $deleted = 0;

        foreach ([
            ['admin_alert_reviews', ['created_at', 'CreatedAt', 'updated_at', 'UpdatedAt']],
        ] as [$table, $columns]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $column = $this->firstExistingColumn($table, $columns);
            if (! $column) {
                continue;
            }

            try {
                $deleted += DB::table($table)->where($column, '<', $cutoff)->delete();
            } catch (\Throwable $e) {
                Log::warning('admin.system_config.prune_failed', [
                    'table' => $table,
                    'column' => $column,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $deleted;
    }

    private function isAdminAccount(?object $admin): bool
    {
        if (! $admin) {
            return false;
        }

        $role = (string) ($admin->VaiTroID ?? $admin->role ?? $admin->Role ?? '');
        $isAdminRole = in_array($role, ['2', 'admin', 'Admin', 'ADMIN'], true);
        $isActive = (int) ($admin->TrangThaiHoatDong ?? 1) === 1;

        return $isAdminRole && $isActive;
    }

    private function validatedFood(Request $request, bool $isCreate = true): array
    {
        $required = $isCreate ? 'required' : 'sometimes|required';

        return $this->validateJson($request, [
            'ten_thuc_pham' => [$required, 'string', 'max:255'],
            'loai_thuc_pham' => [$required, 'string', 'max:100'],
            'calo_goc' => [$required, 'numeric', 'min:0'],
            'thanh_phan' => ['nullable', 'string'],
            'hinh_anh' => ['nullable'],
        ]);
    }

    private function validatedMedicine(Request $request, bool $isCreate = true): array
    {
        $required = $isCreate ? 'required' : 'sometimes|required';

        if (! $request->filled('ham_luong_goc') && $request->filled('lieu_luong_goc')) {
            $request->merge(['ham_luong_goc' => $request->input('lieu_luong_goc')]);
        }

        $data = $this->validateJson($request, [
            'ten_thuoc' => [$required, 'string', 'max:255'],
            'hoat_chat' => [$required, 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string'],
            'tac_dung_phu' => ['nullable', 'string'],
            'canh_bao_ghi_chu' => ['nullable', 'string'],
            'ham_luong_goc' => [$required, 'string', 'max:100'],
            'lieu_luong_goc' => ['nullable', 'string', 'max:100'],
            'nhom_thuoc' => ['nullable', 'string', 'max:100'],
            'loai_thuoc' => ['nullable', 'string', 'max:100'],
            'hinh_anh' => ['nullable'],
            'so_lan_moi_ngay' => ['prohibited'],
            'SoLanMoiNgay' => ['prohibited'],
            'lieu_luong' => ['prohibited'],
            'don_vi' => ['prohibited'],
            'DonVi' => ['prohibited'],
        ]);

        if (array_key_exists('ham_luong_goc', $data)) {
            $data['lieu_luong_goc'] = $data['ham_luong_goc'];
        }

        return $data;
    }

    private function validatedActivity(Request $request, bool $isCreate = true): array
    {
        $required = $isCreate ? 'required' : 'sometimes|required';

        return $this->validateJson($request, [
            'ten_van_dong' => [$required, 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string'],
            'chi_so_met' => [$required, 'numeric', 'min:0.1', 'max:50'],
            // MET is the scientific base; manual fixed duration/calories do not belong in master data.
            'thoi_gian' => ['prohibited'],
            'calo_tieu_hao' => ['prohibited'],
        ]);
    }

    private function validatedHoatDong(Request $request): array
    {
        return $this->validateJson($request, [
            'ten_hoat_dong' => ['required', 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string'],
            'chi_so_met' => ['required', 'numeric', 'min:1', 'max:50'],
            'hinh_anh_icon' => ['nullable', 'string', 'max:2048'],
            'hinh_anh' => ['nullable'],
            'thoi_gian' => ['prohibited'],
            'calo_tieu_hao' => ['prohibited'],
        ]);
    }

    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules, [
            'required' => ':attribute là bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'numeric' => ':attribute phải là số.',
            'min' => ':attribute không hợp lệ.',
            'max' => ':attribute vượt quá độ dài hoặc giá trị cho phép.',
            'unique' => ':attribute đã tồn tại.',
            'prohibited' => ':attribute không được gửi trong dữ liệu master.',
            'in' => ':attribute không nằm trong danh sách cho phép.',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    private function imageInputWasProvided(Request $request): bool
    {
        return $request->hasFile('hinh_anh')
            || $request->hasFile('image')
            || $request->hasFile('file')
            || $request->exists('hinh_anh');
    }

    private function prepareMasterImageValue(Request $request, string $table): ?string
    {
        if (! $this->imageInputWasProvided($request)) {
            return null;
        }

        if (! Schema::hasColumn($table, 'hinh_anh') && ! Schema::hasColumn($table, 'hinh_anh_icon')) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => "Bang {$table} chua co cot hinh_anh de luu duong dan anh.",
            ], 422));
        }

        $file = $request->file('hinh_anh') ?: $request->file('image') ?: $request->file('file');
        if ($file) {
            $validator = Validator::make(['hinh_anh' => $file], [
                'hinh_anh' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ]);

            if ($validator->fails()) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'File anh khong hop le.',
                    'errors' => $validator->errors(),
                ], 422));
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
            $filename = time().'_'.Str::random(28).'.'.$extension;
            $path = $file->storeAs('uploads/images', $filename, 'public');

            return asset('storage/'.$path);
        }

        $rawValue = $request->input('hinh_anh', '');
        if (is_array($rawValue) || is_object($rawValue)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Duong dan anh khong hop le.',
            ], 422));
        }

        $value = trim((string) $rawValue);
        if (mb_strlen($value) > 2048) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Duong dan anh vuot qua do dai cho phep.',
            ], 422));
        }

        return $value === '' ? null : $this->normalizeMasterImageReference($value);
    }

    private function normalizeMasterImageReference(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($this->isCompactIconToken($value)) {
            return $value;
        }

        return $this->absoluteImageUrl($value);
    }

    private function isCompactIconToken(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 64) {
            return false;
        }

        return ! Str::contains($value, ['/', '\\', ':', '.', '?', '#']);
    }

    private function absoluteImageUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (Str::startsWith($value, ['/storage/', 'storage/'])) {
            return url('/'.ltrim($value, '/'));
        }

        if (Str::startsWith($value, ['uploads/images/', '/uploads/images/'])) {
            return asset('storage/'.ltrim($value, '/'));
        }

        return url('/'.ltrim($value, '/'));
    }

    private function imageValueChanged(?string $oldValue, ?string $newValue): bool
    {
        return ($oldValue ?? '') !== ($newValue ?? '');
    }

    private function deleteStoredImage(?string $value): void
    {
        $path = $this->publicDiskImagePath($value);
        if (! $path) {
            return;
        }

        try {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning('admin.master_image.delete_failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function publicDiskImagePath(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        return Str::startsWith($path, 'uploads/images/') ? $path : null;
    }

    private function medicinePayload(array $data, bool $isUpdate = false): array
    {
        $payload = [];
        $this->mapIfPresent($payload, $data, 'ten_thuoc', ['ten_thuoc', 'TenThuoc'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'hoat_chat', ['hoat_chat', 'HoatChat'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'mo_ta', ['mo_ta', 'MoTa'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'tac_dung_phu', ['tac_dung_phu', 'TacDungPhu'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'canh_bao_ghi_chu', ['canh_bao_ghi_chu', 'CanhBao'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'ham_luong_goc', ['ham_luong_goc', 'lieu_luong_goc', 'LieuLuong'], null, $isUpdate);
        if (! $isUpdate || array_key_exists('nhom_thuoc', $data) || array_key_exists('loai_thuoc', $data)) {
            $group = $data['nhom_thuoc'] ?? $data['loai_thuoc'] ?? null;
            $payload['nhom_thuoc'] = $group;
            $payload['NhomThuoc'] = $group;
        }
        if (! $isUpdate || array_key_exists('hinh_anh', $data)) {
            $image = $data['hinh_anh'] ?? null;
            if (Schema::hasColumn('thuoc', 'hinh_anh')) {
                $payload['hinh_anh'] = $image;
            }
            if (Schema::hasColumn('thuoc', 'IconThuoc')) {
                $payload['IconThuoc'] = $this->safeLegacyIconValue($image);
            }
        }

        $this->stampMasterPayload($payload, $isUpdate);
        if (! $isUpdate) {
            $payload['is_active'] = true;
            $payload['deleted_at'] = null;
            $payload['TrangThai'] = 'hoat_dong';
        }

        return $this->onlyExistingColumns('thuoc', $payload);
    }

    private function safeLegacyIconValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($this->isCompactIconToken($value)) {
            if (preg_match('/[^\x20-\x7E]/u', $value)) {
                return 'image';
            }

            return mb_substr($value, 0, 64);
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $basename = basename((string) $path);

        return $basename !== '' ? mb_substr($basename, 0, 120) : 'image';
    }

    private function foodPayload(array $data, bool $isUpdate = false): array
    {
        $payload = [];
        $this->mapIfPresent($payload, $data, 'ten_thuc_pham', ['ten_thuc_pham', 'Ten'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'loai_thuc_pham', ['loai_thuc_pham', 'LoaiThucPham'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'calo_goc', ['calo_goc', 'Calo'], 0, $isUpdate);
        $this->mapIfPresent($payload, $data, 'thanh_phan', ['thanh_phan', 'Keywords'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'hinh_anh', ['hinh_anh', 'HinhAnh'], null, $isUpdate);

        $this->stampMasterPayload($payload, $isUpdate);
        if (! $isUpdate) {
            $payload['DonVi'] = 'Gram';
            $payload['KhoiLuongGram'] = 100;
            $payload['Protein'] = 0;
            $payload['Carb'] = 0;
            $payload['ChatBeo'] = 0;
            $payload['IsHealthy'] = 1;
            $payload['is_active'] = true;
            $payload['deleted_at'] = null;
        }

        return $this->onlyExistingColumns('thucpham', $payload);
    }

    private function activityPayload(array $data, bool $isUpdate = false): array
    {
        $payload = [];
        $this->mapIfPresent($payload, $data, 'ten_van_dong', ['ten_van_dong', 'TenHoatDong'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'mo_ta', ['mo_ta', 'MoTa'], null, $isUpdate);
        $this->mapIfPresent($payload, $data, 'chi_so_met', ['chi_so_met', 'MET'], null, $isUpdate);

        $this->stampMasterPayload($payload, $isUpdate);
        if (! $isUpdate) {
            $payload['is_active'] = true;
            $payload['deleted_at'] = null;
        }

        return $this->onlyExistingColumns('hoatdong', $payload);
    }

    private function mapIfPresent(array &$payload, array $data, string $key, array $columns, mixed $default, bool $isUpdate): void
    {
        if ($isUpdate && ! array_key_exists($key, $data)) {
            return;
        }

        $value = $data[$key] ?? $default;
        foreach ($columns as $column) {
            $payload[$column] = $value;
        }
    }

    private function stampMasterPayload(array &$payload, bool $isUpdate): void
    {
        $now = now('Asia/Ho_Chi_Minh');
        if (! $isUpdate) {
            $payload['created_at'] = $now;
            $payload['CreatedAt'] = $now;
        }
        $payload['updated_at'] = $now;
        $payload['NgayCapNhat'] = $now;
    }

    private function softDeleteMasterRow(string $table, int $id, string $logKey, string $message)
    {
        if (! Schema::hasTable($table)) {
            return response()->json(['success' => false, 'message' => "Chưa có bảng {$table}"], 404);
        }

        $query = DB::table($table)->where('ID', $id);
        $row = $query->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy dữ liệu cần xóa.'], 404);
        }

        $payload = $this->onlyExistingColumns($table, [
            'is_active' => false,
            'deleted_at' => now('Asia/Ho_Chi_Minh'),
            'updated_at' => now('Asia/Ho_Chi_Minh'),
            'TrangThai' => 'da_xoa',
        ]);

        if (empty($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Bảng chưa có cột is_active hoặc deleted_at để xóa mềm.',
            ], 422);
        }

        DB::transaction(function () use ($query, $payload, $logKey, $id, $table, $row) {
            $query->update($payload);
            if (in_array($table, ['thuoc', 'thucpham'], true)) {
                $this->deleteStoredImage($this->rowValue($row, ['hinh_anh', 'HinhAnh', 'IconThuoc'], null));
            }
            Log::warning($logKey, ['id' => $id]);
            Cache::forget('admin:stats:v2');
        });

        return response()->json(['success' => true, 'message' => $message]);
    }

    private function medicineMasterExists(string $name, string $activeIngredient): bool
    {
        $query = DB::table('thuoc');
        $this->applyActiveMasterScope($query, 'thuoc');
        $this->whereEqualsAny($query, 'thuoc', ['ten_thuoc', 'TenThuoc'], $name);
        $this->whereEqualsAny($query, 'thuoc', ['hoat_chat', 'HoatChat'], $activeIngredient);

        return $query->exists();
    }

    private function foodMasterExists(string $name): bool
    {
        $query = DB::table('thucpham');
        $this->applyActiveMasterScope($query, 'thucpham');
        $this->whereEqualsAny($query, 'thucpham', ['ten_thuc_pham', 'Ten'], $name);

        return $query->exists();
    }

    private function activityMasterExists(string $name, float $met): bool
    {
        $query = DB::table('hoatdong');
        $this->applyActiveMasterScope($query, 'hoatdong');
        $this->whereEqualsAny($query, 'hoatdong', ['ten_van_dong', 'TenHoatDong'], $name);
        $this->whereEqualsAny($query, 'hoatdong', ['chi_so_met', 'MET'], (string) $met);

        return $query->exists();
    }

    private function applyActiveMasterScope($query, string $table): void
    {
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        }
        if (Schema::hasColumn($table, 'TrangThai')) {
            $query->where(function ($inner) {
                $inner->whereNull('TrangThai')
                    ->orWhereNotIn('TrangThai', ['da_xoa', 'DaXoa', 'deleted']);
            });
        }
    }

    private function whereLikeAny($query, string $table, array $columns, string $value): void
    {
        $available = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
        if (empty($available)) {
            return;
        }

        $query->where(function ($inner) use ($available, $value) {
            foreach ($available as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $inner->{$method}($column, 'like', "%{$value}%");
            }
        });
    }

    private function whereEqualsAny($query, string $table, array $columns, string $value): void
    {
        $available = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
        if (empty($available)) {
            return;
        }

        $query->where(function ($inner) use ($available, $value) {
            foreach ($available as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $inner->{$method}($column, $value);
            }
        });
    }

    private function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function paginationMeta($page): array
    {
        return [
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
        ];
    }

    private function formatMedicineMaster($row): array
    {
        $strength = $this->rowValue($row, ['ham_luong_goc', 'lieu_luong_goc', 'LieuLuong'], '');

        return [
            'id' => (int) ($row->ID ?? $row->id ?? 0),
            'ten_thuoc' => $this->rowValue($row, ['ten_thuoc', 'TenThuoc'], ''),
            'hoat_chat' => $this->rowValue($row, ['hoat_chat', 'HoatChat'], ''),
            'mo_ta' => $this->rowValue($row, ['mo_ta', 'MoTa'], null),
            'tac_dung_phu' => $this->rowValue($row, ['tac_dung_phu', 'TacDungPhu'], null),
            'canh_bao_ghi_chu' => $this->rowValue($row, ['canh_bao_ghi_chu', 'CanhBao'], null),
            'ham_luong_goc' => $strength,
            'lieu_luong_goc' => $strength,
            'nhom_thuoc' => $this->rowValue($row, ['nhom_thuoc', 'NhomThuoc'], ''),
            'hinh_anh' => $this->imageUrlOrNull($this->rowValue($row, ['hinh_anh', 'IconThuoc'], null)),
            'is_active' => $this->rowIsActive($row),
            'deleted_at' => $this->rowValue($row, ['deleted_at'], null),
            'created_at' => $this->rowValue($row, ['created_at', 'CreatedAt'], null),
            'updated_at' => $this->rowValue($row, ['updated_at', 'NgayCapNhat'], null),
        ];
    }

    private function formatFoodMaster($row): array
    {
        return [
            'id' => (int) ($row->ID ?? $row->id ?? 0),
            'ten_thuc_pham' => $this->rowValue($row, ['ten_thuc_pham', 'Ten'], ''),
            'loai_thuc_pham' => $this->rowValue($row, ['loai_thuc_pham', 'LoaiThucPham'], ''),
            'calo_goc' => (float) $this->rowValue($row, ['calo_goc', 'Calo'], 0),
            'thanh_phan' => $this->rowValue($row, ['thanh_phan', 'Keywords'], null),
            'hinh_anh' => $this->imageUrlOrNull($this->rowValue($row, ['hinh_anh', 'HinhAnh'], null)),
            'is_active' => $this->rowIsActive($row),
            'deleted_at' => $this->rowValue($row, ['deleted_at'], null),
            'created_at' => $this->rowValue($row, ['created_at', 'CreatedAt'], null),
            'updated_at' => $this->rowValue($row, ['updated_at', 'NgayCapNhat'], null),
        ];
    }

    private function formatActivityMaster($row): array
    {
        return [
            'id' => (int) ($row->ID ?? $row->id ?? 0),
            'ten_van_dong' => $this->rowValue($row, ['ten_van_dong', 'TenHoatDong'], ''),
            'mo_ta' => $this->rowValue($row, ['mo_ta', 'MoTa'], null),
            'chi_so_met' => (float) $this->rowValue($row, ['chi_so_met', 'MET'], 0),
            'is_active' => $this->rowIsActive($row),
            'deleted_at' => $this->rowValue($row, ['deleted_at'], null),
            'created_at' => $this->rowValue($row, ['created_at', 'CreatedAt'], null),
            'updated_at' => $this->rowValue($row, ['updated_at', 'NgayCapNhat'], null),
        ];
    }

    private function formatHoatDongMaster($row): array
    {
        $name = $this->rowValue($row, ['ten_hoat_dong'], '');

        return [
            'id' => (int) ($row->id ?? 0),
            'ten_hoat_dong' => $name,
            'ten_van_dong' => $name,
            'mo_ta' => $this->rowValue($row, ['mo_ta'], null),
            'chi_so_met' => (float) $this->rowValue($row, ['chi_so_met'], 0),
            'hinh_anh' => $this->imageUrlOrNull($this->rowValue($row, ['hinh_anh', 'hinh_anh_icon'], null)),
            'hinh_anh_icon' => $this->imageUrlOrNull($this->rowValue($row, ['hinh_anh_icon', 'hinh_anh'], null)),
        ];
    }

    private function imageUrlOrNull(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $this->isCompactIconToken($value)) {
            return null;
        }

        return $this->absoluteImageUrl($value);
    }

    private function rowValue($row, array $columns, mixed $default = null): mixed
    {
        foreach ($columns as $column) {
            if (is_object($row) && property_exists($row, $column) && $row->{$column} !== null) {
                return $row->{$column};
            }
        }

        return $default;
    }

    private function rowIsActive($row): bool
    {
        $deletedAt = $this->rowValue($row, ['deleted_at'], null);
        if ($deletedAt !== null) {
            return false;
        }

        $isActive = $this->rowValue($row, ['is_active'], null);
        if ($isActive !== null) {
            return (bool) $isActive;
        }

        $status = $this->rowValue($row, ['TrangThai'], null);

        return ! in_array($status, ['da_xoa', 'DaXoa', 'deleted'], true);
    }

    private function onlyExistingColumns(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
            ->all();
    }

    private function weeklyStats($today): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $days[] = [
                'label' => $date->format('d/m'),
                'accounts' => $this->countByDate('taikhoan', 'NgayTao', $date->toDateString()),
                'notifications' => $this->countByDate('thongbao', 'ThoiGian', $date->toDateString()),
            ];
        }

        return $days;
    }

    private function notificationTypes()
    {
        if (! Schema::hasTable('thongbao') || ! Schema::hasColumn('thongbao', 'LoaiThongBao')) {
            return [];
        }

        return DB::table('thongbao')
            ->select('LoaiThongBao', DB::raw('COUNT(*) as total'))
            ->groupBy('LoaiThongBao')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->LoaiThongBao ?: 'Khác',
                'value' => (int) $row->total,
            ])
            ->values();
    }

    private function recentNotifications()
    {
        if (! Schema::hasTable('thongbao')) {
            return [];
        }

        $query = DB::table('thongbao as tb');
        if (Schema::hasTable('taikhoan')) {
            $query->leftJoin('taikhoan as t', 't.ID', '=', 'tb.NguoiDungID');
        }
        if (Schema::hasTable('hosonguoidung')) {
            $query->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 'tb.NguoiDungID');
        }

        return $query
            ->orderByDesc(Schema::hasColumn('thongbao', 'ThoiGian') ? 'tb.ThoiGian' : 'tb.ID')
            ->limit(8)
            ->get([
                'tb.ID',
                Schema::hasColumn('thongbao', 'LoaiThongBao') ? 'tb.LoaiThongBao' : DB::raw('NULL as LoaiThongBao'),
                Schema::hasColumn('thongbao', 'NoiDung') ? 'tb.NoiDung' : DB::raw('NULL as NoiDung'),
                Schema::hasColumn('thongbao', 'DaDoc') ? 'tb.DaDoc' : DB::raw('1 as DaDoc'),
                Schema::hasColumn('thongbao', 'ThoiGian') ? 'tb.ThoiGian' : DB::raw('NULL as ThoiGian'),
                Schema::hasTable('taikhoan') ? 't.Email' : DB::raw('NULL as Email'),
                Schema::hasTable('hosonguoidung') ? 'h.Ten' : DB::raw('NULL as Ten'),
            ])
            ->map(fn ($item) => [
                'id' => (int) $item->ID,
                'type' => $item->LoaiThongBao ?: 'HeThong',
                'content' => $item->NoiDung ?: '',
                'is_read' => (int) $item->DaDoc === 1,
                'time' => $item->ThoiGian,
                'user' => $item->Ten ?: $item->Email ?: 'Không rõ',
            ])
            ->values();
    }

    private function scanWeightAbnormalAlerts(): array
    {
        if (! Schema::hasTable('chisosuckhoe')
            || ! Schema::hasColumn('chisosuckhoe', 'NguoiDungID')
            || ! Schema::hasColumn('chisosuckhoe', 'CanNang')) {
            return [];
        }

        $config = $this->systemConfigValues();
        $thresholdPercent = max(0.1, (float) ($config['nguong_sut_can'] ?? 5));
        $watchDays = max(1, (int) ($config['so_ngay_theo_doi'] ?? 30));
        $dateColumn = $this->firstExistingColumn('chisosuckhoe', ['Ngay', 'created_at', 'NgayTao']);
        $select = ['ID', 'NguoiDungID', 'CanNang'];
        if ($dateColumn) {
            $select[] = $dateColumn;
        }

        $rows = DB::table('chisosuckhoe')
            ->whereNotNull('CanNang')
            ->orderBy('NguoiDungID')
            ->orderBy($dateColumn ?: 'ID')
            ->orderBy('ID')
            ->get($select);

        $alerts = [];
        foreach ($rows->groupBy('NguoiDungID') as $userId => $items) {
            $items = $items->values();
            for ($i = 1; $i < $items->count(); $i++) {
                $previous = $items[$i - 1];
                $current = $items[$i];
                $delta = round((float) $current->CanNang - (float) $previous->CanNang, 1);
                if ($delta >= 0 || (float) $previous->CanNang <= 0) {
                    continue;
                }

                $days = 1;
                if ($dateColumn) {
                    $days = max(1, Carbon::parse($previous->{$dateColumn})->diffInDays(Carbon::parse($current->{$dateColumn})));
                }
                if ($days > $watchDays) {
                    continue;
                }

                $percent = round(abs($delta) / (float) $previous->CanNang * 100, 2);
                if ($percent < $thresholdPercent) {
                    continue;
                }

                $alerts[] = $this->recordHeThongCanhBao(
                    (int) $userId,
                    'sut_can_khan_cap',
                    'Canh bao sut can dot ngot '.abs($delta).'kg ('.$percent.'%) trong '.$days.' ngay, vuot nguong Admin cau hinh '.$thresholdPercent.'%/'.$watchDays.' ngay. Can kiem tra nguy co mat nuoc, suy kiet, roi loan chuyen hoa hoac nguoi dung nhap sai du lieu.',
                    'High',
                    'weight_loss:user:'.$userId.':from:'.$previous->ID.':to:'.$current->ID,
                    [
                        'chi_so_bat_thuong' => [
                            'previous_weight_kg' => (float) $previous->CanNang,
                            'current_weight_kg' => (float) $current->CanNang,
                            'delta_kg' => $delta,
                            'delta_percent' => $percent,
                            'days' => $days,
                            'configured_threshold_percent' => $thresholdPercent,
                            'configured_watch_days' => $watchDays,
                        ],
                    ]
                );
            }
        }

        return $alerts;
    }

    private function scanOvereatingAlerts(): array
    {
        if (! Schema::hasTable('buaan') || ! Schema::hasColumn('buaan', 'NguoiDungID')) {
            return [];
        }

        $dateColumn = $this->firstExistingColumn('buaan', ['Ngay', 'created_at', 'CreatedAt']);
        if (! $dateColumn) {
            return [];
        }

        $alerts = [];
        $hasDetails = Schema::hasTable('chitietbuaan') && Schema::hasColumn('chitietbuaan', 'BuaAnID');
        $gramsExpr = $hasDetails && Schema::hasColumn('chitietbuaan', 'SoLuong')
            ? 'COALESCE(SUM(c.SoLuong), 0)'
            : '0';
        if ($hasDetails && Schema::hasColumn('chitietbuaan', 'TongCalo')) {
            $kcalExpr = Schema::hasColumn('buaan', 'TongCalories')
                ? 'COALESCE(SUM(c.TongCalo), MAX(b.TongCalories), 0)'
                : 'COALESCE(SUM(c.TongCalo), 0)';
        } else {
            $kcalExpr = Schema::hasColumn('buaan', 'TongCalories') ? 'COALESCE(MAX(b.TongCalories), 0)' : '0';
        }

        $mealQuery = DB::table('buaan as b')
            ->select('b.ID', 'b.NguoiDungID')
            ->selectRaw("b.{$dateColumn} as ngay_canh_bao")
            ->selectRaw(Schema::hasColumn('buaan', 'LoaiBuaAn') ? 'b.LoaiBuaAn as loai_bua_an' : "NULL as loai_bua_an")
            ->selectRaw("{$gramsExpr} as total_grams")
            ->selectRaw("{$kcalExpr} as total_kcal");

        if ($hasDetails) {
            $mealQuery->leftJoin('chitietbuaan as c', 'c.BuaAnID', '=', 'b.ID');
        }

        $meals = $mealQuery
            ->groupBy('b.ID', 'b.NguoiDungID', "b.{$dateColumn}")
            ->when(Schema::hasColumn('buaan', 'LoaiBuaAn'), fn ($query) => $query->groupBy('b.LoaiBuaAn'))
            ->havingRaw("{$gramsExpr} > 5000 OR {$kcalExpr} > 8000")
            ->get();

        foreach ($meals as $meal) {
            $alerts[] = $this->recordHeThongCanhBao(
                (int) $meal->NguoiDungID,
                'nap_thuc_pham_qua_tai',
                'Bua an '.($meal->loai_bua_an ?: '#'.$meal->ID).' ghi nhan '.round((float) $meal->total_grams, 1).'g va '.round((float) $meal->total_kcal, 1).' kcal. Vuot nguong an toan 5,000g hoac 8,000 kcal.',
                ((float) $meal->total_kcal > 8000 || (float) $meal->total_grams > 5000) ? 'High' : 'Medium',
                'overeating:meal:'.$meal->ID,
                [
                    'chi_so_bat_thuong' => [
                        'meal_id' => (int) $meal->ID,
                        'date' => $meal->ngay_canh_bao,
                        'total_grams' => (float) $meal->total_grams,
                        'total_kcal' => (float) $meal->total_kcal,
                    ],
                ]
            );
        }

        $dayKcalExpr = $hasDetails && Schema::hasColumn('chitietbuaan', 'TongCalo')
            ? 'COALESCE(SUM(c.TongCalo), 0)'
            : (Schema::hasColumn('buaan', 'TongCalories') ? 'COALESCE(SUM(b.TongCalories), 0)' : '0');

        $dayQuery = DB::table('buaan as b')
            ->select('b.NguoiDungID')
            ->selectRaw("DATE(b.{$dateColumn}) as ngay_canh_bao")
            ->selectRaw("{$gramsExpr} as total_grams")
            ->selectRaw("{$dayKcalExpr} as total_kcal");

        if ($hasDetails) {
            $dayQuery->leftJoin('chitietbuaan as c', 'c.BuaAnID', '=', 'b.ID');
        }

        $days = $dayQuery
            ->groupBy('b.NguoiDungID', DB::raw("DATE(b.{$dateColumn})"))
            ->havingRaw("{$gramsExpr} > 5000 OR {$dayKcalExpr} > 8000")
            ->get();

        foreach ($days as $day) {
            $alerts[] = $this->recordHeThongCanhBao(
                (int) $day->NguoiDungID,
                'nap_thuc_pham_qua_tai',
                'Trong ngay '.$day->ngay_canh_bao.' nguoi dung ghi nhan tong '.round((float) $day->total_grams, 1).'g thuc pham va '.round((float) $day->total_kcal, 1).' kcal.',
                ((float) $day->total_kcal > 8000 || (float) $day->total_grams > 5000) ? 'High' : 'Medium',
                'overeating:day:'.$day->NguoiDungID.':'.$day->ngay_canh_bao,
                [
                    'chi_so_bat_thuong' => [
                        'date' => $day->ngay_canh_bao,
                        'total_grams' => (float) $day->total_grams,
                        'total_kcal' => (float) $day->total_kcal,
                    ],
                ]
            );
        }

        return $alerts;
    }

    private function scanStarvationAlerts(): array
    {
        if (! Schema::hasTable('taikhoan') || ! Schema::hasTable('buaan')) {
            return [];
        }

        $dateColumn = $this->firstExistingColumn('buaan', ['Ngay', 'created_at', 'CreatedAt']);
        if (! $dateColumn || ! Schema::hasColumn('buaan', 'NguoiDungID')) {
            return [];
        }

        $today = now('Asia/Ho_Chi_Minh')->startOfDay();
        $usersQuery = DB::table('taikhoan')->select('ID');
        if (Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
            $usersQuery->where('TrangThaiHoatDong', 1);
        }
        if (Schema::hasColumn('taikhoan', 'VaiTroID')) {
            $usersQuery->whereNotIn('VaiTroID', ['1', 'admin', 'Admin', 'ADMIN']);
        }

        $lastMeals = DB::table('buaan')
            ->select('NguoiDungID')
            ->selectRaw("MAX({$dateColumn}) as last_meal_at")
            ->groupBy('NguoiDungID')
            ->pluck('last_meal_at', 'NguoiDungID');

        $alerts = [];
        foreach ($usersQuery->pluck('ID') as $userId) {
            $lastMeal = $lastMeals[(int) $userId] ?? null;
            $missingDays = $lastMeal
                ? Carbon::parse($lastMeal, 'Asia/Ho_Chi_Minh')->startOfDay()->diffInDays($today)
                : 5;

            if ($missingDays < 3) {
                continue;
            }

            $alerts[] = $this->recordHeThongCanhBao(
                (int) $userId,
                'bo_an_dai_ngay',
                'Nguoi dung khong co bat ky ban ghi an uong nao trong '.$missingDays.' ngay lien tiep. Can kiem tra nguy co bo an, suy kiet, loi dong bo du lieu hoac nguoi dung ngung su dung app.',
                $missingDays >= 5 ? 'High' : 'Medium',
                'starvation:user:'.$userId.':last_meal:'.($lastMeal ? Carbon::parse($lastMeal)->toDateString() : 'never'),
                [
                    'chi_so_bat_thuong' => [
                        'missing_days' => $missingDays,
                        'last_meal_at' => $lastMeal,
                    ],
                ]
            );
        }

        return $alerts;
    }

    private function scanLowWaterAlerts(): array
    {
        if (! Schema::hasTable('theodoinuoc')
            || ! Schema::hasColumn('theodoinuoc', 'NguoiDungID')
            || ! Schema::hasColumn('theodoinuoc', 'LuongNuoc')) {
            return [];
        }

        $config = $this->systemConfigValues();
        $minimumMl = max(0, (int) ($config['nuoc_toi_thieu'] ?? 2000));
        if ($minimumMl <= 0) {
            return [];
        }

        $dateColumn = $this->firstExistingColumn('theodoinuoc', ['Ngay', 'created_at', 'CreatedAt']);
        if (! $dateColumn) {
            return [];
        }

        $date = now('Asia/Ho_Chi_Minh')->subDay()->toDateString();
        $rows = DB::table('theodoinuoc')
            ->select('NguoiDungID')
            ->selectRaw('SUM(LuongNuoc) as total_ml')
            ->whereDate($dateColumn, $date)
            ->groupBy('NguoiDungID')
            ->havingRaw('SUM(LuongNuoc) > 0 AND SUM(LuongNuoc) < ?', [$minimumMl])
            ->get();

        $alerts = [];
        foreach ($rows as $row) {
            $total = (int) round((float) $row->total_ml);
            $alerts[] = $this->recordHeThongCanhBao(
                (int) $row->NguoiDungID,
                'uong_nuoc_thap',
                'Ngay '.$date.' nguoi dung chi ghi nhan '.$total.' ml nuoc, thap hon nguong toi thieu Admin cau hinh '.$minimumMl.' ml/ngay. Can nhac nho bo sung nuoc hoac kiem tra loi ghi nhan.',
                'Medium',
                'low_water:user:'.$row->NguoiDungID.':date:'.$date,
                [
                    'chi_so_bat_thuong' => [
                        'date' => $date,
                        'total_ml' => $total,
                        'configured_minimum_ml' => $minimumMl,
                    ],
                ]
            );
        }

        return $alerts;
    }

    private function scanDrugAbuseAlerts(): array
    {
        if (! Schema::hasTable('lichdungthuoc')
            || ! Schema::hasColumn('lichdungthuoc', 'NguoiDungID')
            || ! Schema::hasColumn('lichdungthuoc', 'ThoiGian')) {
            return [];
        }

        $timeExpression = Schema::hasColumn('lichdungthuoc', 'ThoiGianUongThucTe')
            ? 'COALESCE(l.ThoiGianUongThucTe, l.ThoiGian)'
            : 'l.ThoiGian';
        $windowStart = now('Asia/Ho_Chi_Minh')->subHours(4);

        $query = DB::table('lichdungthuoc as l')
            ->whereRaw("{$timeExpression} IS NOT NULL")
            ->whereRaw("{$timeExpression} >= ?", [$windowStart->toDateTimeString()]);

        if (Schema::hasColumn('lichdungthuoc', 'TrangThai')) {
            $query->whereIn('l.TrangThai', ['da_uong', 'DaUong', 'Da uong', 'DaUongThuoc', 'completed']);
        }
        if (Schema::hasTable('thuoc')) {
            $query->leftJoin('thuoc as t', 't.ID', '=', 'l.ThuocID');
        }

        $rows = $query->get([
            'l.ID',
            'l.NguoiDungID',
            Schema::hasColumn('lichdungthuoc', 'ThuocID') ? 'l.ThuocID' : DB::raw('NULL as ThuocID'),
            Schema::hasColumn('lichdungthuoc', 'TanSuat') ? 'l.TanSuat' : DB::raw('NULL as TanSuat'),
            Schema::hasColumn('lichdungthuoc', 'LieuLuong') ? 'l.LieuLuong' : DB::raw('NULL as LieuLuong'),
            DB::raw("{$timeExpression} as ThoiGianDungThuoc"),
            Schema::hasTable('thuoc') && Schema::hasColumn('thuoc', 'TenThuoc') ? 't.TenThuoc' : DB::raw('NULL as TenThuoc'),
            Schema::hasTable('thuoc') && Schema::hasColumn('thuoc', 'ten_thuoc') ? 't.ten_thuoc' : DB::raw('NULL as ten_thuoc'),
            Schema::hasTable('thuoc') && Schema::hasColumn('thuoc', 'HoatChat') ? 't.HoatChat' : DB::raw('NULL as HoatChat'),
            Schema::hasTable('thuoc') && Schema::hasColumn('thuoc', 'hoat_chat') ? 't.hoat_chat' : DB::raw('NULL as hoat_chat'),
            Schema::hasTable('thuoc') && Schema::hasColumn('thuoc', 'SoLanMoiNgay') ? 't.SoLanMoiNgay' : DB::raw('NULL as SoLanMoiNgay'),
        ]);

        $alerts = [];
        foreach ($rows->groupBy(function ($row) {
            $active = trim((string) ($row->HoatChat ?? $row->hoat_chat ?? ''));
            $drugKey = $active !== '' ? Str::lower($active) : 'thuoc_'.$row->ThuocID;

            return $row->NguoiDungID.'|'.$drugKey;
        }) as $groupKey => $items) {
            $first = $items->first();
            $configuredLimit = (int) ($first->SoLanMoiNgay ?? $first->TanSuat ?? 4);
            $configuredLimit = $configuredLimit > 0 ? $configuredLimit : 4;
            $takenCount = $items->count();
            if ($takenCount <= $configuredLimit) {
                continue;
            }

            [$userId, $drugKey] = explode('|', $groupKey, 2);
            $medicineName = $first->TenThuoc ?: ($first->ten_thuoc ?: ('Thuoc #'.$first->ThuocID));
            $alerts[] = $this->recordHeThongCanhBao(
                (int) $userId,
                'lam_dung_thuoc',
                'Trong vong 4 gio gan day, nguoi dung ghi nhan '.$takenCount.' lan dung '.$medicineName.', vuot nguong toi da '.$configuredLimit.' lan do Admin cau hinh. Can xu ly nhu canh bao do ve nguy co qua lieu/nhap sai.',
                'High',
                'drug_abuse:user:'.$userId.':drug:'.$drugKey.':window:'.$windowStart->format('YmdH'),
                [
                    'chi_so_bat_thuong' => [
                        'medicine_name' => $medicineName,
                        'medicine_id' => $first->ThuocID,
                        'active_ingredient' => $first->HoatChat ?? $first->hoat_chat ?? null,
                        'taken_count_4h' => $takenCount,
                        'configured_limit' => $configuredLimit,
                        'window_start' => $windowStart->toDateTimeString(),
                    ],
                ]
            );
        }

        return $alerts;
    }

    private function recordHeThongCanhBao(
        int $userId,
        string $type,
        string $detail,
        string $severity,
        string $alertKey,
        array $metadata = []
    ): array {
        if (! Schema::hasTable('he_thong_canh_baos')) {
            return ['skipped' => true, 'reason' => 'missing_table', 'alert_key' => $alertKey];
        }

        $now = now('Asia/Ho_Chi_Minh');
        $existing = DB::table('he_thong_canh_baos')->where('alert_key', $alertKey)->first();

        if ($existing && ($existing->status ?? 'pending') === 'reviewed') {
            return [
                'skipped' => true,
                'id' => (int) $existing->id,
                'alert_key' => $alertKey,
                'loai_canh_bao' => $type,
                'reason' => 'already_reviewed',
            ];
        }

        $payload = [
            'user_id' => $userId,
            'loai_canh_bao' => $type,
            'noi_dung_chi_tiet' => $detail,
            'muc_do_nguy_hiem' => $severity,
            'status' => 'pending',
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'detected_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('he_thong_canh_baos')->where('id', $existing->id)->update($payload);

            return [
                'updated' => true,
                'id' => (int) $existing->id,
                'alert_key' => $alertKey,
                'loai_canh_bao' => $type,
            ];
        }

        $id = DB::table('he_thong_canh_baos')->insertGetId([
            'alert_key' => $alertKey,
            'created_at' => $now,
            ...$payload,
        ]);

        return [
            'created' => true,
            'id' => (int) $id,
            'alert_key' => $alertKey,
            'loai_canh_bao' => $type,
        ];
    }

    private function heThongCanhBaoAlerts(): array
    {
        if (! Schema::hasTable('he_thong_canh_baos')) {
            return [];
        }

        $query = DB::table('he_thong_canh_baos as c')
            ->where('c.status', 'pending')
            ->orderByRaw("CASE WHEN c.muc_do_nguy_hiem = 'High' THEN 0 ELSE 1 END")
            ->orderByDesc('c.detected_at')
            ->limit(30);

        if (Schema::hasTable('taikhoan')) {
            $query->leftJoin('taikhoan as t', 't.ID', '=', 'c.user_id');
        }
        if (Schema::hasTable('hosonguoidung')) {
            $query->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 'c.user_id');
        }

        return $query->get([
            'c.*',
            Schema::hasTable('taikhoan') ? 't.Email' : DB::raw('NULL as Email'),
            Schema::hasTable('hosonguoidung') ? 'h.Ten' : DB::raw('NULL as Ten'),
        ])->map(fn ($row) => [
            'id' => 'he-thong-canh-bao-'.$row->id,
            'review_key' => 'he_thong_canh_bao:'.$row->id,
            'source' => 'he_thong_canh_bao',
            'type' => $row->loai_canh_bao,
            'severity' => $row->muc_do_nguy_hiem === 'High' ? 'high' : 'medium',
            'user_id' => (int) $row->user_id,
            'user' => $row->Ten ?: $row->Email ?: ('Nguoi dung #'.$row->user_id),
            'title' => $this->alertTypeLabel((string) $row->loai_canh_bao),
            'message' => $row->noi_dung_chi_tiet,
            'action' => 'Admin mo ho so nguoi dung, doi chieu du lieu va bam xac nhan da xu ly.',
            'time' => $row->detected_at,
            'is_read' => false,
            'status' => 'open',
            'review_action' => [
                'method' => 'PUT',
                'url' => '/api/admin/he-thong-canh-bao/'.$row->id.'/status',
                'body' => ['status' => 'reviewed'],
            ],
        ])->values()->all();
    }

    private function formatHeThongCanhBao($row): array
    {
        $metadata = json_decode((string) ($row->metadata ?? ''), true) ?: [];

        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'ten_nguoi_dung' => $row->Ten ?: ($row->Email ?: 'Nguoi dung #'.$row->user_id),
            'email' => $row->Email ?? null,
            'loai_canh_bao' => $row->loai_canh_bao,
            'ten_loai_canh_bao' => $this->alertTypeLabel((string) $row->loai_canh_bao),
            'chi_so_bat_thuong' => $metadata['chi_so_bat_thuong'] ?? $metadata,
            'noi_dung_chi_tiet' => $row->noi_dung_chi_tiet,
            'muc_do_nguy_hiem' => $row->muc_do_nguy_hiem,
            'status' => $row->status,
            'detected_at' => $row->detected_at,
            'review_action' => [
                'method' => 'PUT',
                'url' => '/api/admin/he-thong-canh-bao/'.$row->id.'/status',
                'body' => ['status' => 'reviewed'],
            ],
        ];
    }

    private function formatHeThongCanhBaoModel(HeThongCanhBao $alert): array
    {
        $metadata = is_array($alert->metadata)
            ? $alert->metadata
            : (json_decode((string) $alert->metadata, true) ?: []);
        $user = $alert->user;
        $profile = $user?->profile;
        $displayName = $profile?->Ten ?: ($user?->Email ?: 'Nguoi dung #'.$alert->user_id);
        $reviewAction = [
            'label' => 'Xac nhan da xu ly',
            'method' => 'PUT',
            'url' => '/api/admin/he-thong-canh-bao/'.$alert->id.'/status',
            'body' => ['status' => 'reviewed'],
        ];

        return [
            'id' => (int) $alert->id,
            'user_id' => (int) $alert->user_id,
            'ten_nguoi_dung' => $displayName,
            'email' => $user?->Email,
            'anh_dai_dien' => $profile?->AnhDaiDien,
            'loai_canh_bao' => $alert->loai_canh_bao,
            'ten_loai_canh_bao' => $this->alertTypeLabel((string) $alert->loai_canh_bao),
            'chi_so_bat_thuong' => $metadata['chi_so_bat_thuong'] ?? $metadata,
            'noi_dung_chi_tiet' => $alert->noi_dung_chi_tiet,
            'muc_do_nguy_hiem' => $alert->muc_do_nguy_hiem,
            'status' => $alert->status,
            'can_hien_tag_do' => $alert->status === 'pending',
            'detected_at' => optional($alert->detected_at)->toDateTimeString(),
            'review_action' => $reviewAction,
            'nut_xac_nhan_da_xu_ly' => $reviewAction,
        ];
    }

    private function alertTypeLabel(string $type): string
    {
        return match ($type) {
            'bien_dong_can_nang' => 'Bien dong can nang bat thuong',
            'nap_thuc_pham_qua_tai' => 'Nap thuc pham qua tai',
            'bo_an_dai_ngay' => 'Bo an dai ngay',
            'lam_dung_thuoc' => 'Lam dung/qua lieu thuoc',
            default => $type,
        };
    }

    private function healthRiskAlerts(): array
    {
        $alerts = [];
        $today = now('Asia/Ho_Chi_Minh')->toDateString();

        foreach ($this->heThongCanhBaoAlerts() as $alert) {
            $alerts[] = $alert;
        }
        foreach ($this->riskEventAlerts() as $alert) {
            $alerts[] = $alert;
        }
        foreach ($this->weightRiskAlerts() as $alert) {
            $alerts[] = $alert;
        }
        foreach ($this->medicineRiskAlerts($today) as $alert) {
            $alerts[] = $alert;
        }
        foreach ($this->waterRiskAlerts($today) as $alert) {
            $alerts[] = $alert;
        }
        foreach ($this->calorieRiskAlerts($today) as $alert) {
            $alerts[] = $alert;
        }

        $alerts = array_values(array_filter($alerts, fn ($alert) => ! $this->isAlertReviewed((string) ($alert['review_key'] ?? ''))));
        $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($alerts, fn ($a, $b) => ($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0));

        return array_slice($alerts, 0, 12);
    }

    private function riskEventAlerts(): array
    {
        if (! Schema::hasTable('risk_events')) {
            return [];
        }

        $query = DB::table('risk_events as r')
            ->where('r.VisibleToAdmin', 1)
            ->where('r.Status', 'open')
            ->orderByDesc('r.LastDetectedAt')
            ->limit(20);
        if (Schema::hasTable('taikhoan')) {
            $query->leftJoin('taikhoan as t', 't.ID', '=', 'r.NguoiDungID');
        }
        if (Schema::hasTable('hosonguoidung')) {
            $query->leftJoin('hosonguoidung as h', 'h.NguoiDungID', '=', 'r.NguoiDungID');
        }

        return $query->get([
            'r.ID',
            'r.NguoiDungID',
            'r.Category',
            'r.Severity',
            'r.RiskScore',
            'r.Title',
            'r.Message',
            'r.Action',
            'r.LastDetectedAt',
            Schema::hasTable('taikhoan') ? 't.Email' : DB::raw('NULL as Email'),
            Schema::hasTable('hosonguoidung') ? 'h.Ten' : DB::raw('NULL as Ten'),
        ])->map(fn ($row) => [
            'id' => 'risk-event-'.$row->ID,
            'event_id' => (int) $row->ID,
            'review_key' => 'risk_event:'.$row->ID,
            'source' => 'risk_event',
            'type' => $row->Category,
            'severity' => $row->Severity,
            'score' => (int) $row->RiskScore,
            'user_id' => (int) $row->NguoiDungID,
            'user' => $row->Ten ?: $row->Email ?: ('Nguoi dung #'.$row->NguoiDungID),
            'title' => $row->Title,
            'message' => $row->Message,
            'action' => $row->Action ?: 'Admin xem xet va gui thong bao ca nhan neu can.',
            'time' => $row->LastDetectedAt,
            'is_read' => false,
            'status' => 'open',
        ])->values()->all();
    }

    private function weightRiskAlerts(): array
    {
        if (! Schema::hasTable('chisosuckhoe') || ! Schema::hasColumn('chisosuckhoe', 'CanNang')) {
            return [];
        }

        return DB::table('chisosuckhoe')
            ->whereNotNull('CanNang')
            ->orderBy('NguoiDungID')
            ->orderByDesc(Schema::hasColumn('chisosuckhoe', 'Ngay') ? 'Ngay' : 'ID')
            ->orderByDesc('ID')
            ->get(['ID', 'NguoiDungID', 'CanNang', Schema::hasColumn('chisosuckhoe', 'Ngay') ? 'Ngay' : DB::raw('NULL as Ngay')])
            ->groupBy('NguoiDungID')
            ->flatMap(function ($rows, $userId) {
                $latest = $rows->first();
                $previous = $rows->skip(1)->first();
                if (! $latest || ! $previous) {
                    return [];
                }
                $delta = round((float) $latest->CanNang - (float) $previous->CanNang, 1);
                if (abs($delta) < 3) {
                    return [];
                }

                return [[
                    'id' => 'weight-fast-change-'.$userId.'-'.$latest->ID.'-'.$previous->ID,
                    'review_key' => 'weight_fast_change:user:'.$userId.':latest:'.$latest->ID.':prev:'.$previous->ID,
                    'source' => 'generated_alert',
                    'type' => 'CanNang',
                    'severity' => abs($delta) >= 5 ? 'high' : 'medium',
                    'user_id' => (int) $userId,
                    'user' => $this->userDisplayName((int) $userId),
                    'title' => 'Bien dong can nang nhanh',
                    'message' => 'Can nang thay doi '.($delta > 0 ? '+' : '').$delta.' kg giua 2 lan ghi nhan gan nhat.',
                    'action' => 'Kiem tra lai chi so va gui thong bao tu van cho nguoi dung.',
                    'time' => $latest->Ngay ?? null,
                    'is_read' => false,
                    'status' => 'open',
                ]];
            })
            ->values()
            ->all();
    }

    private function medicineRiskAlerts(string $today): array
    {
        if (! Schema::hasTable('lichdungthuoc')) {
            return [];
        }

        $query = DB::table('lichdungthuoc as l')
            ->whereDate('l.ThoiGian', $today)
            ->whereIn('l.TrangThai', ['DaUong', 'da_uong', 'Da uong']);

        $hasMedicineTable = Schema::hasTable('thuoc');
        if ($hasMedicineTable) {
            $query->leftJoin('thuoc as t', 't.ID', '=', 'l.ThuocID');
        }

        $query->groupBy('l.NguoiDungID', 'l.ThuocID');
        if ($hasMedicineTable) {
            $query->groupBy('t.TenThuoc', 't.SoLanMoiNgay');
        }

        return $query
            ->get([
                'l.NguoiDungID',
                'l.ThuocID',
                DB::raw('COUNT(*) as total'),
                $hasMedicineTable ? 't.TenThuoc' : DB::raw('NULL as TenThuoc'),
                $hasMedicineTable ? 't.SoLanMoiNgay' : DB::raw('NULL as SoLanMoiNgay'),
            ])
            ->filter(function ($row) {
                $limit = (int) ($row->SoLanMoiNgay ?? 0);

                return (int) $row->total > max($limit, 4);
            })
            ->map(fn ($row) => [
                'id' => 'medicine-over-'.$row->NguoiDungID.'-'.$row->ThuocID.'-'.$today,
                'review_key' => 'medicine_over_schedule:user:'.$row->NguoiDungID.':thuoc:'.$row->ThuocID.':date:'.$today,
                'source' => 'generated_alert',
                'type' => 'Thuoc',
                'severity' => 'high',
                'user_id' => (int) $row->NguoiDungID,
                'user' => $this->userDisplayName((int) $row->NguoiDungID),
                'title' => 'Uong thuoc vuot khuyen cao',
                'message' => ($row->TenThuoc ?: 'Thuoc #'.$row->ThuocID).' da duoc ghi nhan '.(int) $row->total.' lan trong ngay.',
                'action' => 'Lien he nguoi dung va doi chieu lieu dung truoc khi tiep tuc.',
                'time' => $today,
                'is_read' => false,
                'status' => 'open',
            ])
            ->values()
            ->all();
    }

    private function waterRiskAlerts(string $today): array
    {
        if (! Schema::hasTable('theodoinuoc')) {
            return [];
        }

        return DB::table('theodoinuoc')
            ->whereDate('Ngay', $today)
            ->select('NguoiDungID')
            ->selectRaw('SUM(LuongNuoc) as total_ml')
            ->groupBy('NguoiDungID')
            ->havingRaw('SUM(LuongNuoc) > 5000')
            ->get()
            ->map(fn ($row) => [
                'id' => 'water-high-'.$row->NguoiDungID.'-'.$today,
                'review_key' => 'water_high_day:user:'.$row->NguoiDungID.':date:'.$today,
                'source' => 'generated_alert',
                'type' => 'Nuoc',
                'severity' => 'medium',
                'user_id' => (int) $row->NguoiDungID,
                'user' => $this->userDisplayName((int) $row->NguoiDungID),
                'title' => 'Luong nuoc trong ngay cao bat thuong',
                'message' => 'Tong nuoc hom nay: '.(int) $row->total_ml.' ml.',
                'action' => 'Nhac nguoi dung kiem tra lai du lieu va uong theo nhu cau co the.',
                'time' => $today,
                'is_read' => false,
                'status' => 'open',
            ])
            ->values()
            ->all();
    }

    private function calorieRiskAlerts(string $today): array
    {
        if (! Schema::hasTable('buaan')) {
            return [];
        }

        return DB::table('buaan')
            ->whereDate('Ngay', $today)
            ->select('NguoiDungID')
            ->selectRaw('SUM(TongCalories) as total_kcal')
            ->groupBy('NguoiDungID')
            ->havingRaw('SUM(TongCalories) > 4000 OR SUM(TongCalories) < 600')
            ->get()
            ->map(fn ($row) => [
                'id' => 'calorie-abnormal-'.$row->NguoiDungID.'-'.$today,
                'review_key' => 'calorie_abnormal:user:'.$row->NguoiDungID.':date:'.$today,
                'source' => 'generated_alert',
                'type' => 'DinhDuong',
                'severity' => ((float) $row->total_kcal > 4000) ? 'medium' : 'low',
                'user_id' => (int) $row->NguoiDungID,
                'user' => $this->userDisplayName((int) $row->NguoiDungID),
                'title' => 'Calo trong ngay bat thuong',
                'message' => 'Tong calo hom nay: '.(int) $row->total_kcal.' kcal.',
                'action' => 'Gui goi y dieu chinh bua an hoac kiem tra lai ghi nhan.',
                'time' => $today,
                'is_read' => false,
                'status' => 'open',
            ])
            ->values()
            ->all();
    }

    private function userDisplayName(int $userId): string
    {
        if (Schema::hasTable('hosonguoidung')) {
            $name = DB::table('hosonguoidung')->where('NguoiDungID', $userId)->value('Ten');
            if ($name) {
                return $name.' (#'.$userId.')';
            }
        }

        $email = Schema::hasTable('taikhoan')
            ? DB::table('taikhoan')->where('ID', $userId)->value('Email')
            : null;

        return ($email ?: 'Nguoi dung').' (#'.$userId.')';
    }

    private function markNotificationRowRead(int $id): int
    {
        if (! Schema::hasTable('thongbao') || ! Schema::hasColumn('thongbao', 'DaDoc')) {
            return 0;
        }

        return DB::table('thongbao')
            ->where('ID', $id)
            ->where('DaDoc', 0)
            ->update(['DaDoc' => 1]);
    }

    private function notificationUnreadCount(): int
    {
        if (! Schema::hasTable('thongbao') || ! Schema::hasColumn('thongbao', 'DaDoc')) {
            return 0;
        }

        return (int) DB::table('thongbao')->where('DaDoc', 0)->count();
    }

    private function markRiskEventReviewed(int $id): int
    {
        if (! Schema::hasTable('risk_events')) {
            return 0;
        }

        $payload = ['Status' => 'reviewed'];
        if (Schema::hasColumn('risk_events', 'ResolvedAt')) {
            $payload['ResolvedAt'] = now('Asia/Ho_Chi_Minh');
        }
        if (Schema::hasColumn('risk_events', 'updated_at')) {
            $payload['updated_at'] = now('Asia/Ho_Chi_Minh');
        }

        return DB::table('risk_events')
            ->where('ID', $id)
            ->where('Status', '<>', 'reviewed')
            ->update($payload);
    }

    private function markHeThongCanhBaoReviewed(int $id): int
    {
        if (! Schema::hasTable('he_thong_canh_baos')) {
            return 0;
        }

        $changed = DB::table('he_thong_canh_baos')
            ->where('id', $id)
            ->where('status', '<>', 'reviewed')
            ->update([
                'status' => 'reviewed',
                'updated_at' => now('Asia/Ho_Chi_Minh'),
            ]);

        if ($changed > 0) {
            $this->storeAlertReview('he_thong_canh_bao:'.$id, [
                'AlertType' => 'he_thong_canh_bao',
                'Title' => 'Admin da xu ly canh bao he thong',
            ]);
        }

        return $changed;
    }

    private function markUserAsReviewed(int $userId): array
    {
        $changed = 0;
        $changed += $this->storeAlertReview('user_profile:'.$userId, [
            'AlertType' => 'user_profile',
            'NguoiDungID' => $userId,
            'Title' => 'Admin da xem ho so nguoi dung',
        ]);

        if (Schema::hasTable('thongbao') && Schema::hasColumn('thongbao', 'NguoiDungID') && Schema::hasColumn('thongbao', 'DaDoc')) {
            $changed += DB::table('thongbao')
                ->where('NguoiDungID', $userId)
                ->where('DaDoc', 0)
                ->update(['DaDoc' => 1]);
        }

        if (Schema::hasTable('risk_events') && Schema::hasColumn('risk_events', 'NguoiDungID')) {
            $payload = ['Status' => 'reviewed'];
            if (Schema::hasColumn('risk_events', 'ResolvedAt')) {
                $payload['ResolvedAt'] = now('Asia/Ho_Chi_Minh');
            }
            if (Schema::hasColumn('risk_events', 'updated_at')) {
                $payload['updated_at'] = now('Asia/Ho_Chi_Minh');
            }
            $changed += DB::table('risk_events')
                ->where('NguoiDungID', $userId)
                ->where('Status', 'open')
                ->update($payload);
        }

        foreach ($this->generatedAlertCandidatesForUser($userId) as $alert) {
            $changed += $this->storeAlertReview((string) $alert['review_key'], [
                'AlertType' => (string) ($alert['type'] ?? 'generated_alert'),
                'NguoiDungID' => $userId,
                'Title' => (string) ($alert['title'] ?? ''),
            ]);
        }

        Cache::forget('admin:stats:v2');

        return ['changed' => $changed];
    }

    private function generatedAlertCandidatesForUser(int $userId): array
    {
        $today = now('Asia/Ho_Chi_Minh')->toDateString();

        return collect([
            ...$this->weightRiskAlerts(),
            ...$this->medicineRiskAlerts($today),
            ...$this->waterRiskAlerts($today),
            ...$this->calorieRiskAlerts($today),
        ])
            ->where('user_id', $userId)
            ->filter(fn ($alert) => ! empty($alert['review_key']))
            ->values()
            ->all();
    }

    private function storeAlertReview(string $alertKey, array $payload = []): int
    {
        if ($alertKey === '' || ! Schema::hasTable('admin_alert_reviews')) {
            return 0;
        }

        $now = now('Asia/Ho_Chi_Minh');
        $exists = DB::table('admin_alert_reviews')->where('AlertKey', $alertKey)->exists();
        $data = [
            'AlertType' => (string) ($payload['AlertType'] ?? 'generated_alert'),
            'NguoiDungID' => $payload['NguoiDungID'] ?? null,
            'Title' => $payload['Title'] ?? null,
            'IsRead' => true,
            'Status' => 'reviewed',
            'ReviewedBy' => Auth::guard('admin')->id(),
            'ReviewedAt' => $now,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('admin_alert_reviews')->where('AlertKey', $alertKey)->update($data);
        } else {
            DB::table('admin_alert_reviews')->insert(['AlertKey' => $alertKey, 'created_at' => $now, ...$data]);
        }

        return $exists ? 0 : 1;
    }

    private function isAlertReviewed(string $alertKey): bool
    {
        if ($alertKey === '' || ! Schema::hasTable('admin_alert_reviews')) {
            return false;
        }

        return DB::table('admin_alert_reviews')
            ->where('AlertKey', $alertKey)
            ->where(function ($query) {
                $query->where('IsRead', 1)
                    ->orWhere('Status', 'reviewed');
            })
            ->exists();
    }

    private function unreviewedAccountCount(array $period = []): int
    {
        if (! Schema::hasTable('thongbao')
            || ! Schema::hasColumn('thongbao', 'NguoiDungID')
            || ! Schema::hasColumn('thongbao', 'DaDoc')) {
            return 0;
        }

        $query = DB::table('thongbao')
            ->where('DaDoc', 0)
            ->whereNotNull('NguoiDungID');
        $this->applyDateWindow($query, 'thongbao', 'ThoiGian', $period);

        return (int) $query->distinct()->count('NguoiDungID');
    }

    private function formatAccount($account, ?array $preloadedStats = null): array
    {
        if (! $account) {
            return [];
        }

        $id = (int) $account->ID;
        $isBlocked = $this->accountIsBlocked($account);
        $isActive = ! $isBlocked;

        return [
            'id' => $id,
            'email' => $account->Email ?? '',
            'name' => $account->Ten ?: 'Chưa cập nhật',
            'gender' => $account->GioiTinh,
            'birthday' => $account->NgaySinh,
            'height' => $account->ChieuCao,
            'weight' => $account->CanNang,
            'avatar' => $account->AnhDaiDien,
            'is_active' => $isActive,
            'is_blocked' => $isBlocked,
            'status' => $isActive ? 'Đang hoạt động' : 'Đã khóa',
            'last_login' => $account->LanDangNhapCuoi,
            'created_at' => $account->NgayTao,
            'stats' => $preloadedStats ?? $this->statsForUser($id),
        ];
    }

    private function accountIsBlocked($account): bool
    {
        if (! $account) {
            return false;
        }

        $blockedByFlag = (bool) ($account->is_blocked ?? false);
        $blockedByLegacyStatus = (int) ($account->TrangThaiHoatDong ?? 1) !== 1;

        return $blockedByFlag || $blockedByLegacyStatus;
    }

    private function preloadAccountStats(array $userIds): array
    {
        $stats = [];
        foreach ($userIds as $id) {
            $stats[(int) $id] = [
                'notifications' => 0,
                'unread_notifications' => 0,
                'meals' => 0,
                'water_logs' => 0,
                'medicines' => 0,
                'activities' => 0,
            ];
        }

        if (empty($stats)) {
            return [];
        }

        foreach ([
            'buaan' => 'meals',
            'theodoinuoc' => 'water_logs',
            'lichdungthuoc' => 'medicines',
            Schema::hasTable('activity_logs') ? 'activity_logs' : 'lichhoatdong' => 'activities',
        ] as $table => $key) {
            foreach ($this->countGroupedByUser($table, $userIds) as $userId => $total) {
                $stats[$userId][$key] = $total;
            }
        }

        foreach ($this->notificationCountsByUser($userIds) as $userId => $row) {
            $stats[$userId]['notifications'] = $row['total'];
            $stats[$userId]['unread_notifications'] = $row['unread'];
        }

        return $stats;
    }

    private function statsForUser(int $id): array
    {
        return [
            'notifications' => $this->countUserRows('thongbao', $id),
            'unread_notifications' => $this->countUserRows('thongbao', $id, function ($query) {
                if (Schema::hasColumn('thongbao', 'DaDoc')) {
                    $query->where('DaDoc', 0);
                }
            }),
            'meals' => $this->countUserRows('buaan', $id),
            'water_logs' => $this->countUserRows('theodoinuoc', $id),
            'medicines' => $this->countUserRows('lichdungthuoc', $id),
            'activities' => $this->countUserRows(Schema::hasTable('activity_logs') ? 'activity_logs' : 'lichhoatdong', $id),
        ];
    }

    private function countGroupedByUser(string $table, array $userIds): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'NguoiDungID')) {
            return [];
        }

        return DB::table($table)
            ->whereIn('NguoiDungID', $userIds)
            ->select('NguoiDungID', DB::raw('COUNT(*) as total'))
            ->groupBy('NguoiDungID')
            ->pluck('total', 'NguoiDungID')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function notificationCountsByUser(array $userIds): array
    {
        if (! Schema::hasTable('thongbao') || ! Schema::hasColumn('thongbao', 'NguoiDungID')) {
            return [];
        }

        return DB::table('thongbao')
            ->whereIn('NguoiDungID', $userIds)
            ->select('NguoiDungID')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(Schema::hasColumn('thongbao', 'DaDoc')
                ? 'SUM(CASE WHEN DaDoc = 0 THEN 1 ELSE 0 END) as unread'
                : '0 as unread')
            ->groupBy('NguoiDungID')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->NguoiDungID => [
                'total' => (int) $row->total,
                'unread' => (int) $row->unread,
            ]])
            ->all();
    }

    private function countTable(string $table, ?callable $callback = null, ?string $dateColumn = null, array $period = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $this->applyDateWindow($query, $table, $dateColumn, $period);
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function countUserRows(string $table, int $userId, ?callable $callback = null, ?string $dateColumn = null, array $period = []): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'NguoiDungID')) {
            return 0;
        }

        $query = DB::table($table)->where('NguoiDungID', $userId);
        $this->applyDateWindow($query, $table, $dateColumn, $period);
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function countByDate(string $table, string $column, string $date): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->whereDate($column, $date)->count();
    }

    private function sumColumn(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->sum($column);
    }
}
