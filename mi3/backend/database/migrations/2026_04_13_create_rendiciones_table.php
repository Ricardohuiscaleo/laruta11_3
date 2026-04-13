<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendiciones', function (Blueprint $table) {
            $table->id();
            $table->string('token', 20)->unique();
            $table->decimal('saldo_anterior', 12, 2)->default(0);
            $table->decimal('total_compras', 12, 2)->default(0);
            $table->decimal('saldo_resultante', 12, 2)->default(0); // saldo_anterior - total_compras
            $table->decimal('monto_transferido', 12, 2)->nullable(); // lo que el dueño transfiere
            $table->decimal('saldo_nuevo', 12, 2)->nullable(); // saldo para la próxima rendición
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->string('creado_por', 100)->nullable(); // Ricardo
            $table->string('aprobado_por', 100)->nullable(); // Yojhans
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamps();
        });

        // Add rendicion_id to compras
        Schema::table('compras', function (Blueprint $table) {
            $table->unsignedBigInteger('rendicion_id')->nullable()->after('usuario');
            $table->index('rendicion_id');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex(['rendicion_id']);
            $table->dropColumn('rendicion_id');
        });
        Schema::dropIfExists('rendiciones');
    }
};
