<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lichdungthuoc')) {
            Schema::table('lichdungthuoc', function (Blueprint $table) {
                if (!Schema::hasColumn('lichdungthuoc', 'ThoiGianUongThucTe')) {
                    $table->dateTime('ThoiGianUongThucTe')->nullable()->after('ThoiGian');
                }
                if (!Schema::hasColumn('lichdungthuoc', 'NgayCapNhat')) {
                    $table->dateTime('NgayCapNhat')->nullable()->after('TanSuat');
                }
            });
        }

        if (Schema::hasTable('thuoc')) {
            Schema::table('thuoc', function (Blueprint $table) {
                if (!Schema::hasColumn('thuoc', 'IconThuoc')) {
                    $table->string('IconThuoc', 20)->nullable()->after('NhomThuoc');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lichdungthuoc')) {
            Schema::table('lichdungthuoc', function (Blueprint $table) {
                if (Schema::hasColumn('lichdungthuoc', 'ThoiGianUongThucTe')) {
                    $table->dropColumn('ThoiGianUongThucTe');
                }
                if (Schema::hasColumn('lichdungthuoc', 'NgayCapNhat')) {
                    $table->dropColumn('NgayCapNhat');
                }
            });
        }

        if (Schema::hasTable('thuoc')) {
            Schema::table('thuoc', function (Blueprint $table) {
                if (Schema::hasColumn('thuoc', 'IconThuoc')) {
                    $table->dropColumn('IconThuoc');
                }
            });
        }
    }
};
