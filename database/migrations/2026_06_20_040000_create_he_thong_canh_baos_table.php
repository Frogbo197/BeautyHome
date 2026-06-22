<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('he_thong_canh_baos')) {
            Schema::create('he_thong_canh_baos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('alert_key', 190)->unique();
                $table->string('loai_canh_bao', 100)->index();
                $table->text('noi_dung_chi_tiet');
                $table->string('muc_do_nguy_hiem', 20)->index();
                $table->enum('status', ['pending', 'reviewed'])->default('pending')->index();
                $table->json('metadata')->nullable();
                $table->timestamp('detected_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('he_thong_canh_baos');
    }
};
