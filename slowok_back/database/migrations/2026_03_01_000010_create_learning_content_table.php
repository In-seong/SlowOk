<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_content', function (Blueprint $table) {
            $table->id('content_id');
            $table->foreignId('category_id')->constrained('learning_category', 'category_id')->onDelete('cascade');
            $table->string('title', 200);
            $table->enum('content_type', ['VIDEO', 'QUIZ', 'GAME', 'READING'])->default('READING');
            $table->json('content_data')->nullable();
            $table->integer('difficulty_level')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('learning_content'); }
};
