<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('screening_question', function (Blueprint $table) {
            $table->id('question_id');
            $table->foreignId('test_id')->constrained('screening_test', 'test_id')->onDelete('cascade');
            $table->text('content');
            $table->string('question_type', 50)->default('multiple_choice');
            $table->json('options')->nullable();
            $table->string('correct_answer')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('screening_question'); }
};
