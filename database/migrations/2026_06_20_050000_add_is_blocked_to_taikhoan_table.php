<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taikhoan') || Schema::hasColumn('taikhoan', 'is_blocked')) {
            return;
        }

        Schema::table('taikhoan', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->index();
        });

        if (Schema::hasColumn('taikhoan', 'TrangThaiHoatDong')) {
            DB::table('taikhoan')
                ->where('TrangThaiHoatDong', 0)
                ->update(['is_blocked' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('taikhoan') || ! Schema::hasColumn('taikhoan', 'is_blocked')) {
            return;
        }

        Schema::table('taikhoan', function (Blueprint $table) {
            $table->dropColumn('is_blocked');
        });
    }
};
