<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_settlements', function (Blueprint $table) {
            $table->increments('id');
            $table->date('settlement_date')->unique();
            $table->integer('total_orders_delivered')->default(0);
            $table->decimal('total_delivery_fees', 10, 2)->default(0);
            $table->json('settlement_data')->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->string('payment_voucher_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->integer('paid_by')->unsigned()->nullable();
            $table->integer('compra_id')->unsigned()->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('settlement_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_settlements');
    }
};
