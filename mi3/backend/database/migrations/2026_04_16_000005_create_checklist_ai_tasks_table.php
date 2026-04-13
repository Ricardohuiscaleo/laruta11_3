<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE checklist_ai_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                contexto VARCHAR(100) NOT NULL,
                problema_detectado TEXT NOT NULL,
                foto_url_origen VARCHAR(500) NOT NULL,
                checklist_item_id_origen INT NOT NULL,
                foto_url_mejora VARCHAR(500) NULL,
                status ENUM('pendiente','mejorado','no_mejorado','escalado') NOT NULL DEFAULT 'pendiente',
                veces_detectado INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_contexto_status (contexto, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS checklist_ai_tasks");
    }
};
