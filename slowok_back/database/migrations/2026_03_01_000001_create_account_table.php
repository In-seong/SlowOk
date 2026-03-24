<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account', function (Blueprint $table) {
            $table->id('account_id');
            $table->string('username', 50)->unique();
            $table->string('password_hash');
            $table->enum('role', ['USER', 'ADMIN'])->default('USER');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('account'); }
};
