<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomina_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('token', 16)->unique();
            $table->string('mes', 7); // YYYY-MM
            $table->json('data');
            $table->timestamps();

            $table->index('mes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_snapshots');
    }
};
