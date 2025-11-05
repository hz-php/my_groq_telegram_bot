<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slot_players', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id')->nullable()->unique();
            $table->integer('balance')->default(1000); // стартовый капитал
            $table->integer('bonus')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_players');
    }
};
