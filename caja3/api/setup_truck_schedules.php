<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear tabla de horarios por día
    $sql = "CREATE TABLE IF NOT EXISTS truck_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        truck_id INT NOT NULL,
        day_of_week TINYINT NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado',
        is_open TINYINT(1) DEFAULT 1,
        open_time TIME NOT NULL,
        close_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (truck_id) REFERENCES food_trucks(id) ON DELETE CASCADE,
        UNIQUE KEY unique_truck_day (truck_id, day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);

    // Insertar horarios por defecto para truck_id = 4 (basado en horarios actuales)
    $defaultSchedules = [
        ['truck_id' => 4, 'day_of_week' => 0, 'is_open' => 0, 'open_time' => '18:00:00', 'close_time' => '00:30:00'], // Domingo cerrado
        ['truck_id' => 4, 'day_of_week' => 1, 'is_open' => 1, 'open_time' => '18:00:00', 'close_time' => '00:30:00'], // Lunes
        ['truck_id' => 4, 'day_of_week' => 2, 'is_open' => 1, 'open_time' => '18:00:00', 'close_time' => '00:30:00'], // Martes
        ['truck_id' => 4, 'day_of_week' => 3, 'is_open' => 1, 'open_time' => '18:00:00', 'close_time' => '00:30:00'], // Miércoles
        ['truck_id' => 4, 'day_of_week' => 4, 'is_open' => 1, 'open_time' => '18:00:00', 'close_time' => '00:30:00'], // Jueves
        ['truck_id' => 4, 'day_of_week' => 5, 'is_open' => 1, 'open_time' => '18:00:00', 'close_time' => '00:30:00'], // Viernes
        ['truck_id' => 4, 'day_of_week' => 6, 'is_open' => 1, 'open_time' => '18:00:00', 'close_time' => '00:30:00'], // Sábado
    ];

    $stmt = $pdo->prepare("
        INSERT INTO truck_schedules (truck_id, day_of_week, is_open, open_time, close_time) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            is_open = VALUES(is_open),
            open_time = VALUES(open_time),
            close_time = VALUES(close_time)
    ");

    foreach ($defaultSchedules as $schedule) {
        $stmt->execute([
            $schedule['truck_id'],
            $schedule['day_of_week'],
            $schedule['is_open'],
            $schedule['open_time'],
            $schedule['close_time']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tabla truck_schedules creada y horarios inicializados correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
