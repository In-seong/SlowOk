<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('challenge_attempt', function (Blueprint $table) {
            $table->id('attempt_id');
            $table->foreignId('profile_id')->constrained('user_profile', 'profile_id')->onDelete('cascade');
            $table->foreignId('challenge_id')->constrained('challenge', 'challenge_id')->onDelete('cascade');
            $table->integer('score')->default(0);
            $table->boolean('is_passed')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('challenge_attempt'); }
};
