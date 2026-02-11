<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    die('Error de conexión a BD');
}

mysqli_set_charset($conn, 'utf8');

// Limpiar tabla existente
mysqli_query($conn, "DELETE FROM food_trucks");

// Insertar ubicaciones reales en Tucapel 2637, Arica
$trucks = [
    [
        'nombre' => 'La Ruta 11 - Truck #1',
        'descripcion' => 'Food truck principal - Especialidades de carne y churrascos',
        'latitud' => -18.4647,
        'longitud' => -70.2997,
        'direccion' => 'Tucapel 2637, Arica, Arica y Parinacota',
        'horario_inicio' => '11:00:00',
        'horario_fin' => '22:00:00',
        'dias_semana' => '["lunes", "martes", "miercoles", "jueves", "viernes", "sabado"]'
    ],
    [
        'nombre' => 'La Ruta 11 - Truck #2', 
        'descripcion' => 'Food truck secundario - Completos, papas y bebidas',
        'latitud' => -18.4647,
        'longitud' => -70.2997,
        'direccion' => 'Tucapel 2637, Arica, Arica y Parinacota',
        'horario_inicio' => '11:00:00',
        'horario_fin' => '22:00:00',
        'dias_semana' => '["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"]'
    ]
];

foreach ($trucks as $truck) {
    $query = "INSERT INTO food_trucks (nombre, descripcion, latitud, longitud, direccion, horario_inicio, horario_fin, dias_semana) 
              VALUES ('{$truck['nombre']}', '{$truck['descripcion']}', {$truck['latitud']}, {$truck['longitud']}, 
                      '{$truck['direccion']}', '{$truck['horario_inicio']}', '{$truck['horario_fin']}', '{$truck['dias_semana']}')";
    
    if (mysqli_query($conn, $query)) {
        echo "✅ {$truck['nombre']} agregado correctamente<br>";
    } else {
        echo "❌ Error agregando {$truck['nombre']}: " . mysqli_error($conn) . "<br>";
    }
}

mysqli_close($conn);
echo "<br>🚚 Food trucks actualizados en Tucapel 2637, Arica";
?>