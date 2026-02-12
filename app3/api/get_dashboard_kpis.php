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

// Obtener parámetros de filtro
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 2025; // Año actual 2025
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : null;

// Preparar respuesta
$response = [
    'kpis' => [
        'ticket_promedio' => 0,
        'ventas_diarias_promedio' => 0,
        'margen_bruto_porcentaje' => 0,
        'utilidad_porcentaje' => 0
    ],
    'graficos' => []
];

// Verificar si la tabla proyecciones_financieras existe
$check_proyecciones_table = mysqli_query($conn, "SHOW TABLES LIKE 'proyecciones_financieras'");
if (mysqli_num_rows($check_proyecciones_table) > 0) {
    // Obtener datos de proyecciones para los gráficos
    $sql_proyecciones = "SELECT 
        pf.periodo_mes as mes,
        SUM(pf.ingresos_brutos_totales) as ventas_totales,
        AVG(dp.precio_promedio) as ticket_promedio
    FROM 
        proyecciones_financieras pf
    JOIN 
        detalles_proyeccion dp ON pf.id = dp.proyeccion_id
    WHERE 
        pf.periodo_anio = $anio
    GROUP BY 
        pf.periodo_mes
    ORDER BY 
        pf.periodo_mes";
    
    $result_proyecciones = mysqli_query($conn, $sql_proyecciones);
    
    // Preparar datos para gráficos
    $ventas_mensuales = [];
    $proyeccion_vs_real = [];
    $ticket_tendencia = [];
    
    if ($result_proyecciones) {
        while ($row = mysqli_fetch_assoc($result_proyecciones)) {
            $mes_num = $row['mes'];
            
            // Ventas mensuales
            $ventas_mensuales[] = [
                'mes' => $mes_num,
                'ventas' => (int)$row['ventas_totales']
            ];
            
            // Proyección vs real (solo proyección, real es 0)
            $proyeccion_vs_real[] = [
                'mes' => $mes_num,
                'proyeccion' => (int)$row['ventas_totales'],
                'real' => 0 // No hay ventas reales aún
            ];
            
            // Tendencia de ticket
            $ticket_tendencia[] = [
                'mes' => $mes_num,
                'ticket' => round($row['ticket_promedio'])
            ];
        }
    }
    
    // Añadir datos a la respuesta
    $response['graficos']['ventas_mensuales'] = $ventas_mensuales;
    $response['graficos']['proyeccion_vs_real'] = $proyeccion_vs_real;
    $response['graficos']['ticket_tendencia'] = $ticket_tendencia;
    
    // Distribución de costos (datos de ejemplo)
    $response['graficos']['distribucion_costos'] = [
        ['categoria' => 'Ingredientes', 'valor' => 1200000],
        ['categoria' => 'Personal', 'valor' => 750000],
        ['categoria' => 'Permisos', 'valor' => 50000],
        ['categoria' => 'Servicios', 'valor' => 100000],
        ['categoria' => 'Otros', 'valor' => 50000],
        ['categoria' => 'Depreciación', 'valor' => 83333]
    ];
}

// Devolver respuesta
echo json_encode($response);

mysqli_close($conn);
?>