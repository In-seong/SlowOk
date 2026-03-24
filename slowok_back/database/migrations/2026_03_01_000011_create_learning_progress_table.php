<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_progress', function (Blueprint $table) {
            $table->id('progress_id');
            $table->foreignId('profile_id')->constrained('user_profile', 'profile_id')->onDelete('cascade');
            $table->foreignId('content_id')->constrained('learning_content', 'content_id')->onDelete('cascade');
            $table->enum('status', ['NOT_STARTED', 'IN_PROGRESS', 'COMPLETED'])->default('NOT_STARTED');
            $table->integer('score')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('learning_progress'); }
};
