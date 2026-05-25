<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_history', function (Blueprint $table) {

            $table->id('ID');

            $table->unsignedBigInteger('NguoiDungID');

            $table->string('SessionID', 64)
                  ->default('default');

            $table->text('UserMessage');

            $table->text('BotReply');

            $table->string('Model', 50)
                  ->nullable();

            $table->timestamp('ThoiGian')
                  ->useCurrent();

            $table->timestamps();

            $table->foreign('NguoiDungID')
                  ->references('ID')
                  ->on('taikhoan')
                  ->onDelete('cascade');

            $table->index([
                'NguoiDungID',
                'SessionID'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_history');
    }
};