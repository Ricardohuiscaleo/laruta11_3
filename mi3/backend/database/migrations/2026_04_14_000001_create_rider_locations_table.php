<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('rider_id')->unsigned()->index();
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->integer('precision_metros')->default(0);
            $table->decimal('velocidad_kmh', 5, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['rider_id', 'created_at']);
            // NO foreign key constraint (tabla personal no es Laravel migration)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_locations');
    }
};
