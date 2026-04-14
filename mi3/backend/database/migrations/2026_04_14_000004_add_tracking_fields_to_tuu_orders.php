<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuu_orders', function (Blueprint $table) {
            $table->string('tracking_url', 255)->nullable()->after('delivery_address');
            $table->decimal('rider_last_lat', 10, 8)->nullable()->after('rider_id');
            $table->decimal('rider_last_lng', 11, 8)->nullable()->after('rider_last_lat');
        });
    }

    public function down(): void
    {
        Schema::table('tuu_orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_url', 'rider_last_lat', 'rider_last_lng']);
        });
    }
};
