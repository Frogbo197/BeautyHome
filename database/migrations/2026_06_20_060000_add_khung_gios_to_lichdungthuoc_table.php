<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lichdungthuoc')
            || Schema::hasColumn('lichdungthuoc', 'khung_gios')) {
            return;
        }

        Schema::table('lichdungthuoc', function (Blueprint $table) {
            $table->text('khung_gios')->nullable()->after('TanSuat');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lichdungthuoc')
            || ! Schema::hasColumn('lichdungthuoc', 'khung_gios')) {
            return;
        }

        Schema::table('lichdungthuoc', function (Blueprint $table) {
            $table->dropColumn('khung_gios');
        });
    }
};
