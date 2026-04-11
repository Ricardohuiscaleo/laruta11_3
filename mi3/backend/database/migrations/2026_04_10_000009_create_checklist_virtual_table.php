<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('checklist_virtual')) {
            return; // Already created by base migration
        }

        Schema::create('checklist_virtual', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('checklist_id');
            $table->unsignedInteger('personal_id');
            $table->text('confirmation_text')->nullable();
            $table->text('improvement_idea');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('personal_id', 'idx_personal_date');
            $table->index('checklist_id', 'idx_checklist');

            $table->foreign('checklist_id', 'fk_cv_checklist')
                  ->references('id')
                  ->on('checklists');
            $table->foreign('personal_id', 'fk_cv_personal')
                  ->references('id')
                  ->on('personal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_virtual');
    }
};
