<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_category', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('learning_category'); }
};
