<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_alert_reviews')) {
            Schema::create('admin_alert_reviews', function (Blueprint $table) {
                $table->id('ID');
                $table->string('AlertKey', 190)->unique();
                $table->string('AlertType', 80)->index();
                $table->unsignedBigInteger('NguoiDungID')->nullable()->index();
                $table->string('Title', 255)->nullable();
                $table->boolean('IsRead')->default(true)->index();
                $table->string('Status', 50)->default('reviewed')->index();
                $table->unsignedBigInteger('ReviewedBy')->nullable()->index();
                $table->timestamp('ReviewedAt')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alert_reviews');
    }
};
