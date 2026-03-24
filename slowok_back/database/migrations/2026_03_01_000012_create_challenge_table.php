<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('challenge', function (Blueprint $table) {
            $table->id('challenge_id');
            $table->foreignId('category_id')->constrained('learning_category', 'category_id')->onDelete('cascade');
            $table->string('title', 200);
            $table->string('challenge_type', 50)->default('DAILY');
            $table->integer('difficulty_level')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('challenge'); }
};
