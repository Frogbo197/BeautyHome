<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    public function page()
    {
        return view('admin.dashboard');
    }

    public function stats()
    {
        $payload = Cache::remember('admin:stats:v2', now()->addSeconds(60), function () {
            $today = now('Asia/Ho_Chi_Minh');
            $accountStats = $this->accountAggregateStats();
            $notificationStats = $this->notificationAggregateStats($today);
            $alerts = $this->healthRiskAlerts();

            return [
                'success' => true,
                'generated_at' => $today->toDateTimeString(),
                'overview' => [
                    ['label' => 'Tai khoan', 'value' => $accountStats['total'], 'note' => $accountStats['active'] . ' dang hoat dong', 'tone' => 'mint'],
                    ['label' => 'Tai khoan bi khoa', 'value' => $accountStats['locked'], 'note' => 'Co the mo lai tu bang nguoi dung', 'tone' => 'rose'],
                    ['label' => 'Thong bao', 'value' => $notificationStats['total'], 'note' => $notificationStats['unread'] . ' thong bao chua doc', 'tone' => 'lavender'],
                    ['label' => 'Canh bao suc khoe', 'value' => count($alerts), 'note' => 'Can admin xem lai va gui nhac nho', 'tone' => count($alerts) ? 'rose' : 'mint'],
                    ['label' => 'Ho so suc khoe', 'value' => $this->countTable('hosonguoidung'), 'note' => $this->countTable('diemsuckhoe') . ' luot cham diem', 'tone' => 'sky'],
                ],
                'features' => $this->featureStats(),
                'weekly' => $this->weeklyStats($today),
                'alerts' => $alerts,
                'notifications' => [
                    'total' => $notificationStats['total'],
                    'unread' => $notificationStats['unread'],
                    'read' => $notificationStats['read'],
                    'today' => $notificationStats['today'],
                    'by_type' => $this->notificationTypes(),
                    'recent' => $this->recentNotifications(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function accounts(Request $request)
    {
        if (!Schema::hasTable('taikhoan')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 1],
            ]);
        }

        $perPage = min(max($request->integer('per_page', 20), 5), 50);
        $query = DB::table('taikhoan as t');

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
        if ($status !== null && $status !== '' && Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
            $query->where('t.TrangThaiHoatDong', $status === 'locked' ? 0 : 1);
        }

        $select = [
            't.ID',
            't.Email',
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

    public function toggleAccount(Request $request, int $id)
    {
        if (!Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng tài khoản'], 404);
        }

        if (!Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
            return response()->json(['success' => false, 'message' => 'Thiếu cột trạng thái tài khoản'], 422);
        }

        $account = DB::table('taikhoan')->where('ID', $id)->first();
        if (!$account) {
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
        if (!Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng tài khoản'], 404);
        }

        $account = $this->accountQuery()
            ->where('t.ID', $id)
            ->first($this->accountSelect());

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        return response()->json([
            'success' => true,
            'account' => $this->formatAccount($account),
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
        if (!Schema::hasTable('taikhoan')) {
            return response()->json(['success' => false, 'message' => 'Chua co bang tai khoan'], 404);
        }

        $account = DB::table('taikhoan')->where('ID', $id)->first();
        if (!$account) {
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
        ]);

        DB::transaction(function () use ($data, $request, $id, $account) {
            if (!empty($data['email']) && $data['email'] !== $account->Email) {
                $exists = DB::table('taikhoan')
                    ->where('Email', $data['email'])
                    ->where('ID', '<>', $id)
                    ->exists();

                if ($exists) {
                    abort(response()->json(['success' => false, 'message' => 'Email da ton tai'], 422));
                }

                DB::table('taikhoan')->where('ID', $id)->update(['Email' => $data['email']]);
            }

            if (array_key_exists('active', $data) && Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
                DB::table('taikhoan')->where('ID', $id)->update([
                    'TrangThaiHoatDong' => $request->boolean('active') ? 1 : 0,
                ]);
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

                if (!empty($profile)) {
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
        if (!Schema::hasTable('taikhoan')) {
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

        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Đã đặt lại mật khẩu']);
    }

    public function updateUserMode(Request $request, int $id)
    {
        if (!Schema::hasTable('taikhoan') || !DB::table('taikhoan')->where('ID', $id)->exists()) {
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
        if (!Schema::hasTable('thucpham')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thực phẩm'], 404);
        }

        $data = $this->validatedFood($request);
        $id = DB::table('thucpham')->insertGetId($this->onlyExistingColumns('thucpham', $data));

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm thực phẩm',
            'food' => DB::table('thucpham')->where('ID', $id)->first(),
        ], 201);
    }

    public function updateFood(Request $request, int $id)
    {
        if (!Schema::hasTable('thucpham')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thực phẩm'], 404);
        }

        $data = $this->validatedFood($request);
        $updated = DB::table('thucpham')->where('ID', $id)->update($this->onlyExistingColumns('thucpham', $data));
        if (!$updated && !DB::table('thucpham')->where('ID', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thực phẩm'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật thực phẩm',
            'food' => DB::table('thucpham')->where('ID', $id)->first(),
        ]);
    }

    public function deleteFood(int $id)
    {
        if (!Schema::hasTable('thucpham')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thực phẩm'], 404);
        }

        if (Schema::hasTable('chitietbuaan') && Schema::hasColumn('chitietbuaan', 'ThucPhamID')
            && DB::table('chitietbuaan')->where('ThucPhamID', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Thuc pham dang co lich su bua an, khong xoa cung de bao toan analytics'], 409);
        }

        $payload = [];
        if (Schema::hasColumn('thucpham', 'TrangThai')) { $payload['TrangThai'] = 'DaXoa'; }
        if (Schema::hasColumn('thucpham', 'Keywords')) { $payload['Keywords'] = DB::raw("CONCAT(COALESCE(Keywords, ''), ' DaXoa')"); }
        if (Schema::hasColumn('thucpham', 'IsHealthy')) { $payload['IsHealthy'] = 0; }
        if (empty($payload)) {
            return response()->json(['success' => false, 'message' => 'Bang thuc pham thieu cot trang thai de soft delete'], 422);
        }

        DB::transaction(function () use ($id, $payload) {
            DB::table('thucpham')->where('ID', $id)->update($payload);
            Log::warning('admin.food.soft_delete', ['food_id' => $id]);
            Cache::forget('admin:stats:v2');
        });

        return response()->json(['success' => true, 'message' => 'Da danh dau xoa thuc pham']);
    }

    public function storeMedicine(Request $request)
    {
        if (!Schema::hasTable('thuoc')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thuốc'], 404);
        }

        $data = $this->validatedMedicine($request);
        $id = DB::table('thuoc')->insertGetId($this->onlyExistingColumns('thuoc', $data));

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm thuốc',
            'medicine' => DB::table('thuoc')->where('ID', $id)->first(),
        ], 201);
    }

    public function updateMedicine(Request $request, int $id)
    {
        if (!Schema::hasTable('thuoc')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thuốc'], 404);
        }

        $data = $this->validatedMedicine($request);
        $updated = DB::table('thuoc')->where('ID', $id)->update($this->onlyExistingColumns('thuoc', $data));
        if (!$updated && !DB::table('thuoc')->where('ID', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thuốc'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật thuốc',
            'medicine' => DB::table('thuoc')->where('ID', $id)->first(),
        ]);
    }

    public function deleteMedicine(int $id)
    {
        if (!Schema::hasTable('thuoc')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thuốc'], 404);
        }

        if (!Schema::hasColumn('thuoc', 'TrangThai')) {
            return response()->json(['success' => false, 'message' => 'Bang thuoc thieu cot trang thai de soft delete'], 422);
        }

        DB::transaction(function () use ($id) {
            DB::table('thuoc')->where('ID', $id)->update(['TrangThai' => 'DaXoa']);
            if (Schema::hasTable('lichdungthuoc') && Schema::hasColumn('lichdungthuoc', 'TrangThai')) {
                DB::table('lichdungthuoc')->where('ThuocID', $id)->update(['TrangThai' => 'da_huy']);
            }
            Log::warning('admin.medicine.soft_delete', ['medicine_id' => $id]);
            Cache::forget('admin:stats:v2');
        });

        return response()->json(['success' => true, 'message' => 'Da danh dau xoa thuoc']);
    }

    public function createNotification(Request $request)
    {
        if (!Schema::hasTable('thongbao')) {
            return response()->json(['success' => false, 'message' => 'Chưa có bảng thông báo'], 404);
        }

        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:taikhoan,ID',
            'send_all' => 'nullable|boolean',
            'type' => 'nullable|string|max:100',
            'content' => 'required|string|max:2000',
        ]);

        if (!$request->boolean('send_all') && empty($data['user_id'])) {
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
                        if (!empty($rows)) {
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

        return response()->json(['success' => true, 'message' => 'Da gui ' . $sent . ' thong bao'], 201);
    }

    public function notifications(Request $request)
    {
        if (!Schema::hasTable('thongbao')) {
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
        if (!Schema::hasTable('thongbao')) {
            return response()->json(['success' => false, 'message' => 'Chua co bang thong bao'], 404);
        }

        DB::table('thongbao')->where('ID', $id)->update(['DaDoc' => 1]);
        Cache::forget('admin:stats:v2');

        return response()->json(['success' => true, 'message' => 'Da danh dau da doc']);
    }

    public function deleteNotification(int $id)
    {
        if (!Schema::hasTable('thongbao')) {
            return response()->json(['success' => false, 'message' => 'Chua co bang thong bao'], 404);
        }

        DB::table('thongbao')->where('ID', $id)->delete();
        Cache::forget('admin:stats:v2');

        return response()->json(['success' => true, 'message' => 'Da xoa thong bao']);
    }

    public function resources(Request $request)
    {
        $type = $request->query('type', 'foods');
        $perPage = min(max($request->integer('per_page', $request->integer('limit', 20)), 5), 80);

        $resource = match ($type) {
            'medicines' => $this->resourceRows('thuoc', ['ID', 'TenThuoc', 'MoTa', 'TacDungPhu', 'LieuLuong', 'DonVi', 'SoLanMoiNgay', 'GhiChu', 'CanhBao', 'HoatChat', 'NhomThuoc', 'TrangThai'], 'ID', $perPage, $request),
            'reminders' => $this->resourceRows('nhacnho', ['ID', 'NguoiDungID', 'LoaiDoiTuong', 'DoiTuongId', 'ThoiGian', 'LapLai', 'NgayTrongTuan', 'TrangThai'], 'ID', $perPage, $request),
            'scores' => $this->resourceRows('diemsuckhoe', ['ID', 'NguoiDungID', 'Diem', 'NgayTinh', 'NhanXetAI'], 'ID', $perPage, $request),
            'foods' => $this->resourceRows('thucpham', ['ID', 'Ten', 'Calo', 'Protein', 'Carb', 'ChatBeo', 'DonVi', 'KhoiLuongGram', 'LoaiThucPham', 'Keywords', 'IsHealthy'], 'ID', $perPage, $request),
            default => ['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'per_page' => $perPage, 'last_page' => 1]],
        };

        return response()->json([
            'success' => true,
            'type' => $type,
            ...$resource,
        ]);
    }

    private function accountAggregateStats(): array
    {
        if (!Schema::hasTable('taikhoan')) {
            return ['total' => 0, 'active' => 0, 'locked' => 0];
        }

        $row = DB::table('taikhoan')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')
                ? 'SUM(CASE WHEN TrangThaiHoatDong = 1 THEN 1 ELSE 0 END) as active'
                : 'COUNT(*) as active')
            ->selectRaw(Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')
                ? 'SUM(CASE WHEN TrangThaiHoatDong = 0 THEN 1 ELSE 0 END) as locked'
                : '0 as locked')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'locked' => (int) ($row->locked ?? 0),
        ];
    }

    private function notificationAggregateStats($today): array
    {
        if (!Schema::hasTable('thongbao')) {
            return ['total' => 0, 'unread' => 0, 'read' => 0, 'today' => 0];
        }

        $row = DB::table('thongbao')
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

    private function featureStats(): array
    {
        return [
            [
                'label' => 'Dinh dưỡng',
                'value' => $this->countTable('buaan'),
                'note' => $this->countTable('chitietbuaan') . ' món đã ghi nhận',
            ],
            [
                'label' => 'Kho thực phẩm',
                'value' => $this->countTable('thucpham'),
                'note' => 'Dữ liệu món ăn và macro',
            ],
            [
                'label' => 'Uống nước',
                'value' => $this->countTable('theodoinuoc'),
                'note' => $this->sumColumn('theodoinuoc', 'LuongNuoc') . ' ml đã ghi nhận',
            ],
            [
                'label' => 'Thuốc',
                'value' => $this->countTable('lichdungthuoc'),
                'note' => $this->countTable('thuoc') . ' loại thuốc',
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
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'NguoiDungID')) {
            return null;
        }

        return DB::table($table)->where('NguoiDungID', $userId)->first();
    }

    private function latestUserRow(string $table, int $userId, string $orderColumn)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'NguoiDungID')) {
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
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'NguoiDungID')) {
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
        if (!Schema::hasTable('buaan')) {
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
        if (!Schema::hasTable('lichdungthuoc')) {
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

        if (!Schema::hasTable('lichhoatdong')) {
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
        if (!Schema::hasTable($table)) {
            return ['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'per_page' => $perPage, 'last_page' => 1]];
        }

        $select = collect($columns)
            ->map(fn ($column) => Schema::hasColumn($table, $column) ? $column : DB::raw("NULL as {$column}"))
            ->all();

        $query = DB::table($table)->select($select);
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
            'healthy' => 'IsHealthy',
        ] as $param => $column) {
            if ($request->filled($param) && Schema::hasColumn($table, $column)) {
                $query->where($column, $request->query($param));
            }
        }
    }

    private function userPreferences(int $userId)
    {
        if (!Schema::hasTable('sothichnguoidung')) {
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

    private function validatedFood(Request $request): array
    {
        $data = $request->validate([
            'Ten' => 'required|string|max:255',
            'Calo' => 'nullable|numeric|min:0',
            'Protein' => 'nullable|numeric|min:0',
            'Carb' => 'nullable|numeric|min:0',
            'ChatBeo' => 'nullable|numeric|min:0',
            'DonVi' => 'nullable|string|max:50',
            'KhoiLuongGram' => 'nullable|numeric|min:0',
            'LoaiThucPham' => 'nullable|string|max:100',
            'Keywords' => 'nullable|string',
            'IsHealthy' => 'nullable|boolean',
        ]);

        return [
            'Ten' => $data['Ten'],
            'Calo' => $data['Calo'] ?? 0,
            'Protein' => $data['Protein'] ?? 0,
            'Carb' => $data['Carb'] ?? 0,
            'ChatBeo' => $data['ChatBeo'] ?? 0,
            'DonVi' => $data['DonVi'] ?? 'Gram',
            'KhoiLuongGram' => $data['KhoiLuongGram'] ?? 100,
            'LoaiThucPham' => $data['LoaiThucPham'] ?? null,
            'Keywords' => $data['Keywords'] ?? null,
            'IsHealthy' => $data['IsHealthy'] ?? 1,
        ];
    }

    private function validatedMedicine(Request $request): array
    {
        $data = $request->validate([
            'TenThuoc' => 'required|string|max:255',
            'MoTa' => 'nullable|string',
            'TacDungPhu' => 'nullable|string',
            'LieuLuong' => 'nullable|string|max:100',
            'DonVi' => 'nullable|string|max:50',
            'SoLanMoiNgay' => 'nullable|integer|min:0|max:50',
            'GhiChu' => 'nullable|string',
            'CanhBao' => 'nullable|string',
            'HoatChat' => 'nullable|string|max:255',
            'NhomThuoc' => 'nullable|string|max:100',
            'TrangThai' => 'nullable|string|max:50',
        ]);

        return [
            'TenThuoc' => $data['TenThuoc'],
            'MoTa' => $data['MoTa'] ?? '',
            'TacDungPhu' => $data['TacDungPhu'] ?? '',
            'LieuLuong' => $data['LieuLuong'] ?? null,
            'DonVi' => $data['DonVi'] ?? null,
            'SoLanMoiNgay' => $data['SoLanMoiNgay'] ?? null,
            'GhiChu' => $data['GhiChu'] ?? null,
            'CanhBao' => $data['CanhBao'] ?? null,
            'HoatChat' => $data['HoatChat'] ?? null,
            'NhomThuoc' => $data['NhomThuoc'] ?? null,
            'TrangThai' => $data['TrangThai'] ?? 'chua_den',
        ];
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
        if (!Schema::hasTable('thongbao') || !Schema::hasColumn('thongbao', 'LoaiThongBao')) {
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
        if (!Schema::hasTable('thongbao')) {
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

    private function healthRiskAlerts(): array
    {
        $alerts = [];
        $today = now('Asia/Ho_Chi_Minh')->toDateString();

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

        $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($alerts, fn ($a, $b) => ($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0));

        return array_slice($alerts, 0, 12);
    }

    private function weightRiskAlerts(): array
    {
        if (!Schema::hasTable('chisosuckhoe') || !Schema::hasColumn('chisosuckhoe', 'CanNang')) {
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
                if (!$latest || !$previous) {
                    return [];
                }
                $delta = round((float) $latest->CanNang - (float) $previous->CanNang, 1);
                if (abs($delta) < 3) {
                    return [];
                }

                return [[
                    'type' => 'CanNang',
                    'severity' => abs($delta) >= 5 ? 'high' : 'medium',
                    'user_id' => (int) $userId,
                    'user' => $this->userDisplayName((int) $userId),
                    'title' => 'Bien dong can nang nhanh',
                    'message' => 'Can nang thay doi ' . ($delta > 0 ? '+' : '') . $delta . ' kg giua 2 lan ghi nhan gan nhat.',
                    'action' => 'Kiem tra lai chi so va gui thong bao tu van cho nguoi dung.',
                ]];
            })
            ->values()
            ->all();
    }

    private function medicineRiskAlerts(string $today): array
    {
        if (!Schema::hasTable('lichdungthuoc')) {
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
                'type' => 'Thuoc',
                'severity' => 'high',
                'user_id' => (int) $row->NguoiDungID,
                'user' => $this->userDisplayName((int) $row->NguoiDungID),
                'title' => 'Uong thuoc vuot khuyen cao',
                'message' => ($row->TenThuoc ?: 'Thuoc #' . $row->ThuocID) . ' da duoc ghi nhan ' . (int) $row->total . ' lan trong ngay.',
                'action' => 'Lien he nguoi dung va doi chieu lieu dung truoc khi tiep tuc.',
            ])
            ->values()
            ->all();
    }

    private function waterRiskAlerts(string $today): array
    {
        if (!Schema::hasTable('theodoinuoc')) {
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
                'type' => 'Nuoc',
                'severity' => 'medium',
                'user_id' => (int) $row->NguoiDungID,
                'user' => $this->userDisplayName((int) $row->NguoiDungID),
                'title' => 'Luong nuoc trong ngay cao bat thuong',
                'message' => 'Tong nuoc hom nay: ' . (int) $row->total_ml . ' ml.',
                'action' => 'Nhac nguoi dung kiem tra lai du lieu va uong theo nhu cau co the.',
            ])
            ->values()
            ->all();
    }

    private function calorieRiskAlerts(string $today): array
    {
        if (!Schema::hasTable('buaan')) {
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
                'type' => 'DinhDuong',
                'severity' => ((float) $row->total_kcal > 4000) ? 'medium' : 'low',
                'user_id' => (int) $row->NguoiDungID,
                'user' => $this->userDisplayName((int) $row->NguoiDungID),
                'title' => 'Calo trong ngay bat thuong',
                'message' => 'Tong calo hom nay: ' . (int) $row->total_kcal . ' kcal.',
                'action' => 'Gui goi y dieu chinh bua an hoac kiem tra lai ghi nhan.',
            ])
            ->values()
            ->all();
    }

    private function userDisplayName(int $userId): string
    {
        if (Schema::hasTable('hosonguoidung')) {
            $name = DB::table('hosonguoidung')->where('NguoiDungID', $userId)->value('Ten');
            if ($name) {
                return $name . ' (#' . $userId . ')';
            }
        }

        $email = Schema::hasTable('taikhoan')
            ? DB::table('taikhoan')->where('ID', $userId)->value('Email')
            : null;

        return ($email ?: 'Nguoi dung') . ' (#' . $userId . ')';
    }

    private function formatAccount($account, ?array $preloadedStats = null): array
    {
        if (!$account) {
            return [];
        }

        $id = (int) $account->ID;
        $isActive = (int) ($account->TrangThaiHoatDong ?? 1) === 1;

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
            'status' => $isActive ? 'Đang hoạt động' : 'Đã khóa',
            'last_login' => $account->LanDangNhapCuoi,
            'created_at' => $account->NgayTao,
            'stats' => $preloadedStats ?? $this->statsForUser($id),
        ];
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
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'NguoiDungID')) {
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
        if (!Schema::hasTable('thongbao') || !Schema::hasColumn('thongbao', 'NguoiDungID')) {
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

    private function countTable(string $table, ?callable $callback = null): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function countUserRows(string $table, int $userId, ?callable $callback = null): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'NguoiDungID')) {
            return 0;
        }

        $query = DB::table($table)->where('NguoiDungID', $userId);
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function countByDate(string $table, string $column, string $date): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->whereDate($column, $date)->count();
    }

    private function sumColumn(string $table, string $column): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->sum($column);
    }
}

