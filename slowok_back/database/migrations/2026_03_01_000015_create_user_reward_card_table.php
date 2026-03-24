<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_reward_card', function (Blueprint $table) {
            $table->foreignId('profile_id')->constrained('user_profile', 'profile_id')->onDelete('cascade');
            $table->foreignId('card_id')->constrained('reward_card', 'card_id')->onDelete('cascade');
            $table->timestamp('earned_at')->useCurrent();
            $table->primary(['profile_id', 'card_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_reward_card'); }
};
