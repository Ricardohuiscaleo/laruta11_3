<?php
function get_gmail_token_from_db($config) {
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->query("SELECT access_token FROM gmail_tokens ORDER BY updated_at DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $row['access_token'] : null;
        
    } catch (Exception $e) {
        error_log("Error getting Gmail token: " . $e->getMessage());
        return null;
    }
}
