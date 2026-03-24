<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reward_card', function (Blueprint $table) {
            $table->id('card_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('rarity', 50)->default('COMMON');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reward_card'); }
};
