<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_report', function (Blueprint $table) {
            $table->id('report_id');
            $table->foreignId('profile_id')->constrained('user_profile', 'profile_id')->onDelete('cascade');
            $table->string('period', 50);
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('learning_report'); }
};
