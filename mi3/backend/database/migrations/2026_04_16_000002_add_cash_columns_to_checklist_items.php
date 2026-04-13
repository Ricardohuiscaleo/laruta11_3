<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE checklist_items ADD COLUMN item_type ENUM('standard','cash_verification') NOT NULL DEFAULT 'standard' AFTER description");
        DB::statement("ALTER TABLE checklist_items ADD COLUMN cash_expected DECIMAL(10,2) NULL AFTER item_type");
        DB::statement("ALTER TABLE checklist_items ADD COLUMN cash_actual DECIMAL(10,2) NULL AFTER cash_expected");
        DB::statement("ALTER TABLE checklist_items ADD COLUMN cash_difference DECIMAL(10,2) NULL AFTER cash_actual");
        DB::statement("ALTER TABLE checklist_items ADD COLUMN cash_result ENUM('ok','discrepancia') NULL AFTER cash_difference");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE checklist_items DROP COLUMN cash_result");
        DB::statement("ALTER TABLE checklist_items DROP COLUMN cash_difference");
        DB::statement("ALTER TABLE checklist_items DROP COLUMN cash_actual");
        DB::statement("ALTER TABLE checklist_items DROP COLUMN cash_expected");
        DB::statement("ALTER TABLE checklist_items DROP COLUMN item_type");
    }
};
