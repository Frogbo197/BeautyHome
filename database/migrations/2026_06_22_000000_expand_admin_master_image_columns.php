<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->modifyToTextIfExists('thuoc', 'IconThuoc');
        $this->modifyToTextIfExists('thuoc', 'hinh_anh');
        $this->modifyToTextIfExists('thucpham', 'hinh_anh');
        $this->modifyToTextIfExists('thucpham', 'HinhAnh');
        $this->modifyToTextIfExists('hoat_dongs', 'hinh_anh_icon');

        if (Schema::hasTable('hoat_dongs') && ! Schema::hasColumn('hoat_dongs', 'hinh_anh')) {
            Schema::table('hoat_dongs', function (Blueprint $table) {
                $table->text('hinh_anh')->nullable()->after('chi_so_met');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hoat_dongs') && Schema::hasColumn('hoat_dongs', 'hinh_anh')) {
            Schema::table('hoat_dongs', function (Blueprint $table) {
                $table->dropColumn('hinh_anh');
            });
        }
    }

    private function modifyToTextIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TEXT NULL");
        }
    }
};
