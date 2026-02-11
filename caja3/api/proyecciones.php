<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Parámetros de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10; // Elementos por página
$offset = ($page - 1) * $limit;

// Parámetro de búsqueda
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Consulta base
    $baseQuery = "FROM proyecciones";
    $countQuery = "SELECT COUNT(*) as total $baseQuery";
    $dataQuery = "SELECT * $baseQuery";
    
    // Añadir condición de búsqueda si es necesario
    if (!empty($search)) {
        $searchParam = "%$search%";
        $baseQuery .= " WHERE nombre LIKE :search OR notas LIKE :search";
        $countQuery = "SELECT COUNT(*) as total $baseQuery";
        $dataQuery = "SELECT * $baseQuery";
    }
    
    // Añadir ordenamiento y límites
    $dataQuery .= " ORDER BY fecha_creacion DESC LIMIT :limit OFFSET :offset";
    
    // Ejecutar consulta de conteo
    $countStmt = $conn->prepare($countQuery);
    if (!empty($search)) {
        $countStmt->bindParam(':search', $searchParam);
    }
    $countStmt->execute();
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ejecutar consulta de datos
    $dataStmt = $conn->prepare($dataQuery);
    if (!empty($search)) {
        $dataStmt->bindParam(':search', $searchParam);
    }
    $dataStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $dataStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    
    // Obtener resultados
    $proyecciones = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear fechas y valores monetarios
    foreach ($proyecciones as &$proyeccion) {
        $proyeccion['fecha_formateada'] = date('d/m/Y', strtotime($proyeccion['fecha_creacion']));
    }
    
    // Calcular información de paginación
    $totalPages = ceil($totalRows / $limit);
    
    // Devolver respuesta
    echo json_encode([
        'success' => true,
        'proyecciones' => $proyecciones,
        'pagination' => [
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}