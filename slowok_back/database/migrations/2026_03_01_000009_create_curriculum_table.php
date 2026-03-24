<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('curriculum', function (Blueprint $table) {
            $table->id('curriculum_id');
            $table->foreignId('profile_id')->constrained('user_profile', 'profile_id')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('learning_category', 'category_id')->onDelete('cascade');
            $table->string('current_level', 50)->nullable();
            $table->json('recommended_path')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('curriculum'); }
};
