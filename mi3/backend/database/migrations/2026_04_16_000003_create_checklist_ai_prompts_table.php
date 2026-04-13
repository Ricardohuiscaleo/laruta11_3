<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE checklist_ai_prompts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                contexto VARCHAR(100) NOT NULL,
                prompt_base TEXT NOT NULL,
                prompt_version INT NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_contexto_active (contexto, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS checklist_ai_prompts");
    }
};
