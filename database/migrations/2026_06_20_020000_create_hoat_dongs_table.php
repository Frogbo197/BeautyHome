<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hoat_dongs')) {
            return;
        }

        Schema::create('hoat_dongs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ten_hoat_dong')->unique();
            $table->text('mo_ta')->nullable();
            $table->double('chi_so_met');
            $table->string('hinh_anh_icon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hoat_dongs');
    }
};
