<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates base tables that already exist in production MySQL (laruta11).
 * Needed for SQLite in-memory testing with RefreshDatabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->string('rol')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('rut')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->float('sueldo_base_cajero')->default(0);
            $table->float('sueldo_base_planchero')->default(0);
            $table->float('sueldo_base_admin')->default(0);
            $table->float('sueldo_base_seguridad')->default(0);
            $table->boolean('activo')->default(true);
        });

        Schema::create('turnos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('personal_id');
            $table->date('fecha');
            $table->string('tipo')->default('normal');
            $table->unsignedInteger('reemplazado_por')->nullable();
            $table->float('monto_reemplazo')->nullable();
            $table->string('pago_por')->nullable();
            $table->foreign('personal_id')->references('id')->on('personal');
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->time('scheduled_time')->nullable();
            $table->date('scheduled_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->unsignedInteger('personal_id')->nullable();
            $table->string('rol')->nullable();
            $table->string('checklist_mode')->default('presencial');
            $table->integer('total_items')->default(0);
            $table->integer('completed_items')->default(0);
            $table->float('completion_percentage')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['personal_id', 'scheduled_date']);
            $table->index(['rol', 'scheduled_date']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('checklist_id');
            $table->integer('item_order');
            $table->string('description');
            $table->boolean('requires_photo')->default(false);
            $table->string('photo_url')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->integer('ai_score')->nullable();
            $table->text('ai_observations')->nullable();
            $table->timestamp('ai_analyzed_at')->nullable();
            $table->foreign('checklist_id')->references('id')->on('checklists');
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->string('rol')->nullable();
            $table->integer('item_order');
            $table->text('description');
            $table->boolean('requires_photo')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->index(['type', 'rol']);
        });

        Schema::create('checklist_virtual', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('checklist_id');
            $table->unsignedInteger('personal_id');
            $table->text('confirmation_text')->nullable();
            $table->text('improvement_idea')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreign('checklist_id')->references('id')->on('checklists');
            $table->foreign('personal_id')->references('id')->on('personal');
        });

        Schema::create('ajustes_categorias', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('icono')->nullable();
            $table->string('color')->nullable();
            $table->string('signo_defecto')->nullable();
        });

        Schema::create('ajustes_sueldo', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('personal_id');
            $table->date('mes');
            $table->float('monto');
            $table->string('concepto');
            $table->unsignedInteger('categoria_id')->nullable();
            $table->text('notas')->nullable();
            $table->foreign('personal_id')->references('id')->on('personal');
            $table->foreign('categoria_id')->references('id')->on('ajustes_categorias');
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_sueldo');
        Schema::dropIfExists('ajustes_categorias');
        Schema::dropIfExists('checklist_virtual');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('turnos');
        Schema::dropIfExists('personal');
        Schema::dropIfExists('usuarios');
    }
};
