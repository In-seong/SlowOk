<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('institution', function (Blueprint $table) {
            $table->id('institution_id');
            $table->string('name', 200);
            $table->string('type', 50)->nullable();
            $table->json('contact_info')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('institution'); }
};
