<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('checklist_items', 'ai_score')) {
            return; // Already applied (base migration includes these columns)
        }

        Schema::table('checklist_items', function (Blueprint $table) {
            $table->integer('ai_score')->nullable()->after('notes');
            $table->text('ai_observations')->nullable()->after('ai_score');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_observations');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropColumn(['ai_score', 'ai_observations', 'ai_analyzed_at']);
        });
    }
};
