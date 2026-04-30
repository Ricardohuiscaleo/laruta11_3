<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_config')) {
            return;
        }

        Schema::create('delivery_config', function (Blueprint $table) {
            $table->id();
            $table->string('config_key', 50)->unique();
            $table->string('config_value', 255);
            $table->string('description', 255)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });

        $now = now()->format('Y-m-d H:i:s');

        DB::table('delivery_config')->insert([
            [
                'config_key' => 'tarifa_base',
                'config_value' => '3500',
                'description' => 'Tarifa base de delivery en pesos',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'config_key' => 'card_surcharge',
                'config_value' => '500',
                'description' => 'Recargo por pago con tarjeta en delivery',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'config_key' => 'distance_threshold_km',
                'config_value' => '6',
                'description' => 'Distancia en km sin recargo adicional',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'config_key' => 'surcharge_per_bracket',
                'config_value' => '1000',
                'description' => 'Recargo por cada tramo adicional de distancia',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'config_key' => 'bracket_size_km',
                'config_value' => '2',
                'description' => 'Tamaño en km de cada tramo de distancia',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'config_key' => 'rl6_discount_factor',
                'config_value' => '0.2857',
                'description' => 'Factor de descuento RL6 (0.2857 = 28.57%)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_config');
    }
};
