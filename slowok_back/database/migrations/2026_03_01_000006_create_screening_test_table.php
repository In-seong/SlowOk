<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('screening_test', function (Blueprint $table) {
            $table->id('test_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('learning_category', 'category_id')->onDelete('cascade');
            $table->integer('question_count')->default(0);
            $table->integer('time_limit')->nullable()->comment('minutes');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('screening_test'); }
};
