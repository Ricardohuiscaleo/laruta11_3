<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ajustes_categorias')->insertOrIgnore([
            'nombre' => 'Descuento Crédito R11',
            'slug' => 'descuento_credito_r11',
            'icono' => '💳',
        ]);
    }

    public function down(): void
    {
        DB::table('ajustes_categorias')
            ->where('slug', 'descuento_credito_r11')
            ->delete();
    }
};
