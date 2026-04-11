<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('checklist_templates', 'rol')) {
            return; // Already applied (base migration includes this column)
        }

        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->enum('rol', ['cajero', 'planchero'])->nullable()->after('type');
            $table->index(['type', 'rol'], 'idx_type_rol');
        });

        // Assign rol to existing templates based on description keywords
        // cajero: PedidosYa, TUU, saldo
        DB::table('checklist_templates')
            ->where(function ($q) {
                $q->where('description', 'LIKE', '%PedidosYa%')
                  ->orWhere('description', 'LIKE', '%TUU%')
                  ->orWhere('description', 'LIKE', '%saldo%');
            })
            ->update(['rol' => 'cajero']);

        // planchero: aderezos, salsas, gas
        DB::table('checklist_templates')
            ->where(function ($q) {
                $q->where('description', 'LIKE', '%aderezos%')
                  ->orWhere('description', 'LIKE', '%salsas%')
                  ->orWhere('description', 'LIKE', '%gas%')
                  ->orWhere('description', 'LIKE', '%limpiar%')
                  ->orWhere('description', 'LIKE', '%Guardar%');
            })
            ->update(['rol' => 'planchero']);

        // fotos: interior → cajero, exterior → planchero
        DB::table('checklist_templates')
            ->where('description', 'LIKE', '%FOTO interior%')
            ->update(['rol' => 'cajero']);

        DB::table('checklist_templates')
            ->where('description', 'LIKE', '%FOTO exterior%')
            ->update(['rol' => 'planchero']);
    }

    public function down(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->dropIndex('idx_type_rol');
            $table->dropColumn('rol');
        });
    }
};
