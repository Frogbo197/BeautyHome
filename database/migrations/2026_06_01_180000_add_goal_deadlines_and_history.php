<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('muctieunguoidung')) {
            Schema::table('muctieunguoidung', function (Blueprint $table) {
                if (!Schema::hasColumn('muctieunguoidung', 'NgayBatDau')) {
                    $table->date('NgayBatDau')->nullable()->after('NgayTrongTuan');
                }
                if (!Schema::hasColumn('muctieunguoidung', 'NgayKetThuc')) {
                    $table->date('NgayKetThuc')->nullable()->after('NgayBatDau');
                }
                if (!Schema::hasColumn('muctieunguoidung', 'TrangThai')) {
                    $table->string('TrangThai', 50)->default('DangTheoDoi')->after('NgayKetThuc');
                }
            });
        }

        if (!Schema::hasTable('muctieulichsu')) {
            Schema::create('muctieulichsu', function (Blueprint $table) {
                $table->id('ID');
                $table->unsignedBigInteger('NguoiDungID')->index();
                $table->unsignedBigInteger('MucTieuID')->nullable()->index();
                $table->string('Loai', 100)->index();
                $table->double('GiaTriCu')->nullable();
                $table->double('GiaTriMoi')->nullable();
                $table->string('DonVi', 50)->nullable();
                $table->date('NgayBatDau')->nullable();
                $table->date('NgayKetThuc')->nullable();
                $table->string('TrangThai', 50)->default('DangTheoDoi');
                $table->string('NguonThayDoi', 50)->default('User');
                $table->string('LyDo', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('muctieulichsu');

        if (Schema::hasTable('muctieunguoidung')) {
            Schema::table('muctieunguoidung', function (Blueprint $table) {
                if (Schema::hasColumn('muctieunguoidung', 'TrangThai')) {
                    $table->dropColumn('TrangThai');
                }
                if (Schema::hasColumn('muctieunguoidung', 'NgayKetThuc')) {
                    $table->dropColumn('NgayKetThuc');
                }
                if (Schema::hasColumn('muctieunguoidung', 'NgayBatDau')) {
                    $table->dropColumn('NgayBatDau');
                }
            });
        }
    }
};
