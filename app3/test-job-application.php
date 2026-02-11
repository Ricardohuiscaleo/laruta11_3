<?php
// Script para generar postulación demo y verificar datos

require_once 'api/config.php';

// Datos demo para postulación
$demoData = [
    'user_id' => '101326734074221937133', // Ricardo Huiscaleo
    'position' => 'cajero', // Cambiar a 'maestro_sanguchero' para probar
    'nombre' => 'Ricardo Huiscaleo Demo',
    'telefono' => '+56912345678',
    'instagram' => 'ricardo_demo',
    'nacionalidad' => 'chile',
    'genero' => 'masculino',
    'requisitos_legales' => ['mayor_edad', 'cedula_vigente', 'permiso_trabajo'],
    'curso_cajero' => 'si',
    'answers' => [
        [
            'question' => '1. Apertura y Organización',
            'answer' => 'Primero verificaría si hay algún sistema de respaldo o caja manual. Informaría a los clientes sobre la situación con transparencia y les ofrecería alternativas como pago en efectivo. Me coordinaría con el maestro sanguchero para priorizar los pedidos más simples y mantener el flujo de trabajo. Implementaría un sistema temporal de anotación manual para llevar el control de las ventas.',
            'time_spent' => 120
        ],
        [
            'question' => '2. Atención al Cliente Difícil',
            'answer' => 'Mantendría la calma y escucharía activamente al cliente. Le pediría disculpas por la experiencia negativa y le explicaría que aunque no tenga el ticket, valoramos su feedback. Le ofrecería una solución inmediata como preparar un nuevo sándwich sin costo o un descuento en su próxima compra. Informaría discretamente al maestro sanguchero sobre el problema para evitar que se repita.',
            'time_spent' => 150
        ],
        [
            'question' => '3. Cierre y Mejora del Servicio',
            'answer' => 'Para el cierre, verificaría el cuadre de caja, limpiaría el área de trabajo y organizaría el inventario para el día siguiente. Basándome en las observaciones, propondría: 1) Implementar descuentos estudiantiles con verificación de credencial, y 2) Crear un menú básico en inglés con los platos principales y precios para turistas, mejorando así la experiencia del cliente.',
            'time_spent' => 180
        ]
    ],
    'detected_skills' => [
        'comunicacion' => ['count' => 3, 'label' => 'Comunicación Efectiva'],
        'organizacion' => ['count' => 2, 'label' => 'Organización'],
        'atencion_cliente' => ['count' => 4, 'label' => 'Atención al Cliente'],
        'resolucion_problemas' => ['count' => 2, 'label' => 'Resolución de Problemas']
    ],
    'final_score' => 78
];

try {
    // 1. Crear aplicación
    $stmt = $pdo->prepare("
        INSERT INTO jobs_applications (
            user_id, position, nombre, telefono, instagram, nacionalidad, genero,
            requisitos_legales, curso_cajero, curso_manipulador, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'started', NOW())
    ");
    
    $stmt->execute([
        $demoData['user_id'],
        $demoData['position'],
        $demoData['nombre'],
        $demoData['telefono'],
        $demoData['instagram'],
        $demoData['nacionalidad'],
        $demoData['genero'],
        json_encode($demoData['requisitos_legales']),
        $demoData['curso_cajero'] ?? null,
        null // curso_manipulador
    ]);
    
    $applicationId = $pdo->lastInsertId();
    echo "✅ Aplicación creada con ID: $applicationId\n";
    
    // 2. Guardar respuestas
    foreach ($demoData['answers'] as $index => $answer) {
        $stmt = $pdo->prepare("
            INSERT INTO jobs_answers (application_id, question_number, question_text, answer_text, time_spent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $applicationId,
            $index + 1,
            $answer['question'],
            $answer['answer'],
            $answer['time_spent']
        ]);
    }
    echo "✅ Respuestas guardadas\n";
    
    // 3. Completar aplicación con skills
    $stmt = $pdo->prepare("
        UPDATE jobs_applications 
        SET status = 'completed', 
            score = ?, 
            detected_skills = ?,
            completed_at = NOW(),
            time_elapsed = 451
        WHERE id = ?
    ");
    
    $stmt->execute([
        $demoData['final_score'],
        json_encode($demoData['detected_skills']),
        $applicationId
    ]);
    echo "✅ Aplicación completada con score: {$demoData['final_score']}%\n";
    
    // 4. Verificar datos guardados
    echo "\n🔍 VERIFICANDO DATOS GUARDADOS:\n";
    
    $stmt = $pdo->prepare("SELECT * FROM jobs_applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "ID: {$app['id']}\n";
    echo "User ID: {$app['user_id']}\n";
    echo "Position: {$app['position']}\n";
    echo "Nombre: {$app['nombre']}\n";
    echo "Score: {$app['score']}%\n";
    echo "Status: {$app['status']}\n";
    echo "Detected Skills: " . ($app['detected_skills'] ?: 'NULL') . "\n";
    echo "Created: {$app['created_at']}\n";
    echo "Completed: {$app['completed_at']}\n";
    
    // 5. Verificar respuestas
    echo "\n📝 RESPUESTAS:\n";
    $stmt = $pdo->prepare("SELECT * FROM jobs_answers WHERE application_id = ? ORDER BY question_number");
    $stmt->execute([$applicationId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($answers as $answer) {
        echo "Q{$answer['question_number']}: " . substr($answer['answer_text'], 0, 50) . "...\n";
    }
    
    // 6. Verificar cómo se ve en el dashboard
    echo "\n📊 CÓMO SE VE EN DASHBOARD:\n";
    $stmt = $pdo->prepare("
        SELECT 
            ja.id,
            ja.user_id,
            ja.position,
            ja.nombre,
            ja.score as best_score,
            ja.status,
            ja.detected_skills,
            COUNT(ja2.id) as total_attempts,
            ja.created_at as last_attempt
        FROM jobs_applications ja
        LEFT JOIN jobs_applications ja2 ON ja2.user_id = ja.user_id AND ja2.position = ja.position
        WHERE ja.user_id = ? AND ja.position = ?
        GROUP BY ja.user_id, ja.position
        ORDER BY ja.score DESC
        LIMIT 1
    ");
    
    $stmt->execute([$demoData['user_id'], $demoData['position']]);
    $dashboardData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dashboardData) {
        echo "Dashboard muestra:\n";
        echo "- Nombre: {$dashboardData['nombre']}\n";
        echo "- Posición: {$dashboardData['position']}\n";
        echo "- Best Score: {$dashboardData['best_score']}%\n";
        echo "- Total Attempts: {$dashboardData['total_attempts']}\n";
        echo "- Skills: " . ($dashboardData['detected_skills'] ?: 'NULL') . "\n";
    }
    
    echo "\n✅ Script completado exitosamente!\n";
    echo "🔗 Ve al dashboard para verificar: https://ruta11app.agenterag.com/jobsTracker/dashboard/\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>