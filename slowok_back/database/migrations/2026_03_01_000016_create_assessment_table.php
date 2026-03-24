<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment', function (Blueprint $table) {
            $table->id('assessment_id');
            $table->foreignId('profile_id')->constrained('user_profile', 'profile_id')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('learning_category', 'category_id')->onDelete('cascade');
            $table->string('type', 50)->default('PERIODIC');
            $table->integer('score')->default(0);
            $table->json('feedback')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('assessment'); }
};
