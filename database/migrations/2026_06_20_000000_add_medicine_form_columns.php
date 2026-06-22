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
                if (!Schema::hasColumn('lichdungthuoc', 'DangThuoc')) {
                    $table->string('DangThuoc', 100)->nullable()->after('LoaiThuoc');
                }
            });
        }

        if (Schema::hasTable('thuoc')) {
            Schema::table('thuoc', function (Blueprint $table) {
                if (!Schema::hasColumn('thuoc', 'DangThuoc')) {
                    $table->string('DangThuoc', 100)->nullable()->after('NhomThuoc');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lichdungthuoc')) {
            Schema::table('lichdungthuoc', function (Blueprint $table) {
                if (Schema::hasColumn('lichdungthuoc', 'DangThuoc')) {
                    $table->dropColumn('DangThuoc');
                }
            });
        }

        if (Schema::hasTable('thuoc')) {
            Schema::table('thuoc', function (Blueprint $table) {
                if (Schema::hasColumn('thuoc', 'DangThuoc')) {
                    $table->dropColumn('DangThuoc');
                }
            });
        }
    }
};
