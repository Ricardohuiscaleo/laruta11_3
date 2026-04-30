<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tuu_orders', 'card_surcharge')) {
            return;
        }

        Schema::table('tuu_orders', function (Blueprint $table) {
            $table->decimal('card_surcharge', 10, 2)
                ->default(0.00)
                ->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('tuu_orders', function (Blueprint $table) {
            $table->dropColumn('card_surcharge');
        });
    }
};
