<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();

            // Внешние ключи к мастерам, рефералам и платежам
            $table->foreignId('referrer_master_id')->constrained('masters')->cascadeOnDelete();
            $table->foreignId('referred_master_id')->constrained('masters')->cascadeOnDelete();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();

            // Денежные суммы (integer согласно $casts в модели)
            $table->integer('payment_amount');
            $table->integer('amount');
            $table->integer('percent');

            // Статус вознаграждения
            $table->string('status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
    }
};