<?php
// Configuración de base de datos
$host = 'localhost';
$dbname = 'u958525313_Calcularuta11';
$username = 'u958525313_Calcularuta11';
$password = 'tu_password_aqui'; // Cambiar por tu password real

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tabla
    $sql = "CREATE TABLE IF NOT EXISTS interview_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        position VARCHAR(50) NOT NULL,
        question_type ENUM('yesno', 'open') NOT NULL,
        question_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    // Insertar datos de ejemplo
    $questions = [
        ['cajero', 'yesno', '¿Tiene experiencia en manejo de dinero?'],
        ['cajero', 'yesno', '¿Puede trabajar en turnos rotativos?'],
        ['cajero', 'open', '¿Cómo manejaría un cliente molesto?'],
        ['cajero', 'open', 'Describa su experiencia en atención al cliente'],
        ['maestro_sanguchero', 'yesno', '¿Tiene experiencia en cocina?'],
        ['maestro_sanguchero', 'yesno', '¿Puede trabajar bajo presión?'],
        ['maestro_sanguchero', 'open', '¿Cuál es su especialidad culinaria?'],
        ['maestro_sanguchero', 'open', 'Describa su experiencia preparando sándwiches']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO interview_questions (position, question_type, question_text) VALUES (?, ?, ?)");
    
    foreach($questions as $q) {
        $stmt->execute($q);
    }
    
    echo "✅ Tabla creada y datos insertados correctamente";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>