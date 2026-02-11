<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener datos del dashboard
function obtenerDatosDashboard($conn) {
    // Consultar datos de ventas, costos fijos y activos
    $sqlVentas = "SELECT * FROM ventas_v2 ORDER BY id DESC LIMIT 1";
    $sqlCostosFijos = "SELECT * FROM costos_fijos_v2 ORDER BY id DESC LIMIT 1";
    $sqlActivos = "SELECT * FROM activos_v2 ORDER BY id DESC LIMIT 1";
    
    $resultVentas = $conn->query($sqlVentas);
    $resultCostosFijos = $conn->query($sqlCostosFijos);
    $resultActivos = $conn->query($sqlActivos);
    
    if (!$resultVentas || !$resultCostosFijos || !$resultActivos) {
        throw new Exception('Error al consultar la base de datos');
    }
    
    if ($resultVentas->num_rows === 0 || $resultCostosFijos->num_rows === 0 || $resultActivos->num_rows === 0) {
        throw new Exception('No hay datos suficientes para generar el análisis');
    }
    
    $ventas = $resultVentas->fetch_assoc();
    $costosFijos = $resultCostosFijos->fetch_assoc();
    $activos = $resultActivos->fetch_assoc();
    
    // Calcular valores financieros
    $precioPromedio = $ventas['precio_promedio'];
    $costoVariable = $ventas['costo_variable'] / 100; // Convertir a decimal
    $cantidadVendida = $ventas['cantidad_vendida'];
    
    // Calcular sueldos totales
    $sueldosTotales = 0;
    for ($i = 1; $i <= 4; $i++) {
        if (isset($ventas["sueldo_{$i}"]) && $ventas["sueldo_{$i}"] > 0) {
            $sueldosTotales += $ventas["sueldo_{$i}"];
        }
    }
    
    // Calcular valores derivados
    $ventasDiarias = $cantidadVendida;
    $ventasMensuales = $ventasDiarias * 30;
    $ingresosMensuales = $ventasMensuales * $precioPromedio;
    $costoVariableMensual = $ingresosMensuales * $costoVariable;
    $margenBrutoMensual = $ingresosMensuales - $costoVariableMensual;
    
    // Costos fijos
    $totalCostosFijos = $sueldosTotales + $costosFijos['cargas_sociales'] + 
                        $costosFijos['permisos'] + $costosFijos['servicios'] + 
                        $costosFijos['otros_fijos'];
    
    // Depreciación
    $depreciacionMensual = $activos['vida_util'] > 0 ? 
                          $activos['valor_activos'] / ($activos['vida_util'] * 12) : 0;
    
    // Utilidad
    $utilidadAntesImpuesto = $margenBrutoMensual - $totalCostosFijos - $depreciacionMensual;
    $impuestoRenta = $utilidadAntesImpuesto > 0 ? $utilidadAntesImpuesto * 0.25 : 0;
    $utilidadMensual = $utilidadAntesImpuesto - $impuestoRenta;
    
    // Punto de equilibrio
    $costoUnitario = $precioPromedio * $costoVariable;
    $margenContribucion = $precioPromedio - $costoUnitario;
    $puntoEquilibrioMensual = $margenContribucion > 0 ? 
                             $totalCostosFijos / $margenContribucion : 0;
    
    // Formatear valores para el análisis
    return [
        'nombre_negocio' => 'La Ruta11 Foodtrucks',
        'propietarios' => 'Yojhans, Karina y Ricardo',
        'ubicacion' => 'Arica, Chile',
        'tipo_negocio' => 'Foodtrucks',
        'numero_carros' => $costosFijos['numero_carros'],
        
        // Precios y costos
        'precio_neto' => '$' . number_format($precioPromedio, 0, '.', ','),
        'precio_bruto' => '$' . number_format($precioPromedio * 1.19, 0, '.', ','),
        'costo_neto' => '$' . number_format($precioPromedio * $costoVariable, 0, '.', ','),
        'costo_bruto' => '$' . number_format($precioPromedio * $costoVariable * 1.19, 0, '.', ','),
        'costo_promedio' => round($costoVariable * 100) . '%',
        
        // Ventas
        'ventas_diarias' => number_format($ventasDiarias, 0, '.', ',') . ' unidades',
        'punto_equilibrio_mensual' => number_format($puntoEquilibrioMensual, 0, '.', ',') . ' unidades',
        
        // Resultados financieros
        'ingresos_mensuales' => '$' . number_format($ingresosMensuales, 0, '.', ','),
        'costos_variables_mensuales' => '$' . number_format($costoVariableMensual, 0, '.', ','),
        'margen_bruto_mensual' => '$' . number_format($margenBrutoMensual, 0, '.', ','),
        'costos_fijos_totales' => '$' . number_format($totalCostosFijos, 0, '.', ','),
        'utilidad_mensual' => '$' . number_format($utilidadMensual, 0, '.', ','),
        
        // Proyecciones anuales
        'ingresos_anuales' => '$' . number_format($ingresosMensuales * 12, 0, '.', ','),
        'utilidad_anual' => '$' . number_format($utilidadMensual * 12, 0, '.', ','),
        
        // Impuestos
        'iva_mensual' => '$' . number_format(($ingresosMensuales - $costoVariableMensual) * 0.19, 0, '.', ','),
        'ppm_mensual' => '$' . number_format($ingresosMensuales * 0.0025, 0, '.', ','),
        'renta_anual' => '$' . number_format($utilidadAntesImpuesto * 12 * 0.25, 0, '.', ','),
        'ppm_anual' => '$' . number_format($ingresosMensuales * 0.0025 * 12, 0, '.', ','),
        'pago_final_abril' => '$' . number_format(max(0, ($utilidadAntesImpuesto * 12 * 0.25) - ($ingresosMensuales * 0.0025 * 12)), 0, '.', ',')
    ];
}

// Generar el prompt para cada tipo de análisis
function generarPrompt($datos, $tipo) {
    $prompt = "Genera un análisis financiero {$tipo} para el negocio {$datos['nombre_negocio']} ubicado en {$datos['ubicacion']} y propiedad de {$datos['propietarios']}.\n\n";
    $prompt .= "INSTRUCCIONES IMPORTANTES:\n";
    $prompt .= "1. Usa punto como separador decimal y redondea los porcentajes a números enteros.\n";
    $prompt .= "2. NO CALCULES TUS PROPIOS VALORES. Usa EXACTAMENTE los valores proporcionados.\n";
    $prompt .= "3. Usa formato HTML con etiquetas <h3> para títulos y <p> para párrafos.\n\n";
    $prompt .= "DATOS FINANCIEROS:\n";
    
    // Agregar todos los datos disponibles al prompt
    foreach ($datos as $clave => $valor) {
        $nombreLegible = str_replace('_', ' ', $clave);
        $nombreLegible = ucfirst($nombreLegible);
        $prompt .= "- {$nombreLegible}: {$valor}\n";
    }
    
    // Instrucciones específicas según el tipo
    switch ($tipo) {
        case 'descriptivo':
            $prompt .= "\nRealiza un análisis descriptivo detallado de la situación financiera actual.";
            break;
        case 'diagnostico':
            $prompt .= "\nRealiza un diagnóstico de fortalezas y debilidades financieras.";
            break;
        case 'predictivo':
            $prompt .= "\nRealiza un análisis predictivo sobre el futuro financiero del negocio.";
            break;
        case 'prescriptivo':
            $prompt .= "\nRealiza un análisis prescriptivo con recomendaciones concretas para mejorar.";
            break;
    }
    
    return $prompt;
}

// Generar la configuración de la API para cada modelo
function generarConfiguracionAPI($prompt, $modelo = 'gemini-2.0-flash') {
    return [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => $prompt
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.4,
            "maxOutputTokens" => 1024,
            "topK" => 40,
            "topP" => 0.8
        ],
        "model" => $modelo
    ];
}

try {
    // Obtener datos del dashboard
    $datosDashboard = obtenerDatosDashboard($conn);
    
    // Generar prompts para cada tipo de análisis
    $tiposAnalisis = ['descriptivo', 'diagnostico', 'predictivo', 'prescriptivo'];
    $prompts = [];
    $configuraciones = [];
    
    foreach ($tiposAnalisis as $tipo) {
        $prompts[$tipo] = generarPrompt($datosDashboard, $tipo);
        $configuraciones[$tipo] = generarConfiguracionAPI($prompts[$tipo], 'gemini-2.0-flash');
    }
    
    // Mostrar los datos y prompts
    echo json_encode([
        'success' => true,
        'datos_dashboard' => $datosDashboard,
        'prompts' => $prompts,
        'configuraciones_api' => $configuraciones,
        'mensaje' => 'Este script muestra los datos exactos que se envían a la API de Gemini'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>