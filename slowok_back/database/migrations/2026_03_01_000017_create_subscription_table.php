<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription', function (Blueprint $table) {
            $table->id('subscription_id');
            $table->foreignId('account_id')->constrained('account', 'account_id')->onDelete('cascade');
            $table->string('plan_type', 50)->default('FREE');
            $table->enum('status', ['ACTIVE', 'EXPIRED', 'CANCELLED'])->default('ACTIVE');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('subscription'); }
};
