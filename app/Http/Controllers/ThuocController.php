<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Service\HealthRiskEngineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ThuocController extends Controller
{
    private const STATUSES = ['can_uong', 'da_uong', 'bo_lo', 'tam_ngung', 'het_lieu', 'da_huy'];

    public function themThuoc(Request $request)
    {
        $data = $request->validate([
            'nguoiDungId' => 'required|integer',
            'tenThuoc' => 'required|string|max:255',
            'lieuLuong' => 'required|string|max:100',
            'thoiGian' => 'required',
            'donVi' => 'nullable|string|max:50',
            'ghiChu' => 'nullable|string',
            'loaiThuoc' => 'nullable|string|max:100',
            'dangThuoc' => 'nullable|string|max:100',
            'iconThuoc' => 'nullable|string|max:20',
            'soLanMoiNgay' => 'nullable|integer|min:1|max:12',
            'allow_duplicate' => 'nullable|boolean',
        ]);

        if (!$this->isAllowedMedicineName((string) $data['tenThuoc'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vui long chon thuoc tu danh muc goi y',
            ], 422);
        }

        $time = $this->normalizeDateTime((string) $data['thoiGian']);
        $thuocId = $this->upsertMedicineMaster($data, $time);

        $exists = DB::table('lichdungthuoc')
            ->where('NguoiDungID', $data['nguoiDungId'])
            ->where('ThuocID', $thuocId)
            ->where('ThoiGian', $time)
            ->where(function ($q) {
                $q->whereNull('TrangThai')->orWhere('TrangThai', '<>', 'da_huy');
            })
            ->exists();
        if ($exists && empty($data['allow_duplicate'])) {
            return response()->json([
                'success' => false,
                'message' => 'Thuoc nay da ton tai',
            ], 409);
        }

        $payload = $this->onlyExistingColumns('lichdungthuoc', [
            'NguoiDungID' => $data['nguoiDungId'],
            'ThuocID' => $thuocId,
            'ThoiGian' => $time,
            'TrangThai' => 'can_uong',
            'DonVi' => $data['donVi'] ?? '',
            'LieuLuong' => $data['lieuLuong'],
            'GhiChu' => $data['ghiChu'] ?? '',
            'LoaiThuoc' => $data['loaiThuoc'] ?? 'Khac',
            'DangThuoc' => $data['dangThuoc'] ?? 'Vien uong',
            'IconThuoc' => $data['iconThuoc'] ?? 'pill',
            'TanSuat' => $data['soLanMoiNgay'] ?? 1,
            'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
        ]);

        $id = DB::table('lichdungthuoc')->insertGetId($payload);

        return response()->json([
            'success' => true,
            'message' => 'Da them thuoc thanh cong',
            'data' => $this->findScheduleRow($id),
        ]);
    }

    public function layDanhSachThuoc(Request $request, $nguoiDungId)
    {
        $date = $request->query('date');
        $query = $this->baseScheduleQuery($nguoiDungId);

        if ($date) {
            $query->whereDate('lichdungthuoc.ThoiGian', $date);
        }

        $danhSachThuoc = $query
            ->select($this->scheduleSelectColumns())
            ->orderBy('lichdungthuoc.ThoiGian', 'asc')
            ->get();

        if ($date && $danhSachThuoc->isEmpty()) {
            $danhSachThuoc = $this->lichThuocDaiHanTheoNgay((int) $nguoiDungId, (string) $date);
        }

        return response()->json([
            'success' => true,
            'data' => $danhSachThuoc,
        ]);
    }

    public function danhDauDaUong($id, HealthRiskEngineService $riskEngine)
    {
        return $this->capNhatTrangThai(new Request(['trangThai' => 'da_uong']), (int) $id, $riskEngine);
    }

    public function capNhatTrangThai(Request $request, int $id, HealthRiskEngineService $riskEngine)
    {
        $data = $request->validate([
            'trangThai' => ['required', 'string', Rule::in(self::STATUSES)],
            'thoiGianUongThucTe' => 'nullable|date',
            'thoiGian' => 'nullable',
        ]);

        if (!empty($data['thoiGian'])) {
            $id = $this->ensureScheduleForDisplayedDate($id, (string) $data['thoiGian']);
        }

        $status = $data['trangThai'];
        $payload = ['TrangThai' => $status];
        if (Schema::hasColumn('lichdungthuoc', 'ThoiGianUongThucTe')) {
            $payload['ThoiGianUongThucTe'] = $status === 'da_uong'
                ? $this->normalizeDateTime($data['thoiGianUongThucTe'] ?? now('Asia/Ho_Chi_Minh')->toDateTimeString())
                : null;
        }
        if (Schema::hasColumn('lichdungthuoc', 'NgayCapNhat')) {
            $payload['NgayCapNhat'] = now('Asia/Ho_Chi_Minh');
        }

        DB::table('lichdungthuoc')->where('ID', $id)->update($payload);
        $row = DB::table('lichdungthuoc')->where('ID', $id)->first();
        if ($row) {
            try {
                $riskEngine->evaluateAfterMedication((int) $row->NguoiDungID, substr((string) $row->ThoiGian, 0, 10), (int) $id);
            } catch (\Throwable $e) {
                // Risk checks must never block medication status updates.
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Da cap nhat trang thai',
            'data' => $this->findScheduleRow($id),
        ]);
    }

    public function capNhatThuoc(Request $request, int $id)
    {
        try {
            $request->replace($this->normalizeMedicineUpdateInput($request));

            $data = $request->validate([
                'tenThuoc' => 'nullable|string|max:255',
                'lieuLuong' => 'nullable|string|max:100',
                'donVi' => 'nullable|string|max:50',
                'thoiGian' => 'nullable',
                'ghiChu' => 'nullable|string',
                'loaiThuoc' => 'nullable|string|max:100',
                'dangThuoc' => 'nullable|string|max:100',
                'iconThuoc' => 'nullable|string|max:20',
                'soLanMoiNgay' => 'nullable|integer|min:1|max:12',
                'trangThai' => ['nullable', 'string', Rule::in(self::STATUSES)],
                'khung_gios' => 'nullable|array|min:1|max:12',
                'khung_gios.*' => 'required|date_format:H:i',
            ]);

            $row = DB::table('lichdungthuoc')->where('ID', $id)->first();
            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Khong tim thay lich thuoc'], 404);
            }

            $targetThuocId = $this->medicineIdForUpdate($data, $row);
            $hasKhungGios = isset($data['khung_gios']) && is_array($data['khung_gios']) && count($data['khung_gios']) > 0;
            if (! $hasKhungGios) {
                DB::transaction(function () use ($data, $row, $id, $targetThuocId) {
                    if ($targetThuocId !== null) {
                        $this->updateMedicineMasterForSchedule($targetThuocId, $data);
                    }

                    DB::table('lichdungthuoc')->where('ID', $id)->update(
                        $this->onlyExistingColumns('lichdungthuoc', [
                            'ThuocID' => $targetThuocId,
                            'TrangThai' => $data['trangThai'] ?? null,
                            'DonVi' => $data['donVi'] ?? null,
                            'LieuLuong' => $data['lieuLuong'] ?? null,
                            'GhiChu' => $data['ghiChu'] ?? null,
                            'LoaiThuoc' => $data['loaiThuoc'] ?? null,
                            'DangThuoc' => $data['dangThuoc'] ?? null,
                            'IconThuoc' => $data['iconThuoc'] ?? null,
                            'TanSuat' => $data['soLanMoiNgay'] ?? null,
                            'ThoiGian' => !empty($data['thoiGian'])
                                ? $this->normalizeDateTime((string) $data['thoiGian'])
                                : null,
                            'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
                        ], true)
                    );
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Cap nhat thong tin thuoc thanh cong!',
                    'data' => $this->findScheduleRow($id),
                ], 200);
            }

            $khungGios = array_values(array_unique($data['khung_gios']));
            sort($khungGios);
            $encodedKhungGios = json_encode($khungGios, JSON_UNESCAPED_UNICODE);
            $dateSource = (string) ($data['thoiGian'] ?? $row->ThoiGian ?? now('Asia/Ho_Chi_Minh')->toDateTimeString());
            $date = Carbon::parse(str_replace('T', ' ', $dateSource), 'Asia/Ho_Chi_Minh')->toDateString();
            $updatedIds = [];

            DB::transaction(function () use ($data, $row, $id, $khungGios, $encodedKhungGios, $date, $targetThuocId, &$updatedIds) {
                if ($targetThuocId !== null) {
                    $this->updateMedicineMasterForSchedule($targetThuocId, array_merge($data, [
                        'soLanMoiNgay' => count($khungGios),
                    ]));
                }

                $basePayload = $this->onlyExistingColumns('lichdungthuoc', [
                    'ThuocID' => $targetThuocId,
                    'TrangThai' => $data['trangThai'] ?? null,
                    'DonVi' => $data['donVi'] ?? null,
                    'LieuLuong' => $data['lieuLuong'] ?? null,
                    'GhiChu' => $data['ghiChu'] ?? null,
                    'LoaiThuoc' => $data['loaiThuoc'] ?? null,
                    'DangThuoc' => $data['dangThuoc'] ?? null,
                    'IconThuoc' => $data['iconThuoc'] ?? null,
                    'TanSuat' => count($khungGios),
                    'khung_gios' => $encodedKhungGios,
                    'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
                ], true);

                $existingRows = DB::table('lichdungthuoc')
                    ->where('NguoiDungID', $row->NguoiDungID)
                    ->where('ThuocID', $row->ThuocID)
                    ->whereDate('ThoiGian', $date)
                    ->where(function ($query) {
                        $query->whereNull('TrangThai')->orWhere('TrangThai', '<>', 'da_huy');
                    })
                    ->orderBy('ThoiGian')
                    ->get();

                if ($existingRows->isEmpty()) {
                    $existingRows = collect([$row]);
                }

                $rowsByTime = $existingRows->keyBy(fn ($item) => substr((string) $item->ThoiGian, 11, 5));
                $unusedRows = $existingRows->values();
                $usedRowIds = [];

                foreach ($khungGios as $clock) {
                    $target = $rowsByTime->get($clock);
                    if (!$target) {
                        $target = $unusedRows->first(fn ($item) => !in_array((int) $item->ID, $usedRowIds, true));
                    }

                    $schedulePayload = array_merge($basePayload, $this->onlyExistingColumns('lichdungthuoc', [
                        'ThoiGian' => "{$date} {$clock}:00",
                    ]));

                    if ($target) {
                        DB::table('lichdungthuoc')->where('ID', $target->ID)->update($schedulePayload);
                        $usedRowIds[] = (int) $target->ID;
                        $updatedIds[] = (int) $target->ID;
                        continue;
                    }

                    $insertPayload = array_merge($schedulePayload, $this->onlyExistingColumns('lichdungthuoc', [
                        'NguoiDungID' => $row->NguoiDungID,
                        'ThuocID' => $targetThuocId ?? $row->ThuocID,
                        'TrangThai' => $data['trangThai'] ?? 'can_uong',
                        'DonVi' => $data['donVi'] ?? $row->DonVi ?? '',
                        'LieuLuong' => $data['lieuLuong'] ?? $row->LieuLuong ?? '',
                        'GhiChu' => $data['ghiChu'] ?? $row->GhiChu ?? '',
                        'LoaiThuoc' => $data['loaiThuoc'] ?? $row->LoaiThuoc ?? null,
                        'DangThuoc' => $data['dangThuoc'] ?? $row->DangThuoc ?? null,
                        'IconThuoc' => $data['iconThuoc'] ?? $row->IconThuoc ?? null,
                        'NgayTao' => now('Asia/Ho_Chi_Minh'),
                    ], true));
                    $updatedIds[] = (int) DB::table('lichdungthuoc')->insertGetId($insertPayload);
                }

                $obsoleteIds = $existingRows
                    ->pluck('ID')
                    ->map(fn ($value) => (int) $value)
                    ->diff($updatedIds)
                    ->values();

                if ($obsoleteIds->isNotEmpty() && Schema::hasColumn('lichdungthuoc', 'TrangThai')) {
                    DB::table('lichdungthuoc')
                        ->whereIn('ID', $obsoleteIds)
                        ->update($this->onlyExistingColumns('lichdungthuoc', [
                            'TrangThai' => 'da_huy',
                            'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
                        ]));
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật khung giờ uống thuốc thành công!',
                'data' => collect($updatedIds)
                    ->unique()
                    ->values()
                    ->map(fn ($scheduleId) => $this->findScheduleRow((int) $scheduleId))
                    ->filter()
                    ->values(),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('medicine.schedule.update_failed', [
                'schedule_id' => $id,
                'payload' => $request->all(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Khong the cap nhat khung gio uong thuoc. Vui long thu lai.',
            ], 500);
        }
    }

    public function xoaThuoc($id)
    {
        $row = DB::table('lichdungthuoc')->where('ID', $id)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay lich thuoc'], 404);
        }

        $query = DB::table('lichdungthuoc')->where('NguoiDungID', $row->NguoiDungID);
        if (!empty($row->ThuocID)) {
            $query->where('ThuocID', $row->ThuocID);
        } else {
            $query->where('ID', $id);
        }

        if (Schema::hasColumn('lichdungthuoc', 'TrangThai')) {
            $query->update($this->onlyExistingColumns('lichdungthuoc', [
                    'TrangThai' => 'da_huy',
                    'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
                ]));
        } else {
            $query->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Da xoa thuoc',
        ]);
    }

    public function baoCao(Request $request, int $nguoiDungId)
    {
        $range = $request->query('range', 'week');
        $loaiThuoc = $request->query('loaiThuoc');
        $now = now('Asia/Ho_Chi_Minh');
        $start = $range === 'month'
            ? $now->copy()->startOfMonth()
            : $now->copy()->startOfWeek();
        $end = $range === 'month'
            ? $now->copy()->endOfMonth()
            : $now->copy()->endOfWeek();

        $query = DB::table('lichdungthuoc')
            ->join('thuoc', 'thuoc.ID', '=', 'lichdungthuoc.ThuocID')
            ->where('lichdungthuoc.NguoiDungID', $nguoiDungId)
            ->whereDate('lichdungthuoc.ThoiGian', '>=', $start->toDateString())
            ->whereDate('lichdungthuoc.ThoiGian', '<=', $end->toDateString())
            ->where(function ($q) {
                $q->whereNull('lichdungthuoc.TrangThai')
                    ->orWhere('lichdungthuoc.TrangThai', '<>', 'da_huy');
            });

        if ($loaiThuoc) {
            $query->where(function ($q) use ($loaiThuoc) {
                $q->where('lichdungthuoc.LoaiThuoc', $loaiThuoc)
                    ->orWhere('thuoc.NhomThuoc', $loaiThuoc);
            });
        }

        $rows = $query->select($this->scheduleSelectColumns())->get();
        $total = $rows->count();
        $taken = $rows->where('TrangThai', 'da_uong')->count() + $rows->where('TrangThai', 'DaUong')->count();
        $missed = $rows->where('TrangThai', 'bo_lo')->count();
        $adherence = $total > 0 ? round($taken / $total * 100, 1) : 0;

        $byMedicine = $rows
            ->groupBy('TenThuoc')
            ->map(function ($items, $name) {
                $total = $items->count();
                $taken = $items->where('TrangThai', 'da_uong')->count() + $items->where('TrangThai', 'DaUong')->count();
                $missed = $items->where('TrangThai', 'bo_lo')->count();
                return [
                    'ten_thuoc' => $name,
                    'tong_lan' => $total,
                    'da_uong' => $taken,
                    'bo_lo' => $missed,
                    'adherence' => $total > 0 ? round($taken / $total * 100, 1) : 0,
                ];
            })
            ->values()
            ->sortByDesc('bo_lo')
            ->values();

        return response()->json([
            'success' => true,
            'range' => $range,
            'summary' => [
                'tong_lan' => $total,
                'da_uong' => $taken,
                'bo_lo' => $missed,
                'adherence' => $adherence,
                'streak' => $this->streak($rows),
                'hay_bo_lo_nhat' => $byMedicine->first(),
            ],
            'by_medicine' => $byMedicine,
            'chart' => $this->chart($rows, $start, $end),
        ]);
    }

    public function timKiemThuoc(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $group = trim((string) $request->query('nhom', $request->query('loai', '')));
        $limit = min(max((int) $request->query('limit', 10), 1), 30);
        $popular = collect($this->popularMedicineCatalog());

        $dbRows = collect();
        if (Schema::hasTable('thuoc')) {
            $dbRows = DB::table('thuoc')
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($inner) use ($q) {
                        $inner->where('TenThuoc', 'like', "%{$q}%");
                        if (Schema::hasColumn('thuoc', 'NhomThuoc')) {
                            $inner->orWhere('NhomThuoc', 'like', "%{$q}%");
                        }
                        if (Schema::hasColumn('thuoc', 'HoatChat')) {
                            $inner->orWhere('HoatChat', 'like', "%{$q}%");
                        }
                    });
                })
                ->when($group !== '' && Schema::hasColumn('thuoc', 'NhomThuoc'), fn ($query) => $query->where('NhomThuoc', 'like', "%{$group}%"))
                ->select($this->medicineSelectColumns())
                ->limit($limit)
                ->get();
        }

        $candidates = $popular
            ->filter(function ($item) use ($q, $group) {
                $matchesQuery = $q === ''
                    || stripos($item['TenThuoc'], $q) !== false
                    || stripos($item['NhomThuoc'], $q) !== false
                    || stripos($item['HoatChat'] ?? '', $q) !== false;
                $matchesGroup = $group === '' || stripos($item['NhomThuoc'], $group) !== false;
                return $matchesQuery && $matchesGroup;
            })
            ->concat($dbRows)
            ->unique(fn ($item) => strtolower((string) $this->valueOf($item, 'TenThuoc', '')))
            ->values();

        $strongMatches = $q !== '' && mb_strlen($q) >= 3
            ? $candidates->filter(fn ($item) => $this->isStrongMedicineMatch($item, $q))->values()
            : collect();

        $items = ($strongMatches->isNotEmpty() ? $strongMatches : $candidates)
            ->sortBy(fn ($item) => $this->medicineSearchSortKey($item, $q))
            ->take($limit)
            ->values()
            ->map(fn ($item) => [
                'TenThuoc' => $this->valueOf($item, 'TenThuoc', ''),
                'LoaiThuoc' => $this->valueOf($item, 'NhomThuoc', 'Khac'),
                'NhomThuoc' => $this->valueOf($item, 'NhomThuoc', 'Khac'),
                'DangThuoc' => $this->valueOf($item, 'DangThuoc', ''),
                'HoatChat' => $this->valueOf($item, 'HoatChat', ''),
                'IconThuoc' => $this->valueOf($item, 'IconThuoc', 'pill'),
                'DonVi' => $this->valueOf($item, 'DonVi', 'mg'),
                'LieuLuong' => $this->valueOf($item, 'LieuLuong', ''),
                'GhiChu' => $this->valueOf($item, 'GhiChu', ''),
                'CanhBao' => $this->valueOf($item, 'CanhBao', ''),
                'NguonThamKhao' => $this->valueOf($item, 'NguonThamKhao', 'Danh muc noi bo'),
            ]);

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function danhMucThuoc()
    {
        $groups = collect($this->popularMedicineCatalog())
            ->groupBy('NhomThuoc')
            ->map(fn ($items, $name) => [
                'ten_nhom' => $name,
                'so_luong_goi_y' => $items->count(),
                'vi_du' => $items->take(4)->pluck('TenThuoc')->values(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Danh muc nay dung de goi y ten thuoc va nhom thuoc, khong thay the don thuoc cua bac si.',
            'data' => $groups,
        ]);
    }

    private function medicineIdForUpdate(array $data, $row): ?int
    {
        $currentId = isset($row->ThuocID) ? (int) $row->ThuocID : null;
        if ($currentId !== null && $currentId <= 0) {
            $currentId = null;
        }

        $name = trim((string) ($data['tenThuoc'] ?? ''));
        if ($name === '' || ! Schema::hasTable('thuoc')) {
            return $currentId;
        }

        $existing = DB::table('thuoc')->where('TenThuoc', $name)->first();
        if ($existing) {
            return (int) $existing->ID;
        }

        return (int) DB::table('thuoc')->insertGetId($this->onlyExistingColumns('thuoc', [
            'TenThuoc' => $name,
            'MoTa' => $data['moTa'] ?? '',
            'TacDungPhu' => $data['tacDungPhu'] ?? '',
            'LieuLuong' => $data['lieuLuong'] ?? $row->LieuLuong ?? '',
            'DonVi' => $data['donVi'] ?? $row->DonVi ?? '',
            'SoLanMoiNgay' => $data['soLanMoiNgay'] ?? $row->TanSuat ?? 1,
            'GhiChu' => $data['ghiChu'] ?? $row->GhiChu ?? '',
            'CanhBao' => '',
            'ThoiGian' => $data['thoiGian'] ?? $row->ThoiGian ?? null,
            'TrangThai' => $data['trangThai'] ?? $row->TrangThai ?? 'can_uong',
            'HoatChat' => '',
            'NhomThuoc' => $data['loaiThuoc'] ?? $row->LoaiThuoc ?? '',
            'DangThuoc' => $data['dangThuoc'] ?? $row->DangThuoc ?? 'Vien uong',
            'IconThuoc' => $data['iconThuoc'] ?? $row->IconThuoc ?? 'pill',
        ], true));
    }

    private function updateMedicineMasterForSchedule(int $thuocId, array $data): void
    {
        if (! Schema::hasTable('thuoc')) {
            return;
        }

        $payload = [];
        if (!empty($data['tenThuoc'])) {
            $payload['TenThuoc'] = $data['tenThuoc'];
        }
        if (array_key_exists('lieuLuong', $data)) {
            $payload['LieuLuong'] = $data['lieuLuong'];
        }
        if (array_key_exists('donVi', $data)) {
            $payload['DonVi'] = $data['donVi'];
        }
        if (array_key_exists('soLanMoiNgay', $data)) {
            $payload['SoLanMoiNgay'] = $data['soLanMoiNgay'];
        }
        if (array_key_exists('ghiChu', $data) && !empty($data['tenThuoc'])) {
            $payload['GhiChu'] = $data['ghiChu'];
        }
        if (array_key_exists('loaiThuoc', $data)) {
            $payload['NhomThuoc'] = $data['loaiThuoc'];
        }
        if (array_key_exists('dangThuoc', $data)) {
            $payload['DangThuoc'] = $data['dangThuoc'];
        }
        if (array_key_exists('iconThuoc', $data)) {
            $payload['IconThuoc'] = $data['iconThuoc'];
        }

        $payload = $this->onlyExistingColumns('thuoc', $payload, true);
        if (!empty($payload)) {
            DB::table('thuoc')->where('ID', $thuocId)->update($payload);
        }
    }

    private function upsertMedicineMaster(array $data, string $time): int
    {
        $thuoc = DB::table('thuoc')->where('TenThuoc', $data['tenThuoc'])->first();
        if ($thuoc) {
            return (int) $thuoc->ID;
        }

        return DB::table('thuoc')->insertGetId($this->onlyExistingColumns('thuoc', [
            'TenThuoc' => $data['tenThuoc'],
            'MoTa' => $data['moTa'] ?? '',
            'TacDungPhu' => $data['tacDungPhu'] ?? '',
            'LieuLuong' => $data['lieuLuong'],
            'DonVi' => $data['donVi'] ?? '',
            'SoLanMoiNgay' => $data['soLanMoiNgay'] ?? 1,
            'GhiChu' => $data['ghiChu'] ?? '',
            'CanhBao' => '',
            'ThoiGian' => $time,
            'TrangThai' => 'can_uong',
            'HoatChat' => '',
            'NhomThuoc' => $data['loaiThuoc'] ?? '',
            'DangThuoc' => $data['dangThuoc'] ?? 'Vien uong',
            'IconThuoc' => $data['iconThuoc'] ?? 'pill',
        ]));
    }

    private function ensureScheduleForDisplayedDate(int $id, string $displayedTime): int
    {
        $row = DB::table('lichdungthuoc')->where('ID', $id)->first();
        if (!$row) {
            return $id;
        }

        $time = $this->normalizeDateTime($displayedTime);
        if ((string) $row->ThoiGian === $time) {
            return $id;
        }

        $existing = DB::table('lichdungthuoc')
            ->where('NguoiDungID', $row->NguoiDungID)
            ->where('ThuocID', $row->ThuocID)
            ->where('ThoiGian', $time)
            ->where(function ($q) {
                $q->whereNull('TrangThai')->orWhere('TrangThai', '<>', 'da_huy');
            })
            ->first();
        if ($existing) {
            return (int) $existing->ID;
        }

        $payload = $this->onlyExistingColumns('lichdungthuoc', [
            'NguoiDungID' => $row->NguoiDungID,
            'ThuocID' => $row->ThuocID,
            'ThoiGian' => $time,
            'TrangThai' => 'can_uong',
            'DonVi' => $row->DonVi ?? '',
            'LieuLuong' => $row->LieuLuong ?? '',
            'GhiChu' => $row->GhiChu ?? '',
            'LoaiThuoc' => $row->LoaiThuoc ?? 'Vien uong',
            'DangThuoc' => $row->DangThuoc ?? 'Vien uong',
            'IconThuoc' => $row->IconThuoc ?? 'pill',
            'TanSuat' => $row->TanSuat ?? 1,
            'NgayTao' => now('Asia/Ho_Chi_Minh'),
            'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
        ]);

        return (int) DB::table('lichdungthuoc')->insertGetId($payload);
    }

    private function findScheduleRow(int $id)
    {
        return DB::table('lichdungthuoc')
            ->join('thuoc', 'thuoc.ID', '=', 'lichdungthuoc.ThuocID')
            ->where('lichdungthuoc.ID', $id)
            ->select($this->scheduleSelectColumns())
            ->first();
    }

    private function baseScheduleQuery(int $nguoiDungId)
    {
        return DB::table('lichdungthuoc')
            ->join('thuoc', 'thuoc.ID', '=', 'lichdungthuoc.ThuocID')
            ->where('lichdungthuoc.NguoiDungID', $nguoiDungId)
            ->where(function ($q) {
                $q->whereNull('lichdungthuoc.TrangThai')
                    ->orWhere('lichdungthuoc.TrangThai', '<>', 'da_huy');
            });
    }

    private function lichThuocDaiHanTheoNgay(int $nguoiDungId, string $date)
    {
        $rows = $this->baseScheduleQuery($nguoiDungId)
            ->whereDate('lichdungthuoc.ThoiGian', '<=', $date)
            ->whereNotIn('lichdungthuoc.TrangThai', ['het_lieu', 'tam_ngung', 'da_huy'])
            ->select($this->scheduleSelectColumns())
            ->orderBy('lichdungthuoc.ThoiGian', 'asc')
            ->get();

        return $rows
            ->unique(function ($row) {
                return implode('|', [
                    $row->ThuocID ?? '',
                    $row->TenThuoc ?? '',
                    $row->LieuLuong ?? '',
                    $row->DonVi ?? '',
                    substr((string) $row->ThoiGian, 11, 5),
                ]);
            })
            ->map(function ($row) use ($date) {
                $time = substr((string) $row->ThoiGian, 11, 8) ?: '08:00:00';
                $row->ThoiGian = "{$date} {$time}";
                $row->TrangThai = 'can_uong';
                $row->ThoiGianUongThucTe = null;
                return $row;
            })
            ->values();
    }

    private function scheduleSelectColumns(): array
    {
        $select = [
            'lichdungthuoc.ID',
            'lichdungthuoc.ThuocID',
            'thuoc.TenThuoc',
            'lichdungthuoc.LieuLuong',
            'lichdungthuoc.DonVi',
            'lichdungthuoc.ThoiGian',
            'lichdungthuoc.TrangThai',
            'lichdungthuoc.GhiChu',
        ];
        foreach ([
            'ThoiGianUongThucTe',
            'LoaiThuoc',
            'DangThuoc',
            'IconThuoc',
            'TanSuat',
            'khung_gios',
        ] as $column) {
            if (Schema::hasColumn('lichdungthuoc', $column)) {
                $select[] = "lichdungthuoc.$column";
            }
        }
        if (Schema::hasColumn('thuoc', 'SoLanMoiNgay')) {
            $select[] = 'thuoc.SoLanMoiNgay';
        }
        if (Schema::hasColumn('thuoc', 'NhomThuoc')) {
            $select[] = 'thuoc.NhomThuoc';
        }
        return $select;
    }

    private function medicineSelectColumns(): array
    {
        return array_values(array_filter([
            'TenThuoc',
            Schema::hasColumn('thuoc', 'HoatChat') ? 'HoatChat' : null,
            Schema::hasColumn('thuoc', 'NhomThuoc') ? 'NhomThuoc' : null,
            Schema::hasColumn('thuoc', 'DangThuoc') ? 'DangThuoc' : null,
            Schema::hasColumn('thuoc', 'IconThuoc') ? 'IconThuoc' : null,
            Schema::hasColumn('thuoc', 'DonVi') ? 'DonVi' : null,
            Schema::hasColumn('thuoc', 'LieuLuong') ? 'LieuLuong' : null,
            Schema::hasColumn('thuoc', 'GhiChu') ? 'GhiChu' : null,
            Schema::hasColumn('thuoc', 'CanhBao') ? 'CanhBao' : null,
        ]));
    }

    private function popularMedicineCatalog(): array
    {
        return config('medicine_catalog.items', []);
    }

    private function isAllowedMedicineName(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $needle = mb_strtolower($name);
        foreach ($this->popularMedicineCatalog() as $item) {
            if (mb_strtolower((string) $this->valueOf($item, 'TenThuoc', '')) === $needle) {
                return true;
            }
        }

        return Schema::hasTable('thuoc')
            && DB::table('thuoc')->where('TenThuoc', $name)->exists();
    }

    private function medicineSearchSortKey($item, string $q): string
    {
        $name = mb_strtolower((string) $this->valueOf($item, 'TenThuoc', ''));
        $active = mb_strtolower((string) $this->valueOf($item, 'HoatChat', ''));
        $group = mb_strtolower((string) $this->valueOf($item, 'NhomThuoc', ''));
        $needle = mb_strtolower($q);

        $score = 50;
        if ($needle !== '') {
            if (str_starts_with($name, $needle)) {
                $score = 0;
            } elseif (str_contains($name, $needle)) {
                $score = 10;
            } elseif (str_starts_with($active, $needle)) {
                $score = 20;
            } elseif (str_contains($active, $needle)) {
                $score = 30;
            } elseif (str_contains($group, $needle)) {
                $score = 40;
            }
        }

        return sprintf('%03d-%s', $score, $name);
    }

    private function isStrongMedicineMatch($item, string $q): bool
    {
        $needle = mb_strtolower($q);
        $name = mb_strtolower((string) $this->valueOf($item, 'TenThuoc', ''));
        $active = mb_strtolower((string) $this->valueOf($item, 'HoatChat', ''));

        return str_starts_with($name, $needle)
            || str_starts_with($active, $needle)
            || str_contains($active, ' ' . $needle);
    }

    private function chart($rows, Carbon $start, Carbon $end): array
    {
        $out = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $items = $rows->filter(fn ($row) => substr((string) $row->ThoiGian, 0, 10) === $date);
            $total = $items->count();
            $taken = $items->where('TrangThai', 'da_uong')->count() + $items->where('TrangThai', 'DaUong')->count();
            $out[] = [
                'label' => $date,
                'tong_lan' => $total,
                'da_uong' => $taken,
                'bo_lo' => $items->where('TrangThai', 'bo_lo')->count(),
                'adherence' => $total > 0 ? round($taken / $total * 100, 1) : 0,
            ];
            $cursor->addDay();
        }
        return $out;
    }

    private function streak($rows): int
    {
        $byDay = $rows->groupBy(fn ($row) => substr((string) $row->ThoiGian, 0, 10));
        $streak = 0;
        $cursor = now('Asia/Ho_Chi_Minh')->startOfDay();
        while (true) {
            $key = $cursor->toDateString();
            if (!$byDay->has($key)) break;
            $items = $byDay[$key];
            $total = $items->count();
            $taken = $items->where('TrangThai', 'da_uong')->count() + $items->where('TrangThai', 'DaUong')->count();
            if ($total === 0 || $taken < $total) break;
            $streak++;
            $cursor->subDay();
        }
        return $streak;
    }

    private function normalizeMedicineUpdateInput(Request $request): array
    {
        $input = $request->all();
        $aliases = [
            'tenThuoc' => ['ten_thuoc'],
            'lieuLuong' => ['lieu_luong', 'lieu_luong_goc'],
            'donVi' => ['don_vi'],
            'thoiGian' => ['thoi_gian', 'gio_uong'],
            'ghiChu' => ['ghi_chu', 'ghi_chu_rieng'],
            'loaiThuoc' => ['loai_thuoc'],
            'dangThuoc' => ['dang_thuoc'],
            'iconThuoc' => ['icon_thuoc'],
            'soLanMoiNgay' => ['so_lan_moi_ngay', 'tan_suat'],
            'trangThai' => ['trang_thai'],
            'khung_gios' => ['khungGios', 'cac_khung_gio', 'cacKhungGio', 'gio_uongs'],
        ];

        foreach ($aliases as $target => $sources) {
            if (array_key_exists($target, $input) && $input[$target] !== null) {
                continue;
            }

            foreach ($sources as $source) {
                if (array_key_exists($source, $input)) {
                    $input[$target] = $input[$source];
                    break;
                }
            }
        }

        if (array_key_exists('khung_gios', $input) && $input['khung_gios'] !== null) {
            $rawKhungGios = $input['khung_gios'];
            if (is_string($rawKhungGios)) {
                $decoded = json_decode($rawKhungGios, true);
                $rawKhungGios = is_array($decoded) ? $decoded : explode(',', $rawKhungGios);
            } elseif (! is_array($rawKhungGios)) {
                $rawKhungGios = [$rawKhungGios];
            }

            $input['khung_gios'] = array_values(array_filter(
                array_map(fn ($value) => trim((string) $value), $rawKhungGios),
                fn ($value) => $value !== ''
            ));
        }

        return $input;
    }

    private function onlyExistingColumns(string $table, array $payload, bool $skipNull = false): array
    {
        $out = [];
        foreach ($payload as $column => $value) {
            if ((!$skipNull || $value !== null) && Schema::hasColumn($table, $column)) {
                $out[$column] = $value;
            }
        }
        return $out;
    }

    private function valueOf($item, string $key, $default = null)
    {
        if (is_array($item)) {
            return $item[$key] ?? $default;
        }
        return $item->{$key} ?? $default;
    }

    private function normalizeDateTime(string $value): string
    {
        try {
            return Carbon::parse(str_replace('T', ' ', $value), 'Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
        }
    }
}
