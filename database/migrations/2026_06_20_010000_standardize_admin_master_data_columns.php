<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('thuoc')) {
            Schema::table('thuoc', function (Blueprint $table) {
                if (! Schema::hasColumn('thuoc', 'ten_thuoc')) {
                    $table->string('ten_thuoc')->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'hoat_chat')) {
                    $table->string('hoat_chat')->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'mo_ta')) {
                    $table->text('mo_ta')->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'tac_dung_phu')) {
                    $table->text('tac_dung_phu')->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'canh_bao_ghi_chu')) {
                    $table->text('canh_bao_ghi_chu')->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'lieu_luong_goc')) {
                    $table->string('lieu_luong_goc', 100)->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'ham_luong_goc')) {
                    $table->string('ham_luong_goc', 100)->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'hinh_anh')) {
                    $table->string('hinh_anh', 2048)->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
                if (! Schema::hasColumn('thuoc', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->index();
                }
                if (! Schema::hasColumn('thuoc', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('thuoc', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            $this->backfillFromLegacy('thuoc', [
                'ten_thuoc' => 'TenThuoc',
                'hoat_chat' => 'HoatChat',
                'mo_ta' => 'MoTa',
                'tac_dung_phu' => 'TacDungPhu',
                'canh_bao_ghi_chu' => 'CanhBao',
                'lieu_luong_goc' => 'LieuLuong',
                'ham_luong_goc' => 'LieuLuong',
            ]);
        }

        if (Schema::hasTable('thucpham')) {
            Schema::table('thucpham', function (Blueprint $table) {
                if (! Schema::hasColumn('thucpham', 'ten_thuc_pham')) {
                    $table->string('ten_thuc_pham')->nullable();
                }
                if (! Schema::hasColumn('thucpham', 'loai_thuc_pham')) {
                    $table->string('loai_thuc_pham', 100)->nullable();
                }
                if (! Schema::hasColumn('thucpham', 'calo_goc')) {
                    $table->decimal('calo_goc', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('thucpham', 'thanh_phan')) {
                    $table->text('thanh_phan')->nullable();
                }
                if (! Schema::hasColumn('thucpham', 'hinh_anh')) {
                    $table->string('hinh_anh', 2048)->nullable();
                }
                if (! Schema::hasColumn('thucpham', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
                if (! Schema::hasColumn('thucpham', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->index();
                }
                if (! Schema::hasColumn('thucpham', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('thucpham', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            $this->backfillFromLegacy('thucpham', [
                'ten_thuc_pham' => 'Ten',
                'loai_thuc_pham' => 'LoaiThucPham',
                'calo_goc' => 'Calo',
                'thanh_phan' => 'Keywords',
            ]);
        }

        if (Schema::hasTable('hoatdong')) {
            Schema::table('hoatdong', function (Blueprint $table) {
                if (! Schema::hasColumn('hoatdong', 'ten_van_dong')) {
                    $table->string('ten_van_dong')->nullable();
                }
                if (! Schema::hasColumn('hoatdong', 'mo_ta')) {
                    $table->text('mo_ta')->nullable();
                }
                if (! Schema::hasColumn('hoatdong', 'chi_so_met')) {
                    $table->decimal('chi_so_met', 5, 2)->nullable();
                }
                if (! Schema::hasColumn('hoatdong', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
                if (! Schema::hasColumn('hoatdong', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->index();
                }
                if (! Schema::hasColumn('hoatdong', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('hoatdong', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            $this->backfillFromLegacy('hoatdong', [
                'ten_van_dong' => 'TenHoatDong',
                'mo_ta' => 'MoTa',
                'chi_so_met' => 'MET',
            ]);
        }
    }

    public function down(): void
    {
        $this->dropIfExists('thuoc', [
            'ten_thuoc',
            'hoat_chat',
            'mo_ta',
            'tac_dung_phu',
            'canh_bao_ghi_chu',
            'lieu_luong_goc',
            'ham_luong_goc',
            'hinh_anh',
            'is_active',
            'deleted_at',
            'created_at',
            'updated_at',
        ]);

        $this->dropIfExists('thucpham', [
            'ten_thuc_pham',
            'loai_thuc_pham',
            'calo_goc',
            'thanh_phan',
            'hinh_anh',
            'is_active',
            'deleted_at',
            'created_at',
            'updated_at',
        ]);

        $this->dropIfExists('hoatdong', [
            'ten_van_dong',
            'mo_ta',
            'chi_so_met',
            'is_active',
            'deleted_at',
            'created_at',
            'updated_at',
        ]);
    }

    private function dropIfExists(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function backfillFromLegacy(string $table, array $columnMap): void
    {
        $updates = [];
        foreach ($columnMap as $snakeColumn => $legacyColumn) {
            if (Schema::hasColumn($table, $snakeColumn) && Schema::hasColumn($table, $legacyColumn)) {
                $updates[$snakeColumn] = DB::raw($legacyColumn);
            }
        }

        if ($updates !== []) {
            DB::table($table)->update($updates);
        }
    }
};
