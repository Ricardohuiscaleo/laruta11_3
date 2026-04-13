<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE checklist_templates ADD COLUMN item_type ENUM('standard','cash_verification') NOT NULL DEFAULT 'standard' AFTER description");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE checklist_templates DROP COLUMN item_type");
    }
};
