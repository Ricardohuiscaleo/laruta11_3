<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Incluir archivo de configuración
require_once __DIR__ . '/../config.php';

// Si no se pudo conectar con la configuración principal, intentar con alternativas
if (!isset($conn) || $conn === false) {
    // Si no existe, intentamos con la configuración global
    $config_path = __DIR__ . '/../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
        
        // Configurar la conexión a la base de datos usando los valores del config global
        $conn = mysqli_connect(
            $config['Calcularuta11_db_host'],
            $config['Calcularuta11_db_user'],
            $config['Calcularuta11_db_pass'],
            $config['Calcularuta11_db_name']
        );
        
        // Verificar la conexión
        if($conn === false){
            http_response_code(500);
            echo json_encode(["error" => "No se pudo conectar a la base de datos: " . mysqli_connect_error()]);
            exit;
        }
        
        // Configurar el conjunto de caracteres a utf8
        mysqli_set_charset($conn, "utf8");
    } else {
        http_response_code(500);
        echo json_encode(["error" => "No se encontró el archivo de configuración"]);
        exit;
    }
}

// Generar datos de ejemplo para el dashboard
$anio = 2025; // Año actual
$meses = 12;

// Array para almacenar resultados
$results = [];

// Verificar si ya existen proyecciones para 2025
$sql_check = "SELECT COUNT(*) as count FROM proyecciones_financieras WHERE periodo_anio = $anio";
$result_check = mysqli_query($conn, $sql_check);
$row = mysqli_fetch_assoc($result_check);

if ($row['count'] == 0) {
    // Insertar proyecciones para cada mes de 2025
    for ($mes = 1; $mes <= $meses; $mes++) {
        // Valores base
        $precio_promedio = 5000 + rand(0, 1000); // Entre 5000 y 6000
        $cantidad_vendida = 30 + rand(0, 10); // Entre 30 y 40
        $costo_variable_porcentaje = 35 + rand(0, 10); // Entre 35% y 45%
        
        // Calcular ingresos y costos
        $ingresos_brutos = $precio_promedio * $cantidad_vendida * 24; // 24 días al mes
        $ingresos_netos = $ingresos_brutos / 1.19; // Quitar IVA
        $costo_variable = $ingresos_brutos * ($costo_variable_porcentaje / 100);
        $margen_bruto = $ingresos_netos - $costo_variable;
        
        // Costos fijos
        $sueldo_base = 500000;
        $cargas_sociales = 25;
        $costo_personal = $sueldo_base * (1 + $cargas_sociales/100); // 1 empleado
        $permisos = 50000;
        $servicios = 100000;
        $otros_fijos = 50000;
        $costos_fijos = $costo_personal + $permisos + $servicios + $otros_fijos;
        
        // Utilidad
        $utilidad = $margen_bruto - $costos_fijos;
        $iva_pagar = $ingresos_brutos - $ingresos_netos;
        $ppm = $utilidad * 0.01; // 1% de PPM
        $flujo_caja = $utilidad - $iva_pagar - $ppm;
        
        // Insertar proyección
        $sql_insert = "INSERT INTO proyecciones_financieras (
            nombre, periodo_mes, periodo_anio, valor_activos, vida_util_anios, 
            numero_carros, sueldo_base, cargas_sociales_porcentaje, permisos_por_carro, 
            servicios_por_carro, otros_fijos, dias_trabajo, ingresos_brutos_totales, 
            ingresos_netos, costo_variable_total, margen_bruto, costos_fijos_totales, 
            utilidad_antes_impuesto, iva_a_pagar, ppm, flujo_caja_neto, notas
        ) VALUES (
            'Proyección $mes-$anio', $mes, $anio, 5000000, 5, 
            1, $sueldo_base, $cargas_sociales, $permisos, 
            $servicios, $otros_fijos, 24, $ingresos_brutos, 
            $ingresos_netos, $costo_variable, $margen_bruto, $costos_fijos, 
            $utilidad, $iva_pagar, $ppm, $flujo_caja, 'Proyección automática para dashboard'
        )";
        
        if (mysqli_query($conn, $sql_insert)) {
            $proyeccion_id = mysqli_insert_id($conn);
            
            // Insertar detalle de proyección
            $sql_detalle = "INSERT INTO detalles_proyeccion (
                proyeccion_id, numero_carro, precio_promedio, costo_variable_porcentaje, cantidad_vendida_dia
            ) VALUES (
                $proyeccion_id, 1, $precio_promedio, $costo_variable_porcentaje, $cantidad_vendida
            )";
            
            mysqli_query($conn, $sql_detalle);
            
            $results[] = ["success" => true, "message" => "Proyección para mes $mes de $anio creada correctamente"];
        } else {
            $results[] = ["success" => false, "message" => "Error al crear proyección para mes $mes: " . mysqli_error($conn)];
        }
    }
} else {
    $results[] = ["success" => true, "message" => "Ya existen proyecciones para $anio, no se crearon nuevas"];
}

// Devolver resultados
echo json_encode(["results" => $results]);

mysqli_close($conn);
?>