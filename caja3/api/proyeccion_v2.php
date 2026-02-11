<?php
// Evitar el almacenamiento en caché de las respuestas
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Si se proporciona un ID, obtener una proyección específica
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $sql = "SELECT * FROM proyecciones_v2 WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $proyeccion = $result->fetch_assoc();
        $proyeccion['datos'] = json_decode($proyeccion['datos'], true);
        
        echo json_encode([
            'success' => true,
            'data' => $proyeccion
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Proyección no encontrada'
        ]);
    }
} else {
    // Obtener datos actuales para la calculadora
    
    // Obtener activos
    $sql_activos = "SELECT * FROM activos_v2 ORDER BY id DESC LIMIT 1";
    $result_activos = $conn->query($sql_activos);
    $activos = $result_activos->fetch_assoc();
    
    // Obtener costos fijos
    $sql_costos = "SELECT * FROM costos_fijos_v2 ORDER BY id DESC LIMIT 1";
    $result_costos = $conn->query($sql_costos);
    $costos_fijos = $result_costos->fetch_assoc();
    
    // Obtener ventas con datos de personal
    $sql_ventas = "SELECT id, carro_id, precio_promedio, costo_variable, cantidad_vendida, 
                  cargo_1, sueldo_1, cargo_2, sueldo_2, cargo_3, sueldo_3, cargo_4, sueldo_4 
                  FROM ventas_v2 ORDER BY carro_id";
    $result_ventas = $conn->query($sql_ventas);
    $ventas = [];
    
    if ($result_ventas && $result_ventas->num_rows > 0) {
        while ($row = $result_ventas->fetch_assoc()) {
            // Asegurarse de que los valores numéricos sean realmente numéricos
            // Convertir explícitamente a números para evitar problemas de tipo
            $row['precio_promedio'] = (float)$row['precio_promedio'];
            $row['costo_variable'] = (float)$row['costo_variable'];
            $row['cantidad_vendida'] = (int)$row['cantidad_vendida'];
            $row['sueldo_1'] = (int)$row['sueldo_1'];
            $row['sueldo_2'] = (int)$row['sueldo_2'];
            if ($row['sueldo_3'] !== null) $row['sueldo_3'] = (int)$row['sueldo_3'];
            if ($row['sueldo_4'] !== null) $row['sueldo_4'] = (int)$row['sueldo_4'];
            
            // Asegurarse de que los valores de texto sean realmente texto
            $row['cargo_1'] = (string)$row['cargo_1'];
            $row['cargo_2'] = (string)$row['cargo_2'];
            // Los valores NULL se mantienen como NULL
            
            $ventas[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'activos' => $activos,
            'costos_fijos' => $costos_fijos,
            'ventas' => $ventas
        ]
    ]);
}

$conn->close();
?>