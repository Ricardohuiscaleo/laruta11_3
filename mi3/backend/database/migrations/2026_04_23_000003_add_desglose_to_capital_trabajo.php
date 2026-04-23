<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capital_trabajo', function (Blueprint $table) {
            $table->json('desglose_ingresos')->nullable()->after('ingresos_ventas');
            $table->json('desglose_gastos')->nullable()->after('egresos_gastos');
        });
    }

    public function down(): void
    {
        Schema::table('capital_trabajo', function (Blueprint $table) {
            $table->dropColumn(['desglose_ingresos', 'desglose_gastos']);
        });
    }
};
