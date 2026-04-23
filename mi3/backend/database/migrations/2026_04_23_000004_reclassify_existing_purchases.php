<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Gas: proveedor Abastible
        DB::statement("
            UPDATE compras
            SET tipo_compra = 'gas'
            WHERE tipo_compra IN ('otros', 'insumos')
            AND LOWER(proveedor) LIKE '%abastible%'
        ");

        // Limpieza: ingredientes categoría Limpieza
        DB::statement("
            UPDATE compras c
            JOIN compras_detalle cd ON c.id = cd.compra_id
            JOIN ingredients i ON cd.ingrediente_id = i.id
            SET c.tipo_compra = 'limpieza'
            WHERE c.tipo_compra IN ('otros', 'insumos')
            AND i.category = 'Limpieza'
        ");

        // Packaging: ingredientes categoría Packaging
        DB::statement("
            UPDATE compras c
            JOIN compras_detalle cd ON c.id = cd.compra_id
            JOIN ingredients i ON cd.ingrediente_id = i.id
            SET c.tipo_compra = 'packaging'
            WHERE c.tipo_compra IN ('otros', 'insumos')
            AND i.category = 'Packaging'
        ");

        // Servicios: ingredientes categoría Servicios
        DB::statement("
            UPDATE compras c
            JOIN compras_detalle cd ON c.id = cd.compra_id
            JOIN ingredients i ON cd.ingrediente_id = i.id
            SET c.tipo_compra = 'servicios'
            WHERE c.tipo_compra IN ('otros', 'insumos')
            AND i.category = 'Servicios'
        ");
    }

    public function down(): void
    {
        // Revert gas back to otros
        DB::statement("
            UPDATE compras
            SET tipo_compra = 'otros'
            WHERE tipo_compra = 'gas'
            AND LOWER(proveedor) LIKE '%abastible%'
        ");

        // Revert limpieza back to otros
        DB::statement("
            UPDATE compras c
            JOIN compras_detalle cd ON c.id = cd.compra_id
            JOIN ingredients i ON cd.ingrediente_id = i.id
            SET c.tipo_compra = 'otros'
            WHERE c.tipo_compra = 'limpieza'
            AND i.category = 'Limpieza'
        ");

        // Revert packaging back to otros
        DB::statement("
            UPDATE compras c
            JOIN compras_detalle cd ON c.id = cd.compra_id
            JOIN ingredients i ON cd.ingrediente_id = i.id
            SET c.tipo_compra = 'otros'
            WHERE c.tipo_compra = 'packaging'
            AND i.category = 'Packaging'
        ");

        // Revert servicios back to otros
        DB::statement("
            UPDATE compras c
            JOIN compras_detalle cd ON c.id = cd.compra_id
            JOIN ingredients i ON cd.ingrediente_id = i.id
            SET c.tipo_compra = 'otros'
            WHERE c.tipo_compra = 'servicios'
            AND i.category = 'Servicios'
        ");
    }
};
