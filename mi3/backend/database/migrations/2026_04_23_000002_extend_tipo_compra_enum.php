<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE compras
            MODIFY COLUMN tipo_compra ENUM('ingredientes','insumos','equipamiento','otros','gas','limpieza','packaging','servicios')
            DEFAULT 'ingredientes'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE compras
            MODIFY COLUMN tipo_compra ENUM('ingredientes','insumos','equipamiento','otros')
            DEFAULT 'ingredientes'
        ");
    }
};
