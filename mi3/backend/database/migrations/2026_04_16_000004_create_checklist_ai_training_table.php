<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE checklist_ai_training (
                id INT AUTO_INCREMENT PRIMARY KEY,
                checklist_item_id INT NULL,
                photo_url VARCHAR(500) NOT NULL,
                contexto VARCHAR(100) NOT NULL,
                ai_score INT NULL,
                ai_observations TEXT NULL,
                admin_feedback ENUM('correct','incorrect') NULL,
                admin_notes TEXT NULL,
                admin_score INT NULL,
                prompt_used TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_contexto (contexto),
                INDEX idx_feedback (admin_feedback),
                INDEX idx_contexto_feedback (contexto, admin_feedback)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS checklist_ai_training");
    }
};
