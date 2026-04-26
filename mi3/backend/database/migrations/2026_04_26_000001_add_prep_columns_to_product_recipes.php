<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->string('prep_method', 50)->nullable()->after('unit');
            $table->unsignedSmallInteger('prep_time_seconds')->default(0)->after('prep_method');
            $table->boolean('is_prepped')->default(false)->after('prep_time_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->dropColumn(['prep_method', 'prep_time_seconds', 'is_prepped']);
        });
    }
};
