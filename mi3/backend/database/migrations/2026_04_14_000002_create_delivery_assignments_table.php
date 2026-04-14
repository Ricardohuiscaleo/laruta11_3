<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned();
            $table->integer('rider_id')->unsigned();
            $table->integer('assigned_by')->unsigned();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->enum('status', ['assigned', 'picked_up', 'delivered', 'cancelled'])->default('assigned');
            $table->string('notes', 255)->nullable();

            $table->index('order_id');
            $table->index(['rider_id', 'status']);
            // NO foreign key constraints (tablas externas no gestionadas por Laravel migrations)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_assignments');
    }
};
