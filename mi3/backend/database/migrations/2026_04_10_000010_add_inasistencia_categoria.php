<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ajustes_categorias')->insertOrIgnore([
            'nombre' => 'Inasistencia',
            'slug' => 'inasistencia',
            'icono' => '❌',
            'color' => '#ef4444',
            'signo_defecto' => '-',
        ]);
    }

    public function down(): void
    {
        DB::table('ajustes_categorias')
            ->where('slug', 'inasistencia')
            ->delete();
    }
};
