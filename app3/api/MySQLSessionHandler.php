<?php
// MySQL Session Handler
class MySQLSessionHandler implements SessionHandlerInterface {
    private $conn;
    private $table = 'php_sessions';
    
    public function __construct($db_config) {
        $this->conn = mysqli_connect(
            $db_config['app_db_host'],
            $db_config['app_db_user'],
            $db_config['app_db_pass'],
            $db_config['app_db_name']
        );
        
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
        
        mysqli_set_charset($this->conn, 'utf8mb4');
    }
    
    public function open($save_path, $session_name): bool {
        return true;
    }
    
    public function close(): bool {
        return true;
    }
    
    public function read($session_id): string|false {
        $stmt = mysqli_prepare($this->conn, "SELECT session_data FROM {$this->table} WHERE session_id = ?");
        mysqli_stmt_bind_param($stmt, 's', $session_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['session_data'];
        }
        
        return '';
    }
    
    public function write($session_id, $session_data): bool {
        $stmt = mysqli_prepare($this->conn, 
            "INSERT INTO {$this->table} (session_id, session_data) VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE session_data = ?, last_activity = CURRENT_TIMESTAMP"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $session_id, $session_data, $session_data);
        return mysqli_stmt_execute($stmt);
    }
    
    public function destroy($session_id): bool {
        $stmt = mysqli_prepare($this->conn, "DELETE FROM {$this->table} WHERE session_id = ?");
        mysqli_stmt_bind_param($stmt, 's', $session_id);
        return mysqli_stmt_execute($stmt);
    }
    
    public function gc($maxlifetime): int|false {
        $stmt = mysqli_prepare($this->conn, 
            "DELETE FROM {$this->table} WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        mysqli_stmt_bind_param($stmt, 'i', $maxlifetime);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_affected_rows($stmt);
    }
}
?>
