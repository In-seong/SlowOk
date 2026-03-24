<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('screening_result', function (Blueprint $table) {
            $table->id('result_id');
            $table->foreignId('profile_id')->constrained('user_profile', 'profile_id')->onDelete('cascade');
            $table->foreignId('test_id')->constrained('screening_test', 'test_id')->onDelete('cascade');
            $table->integer('score')->default(0);
            $table->string('level', 50)->nullable();
            $table->json('analysis')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('screening_result'); }
};
