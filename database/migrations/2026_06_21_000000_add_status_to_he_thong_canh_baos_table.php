<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('he_thong_canh_baos') || Schema::hasColumn('he_thong_canh_baos', 'status')) {
            return;
        }

        Schema::table('he_thong_canh_baos', function (Blueprint $table) {
            $table->enum('status', ['pending', 'reviewed'])
                ->default('pending')
                ->index()
                ->after('muc_do_nguy_hiem');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('he_thong_canh_baos') || ! Schema::hasColumn('he_thong_canh_baos', 'status')) {
            return;
        }

        Schema::table('he_thong_canh_baos', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
