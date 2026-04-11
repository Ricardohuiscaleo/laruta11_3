<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions_mi3', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('personal_id');
            $table->json('subscription');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('personal_id', 'idx_personal');
            $table->index('is_active', 'idx_active');

            $table->foreign('personal_id')->references('id')->on('personal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions_mi3');
    }
};
