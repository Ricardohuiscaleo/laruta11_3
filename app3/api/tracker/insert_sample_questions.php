<?php
// Configuración de base de datos
$host = 'localhost';
$dbname = 'u958525313_Calcularuta11';
$username = 'u958525313_Calcularuta11';
$password = 'Calcularuta11*';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Insertar preguntas de ejemplo
    $questions = [
        ['cajero', 'yesno', '¿Tiene experiencia en manejo de dinero?'],
        ['cajero', 'yesno', '¿Puede trabajar en turnos rotativos?'],
        ['cajero', 'yesno', '¿Sabe usar caja registradora?'],
        ['cajero', 'open', '¿Cómo manejaría un cliente molesto?'],
        ['cajero', 'open', 'Describa su experiencia en atención al cliente'],
        
        ['maestro_sanguchero', 'yesno', '¿Tiene experiencia en cocina?'],
        ['maestro_sanguchero', 'yesno', '¿Puede trabajar bajo presión?'],
        ['maestro_sanguchero', 'yesno', '¿Conoce normas de higiene alimentaria?'],
        ['maestro_sanguchero', 'open', '¿Cuál es su especialidad culinaria?'],
        ['maestro_sanguchero', 'open', 'Describa su experiencia preparando sándwiches']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO interview_questions (position, question_type, question_text, question_order, is_active) VALUES (?, ?, ?, 0, 1)");
    
    foreach($questions as $q) {
        $stmt->execute($q);
    }
    
    echo "✅ Preguntas insertadas correctamente en la tabla interview_questions";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>