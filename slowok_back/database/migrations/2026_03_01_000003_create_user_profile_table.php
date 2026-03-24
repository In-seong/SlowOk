<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_profile', function (Blueprint $table) {
            $table->id('profile_id');
            $table->foreignId('account_id')->constrained('account', 'account_id')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('user_type', ['LEARNER', 'PARENT'])->default('LEARNER');
            $table->unsignedBigInteger('parent_profile_id')->nullable();
            $table->timestamps();
            $table->foreign('parent_profile_id')->references('profile_id')->on('user_profile')->onDelete('set null');
        });
    }
    public function down(): void { Schema::dropIfExists('user_profile'); }
};
