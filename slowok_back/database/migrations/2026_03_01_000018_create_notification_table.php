<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('account_id')->constrained('account', 'account_id')->onDelete('cascade');
            $table->string('title', 200);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('notification'); }
};
