<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('checklists', 'personal_id')) {
            return; // Already applied (base migration includes these columns)
        }

        Schema::table('checklists', function (Blueprint $table) {
            $table->integer('personal_id')->nullable()->after('user_name');
            $table->enum('rol', ['cajero', 'planchero'])->nullable()->after('personal_id');
            $table->enum('checklist_mode', ['presencial', 'virtual'])->default('presencial')->after('rol');

            $table->index(['personal_id', 'scheduled_date'], 'idx_personal_date');
            $table->index(['rol', 'scheduled_date'], 'idx_rol_date');

            $table->foreign('personal_id', 'fk_checklists_personal')
                  ->references('id')
                  ->on('personal');
        });
    }

    public function down(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->dropForeign('fk_checklists_personal');
            $table->dropIndex('idx_personal_date');
            $table->dropIndex('idx_rol_date');
            $table->dropColumn(['personal_id', 'rol', 'checklist_mode']);
        });
    }
};
