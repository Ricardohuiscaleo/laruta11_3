<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once 'guardar_analisis.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Procesar la solicitud
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Intentar obtener datos de diferentes fuentes
    if (isset($_POST['tipo'])) {
        // Datos de formulario
        $data = $_POST;
    } else {
        // Datos JSON
        $data = json_decode(file_get_contents('php://input'), true);
    }
    
    if (!isset($data['tipo'])) {
        echo json_encode(['success' => false, 'error' => 'Falta el parámetro tipo']);
        exit;
    }
    
    $tipo = $data['tipo'];
    $tiposValidos = ['descriptivo', 'diagnostico', 'predictivo', 'prescriptivo'];
    
    if (!in_array($tipo, $tiposValidos)) {
        echo json_encode(['success' => false, 'error' => 'Tipo de análisis no válido']);
        exit;
    }
    
    // Obtener datos del dashboard directamente
    try {
        // Consultar datos de ventas, costos fijos y activos
        $sqlVentas = "SELECT * FROM ventas_v2 WHERE carro_id > 0 ORDER BY carro_id";
        $sqlCostosFijos = "SELECT * FROM costos_fijos_v2 ORDER BY id DESC LIMIT 1";
        $sqlActivos = "SELECT * FROM activos_v2 ORDER BY id DESC LIMIT 1";
        
        $resultVentas = $conn->query($sqlVentas);
        $resultCostosFijos = $conn->query($sqlCostosFijos);
        $resultActivos = $conn->query($sqlActivos);
        
        if (!$resultVentas || !$resultCostosFijos || !$resultActivos) {
            throw new Exception('Error al consultar la base de datos');
        }
        
        if ($resultVentas->num_rows === 0 || $resultCostosFijos->num_rows === 0 || $resultActivos->num_rows === 0) {
            throw new Exception('No hay datos suficientes para generar la proyección');
        }
        
        // Procesar datos de ventas
        $ventasCarros = [];
        $ingresosBrutosTotales = 0;
        $costoVariableTotal = 0;
        $precioPromedioTotal = 0;
        $costoVariablePorcentajeTotal = 0;
        $carrosConDatos = 0;
        $cantidadVendidaTotal = 0;
        $sueldosTotales = 0;
        $sueldosDetalle = [];
        $diasTrabajo = 24; // Días de trabajo al mes
        
        while ($ventasCarro = $resultVentas->fetch_assoc()) {
            $carroId = $ventasCarro['carro_id'];
            $precioPromedio = floatval($ventasCarro['precio_promedio']);
            $costoVariablePorcentaje = floatval($ventasCarro['costo_variable']);
            $cantidadVendidaDia = intval($ventasCarro['cantidad_vendida']);
            
            $cantidadVendidaTotal += $cantidadVendidaDia;
            
            // Calcular ventas mensuales por carro
            $ventasMensualesCarro = $precioPromedio * $cantidadVendidaDia * $diasTrabajo;
            $ingresosBrutosTotales += $ventasMensualesCarro;
            
            // Calcular costo variable por carro
            $costoVariableCarro = $ventasMensualesCarro * ($costoVariablePorcentaje / 100);
            $costoVariableTotal += $costoVariableCarro;
            
            // Acumular para promedios
            if ($precioPromedio > 0) {
                $precioPromedioTotal += $precioPromedio;
                $costoVariablePorcentajeTotal += $costoVariablePorcentaje;
                $carrosConDatos++;
            }
            
            // Calcular sueldos por carro
            for ($i = 1; $i <= 4; $i++) {
                if (isset($ventasCarro["sueldo_{$i}"]) && $ventasCarro["sueldo_{$i}"] > 0) {
                    $sueldoLiquido = floatval($ventasCarro["sueldo_{$i}"]);
                    $sueldoBruto = $sueldoLiquido * 1.25; // 125% como aproximación
                    $cargasSociales = 25; // 25% de cargas sociales
                    $costoRealSueldo = $sueldoBruto * (1 + $cargasSociales / 100);
                    
                    $sueldosTotales += $costoRealSueldo;
                    
                    $sueldosDetalle[] = [
                        'carro_id' => $carroId,
                        'cargo' => $ventasCarro["cargo_{$i}"] ?? "Cargo {$i}",
                        'sueldo_liquido' => $sueldoLiquido,
                        'sueldo_bruto' => $sueldoBruto
                    ];
                }
            }
        }
        
        // Obtener datos de costos fijos
        $costosFijos = $resultCostosFijos->fetch_assoc();
        $numeroCarros = intval($costosFijos['numero_carros']);
        $permisos = floatval($costosFijos['permisos']);
        $servicios = floatval($costosFijos['servicios']);
        $otrosFijos = floatval($costosFijos['otros_fijos']);
        
        // Calcular costos fijos
        $costosFijosOperacionales = ($permisos * $numeroCarros) + ($servicios * $numeroCarros) + $otrosFijos;
        $costosFijosTotales = $sueldosTotales + $costosFijosOperacionales;
        
        // Obtener datos de activos
        $activos = $resultActivos->fetch_assoc();
        $valorActivos = floatval($activos['valor_activos']);
        $vidaUtilAnios = intval($activos['vida_util']);
        
        // Calcular depreciación
        $depreciacionMensual = $vidaUtilAnios > 0 ? $valorActivos / ($vidaUtilAnios * 12) : 0;
        
        // Calcular promedios
        $precioPromedioUnitario = $carrosConDatos > 0 ? $precioPromedioTotal / $carrosConDatos : 0;
        $costoVariablePorcentajePromedio = $carrosConDatos > 0 ? $costoVariablePorcentajeTotal / $carrosConDatos : 0;
        $costoVariableUnitario = $precioPromedioUnitario * ($costoVariablePorcentajePromedio / 100);
        $margenContribucionUnitario = $precioPromedioUnitario - $costoVariableUnitario;
        
        // Calcular resultados financieros
        $ingresosNetos = $ingresosBrutosTotales / 1.19; // Quitar IVA (19%)
        $margenBruto = $ingresosNetos - $costoVariableTotal;
        $utilidadAntesImpuesto = $margenBruto - $costosFijosTotales - $depreciacionMensual;
        
        // Calcular provisión de impuesto a la renta (25% sobre utilidad positiva)
        $provisionImpuestoRenta = $utilidadAntesImpuesto > 0 ? $utilidadAntesImpuesto * 0.25 : 0;
        $utilidadNeta = $utilidadAntesImpuesto - $provisionImpuestoRenta;
        
        // Calcular impuestos
        $ivaDebito = $ingresosBrutosTotales - $ingresosNetos;
        $ivaCredito = ($costoVariableTotal + $costosFijosOperacionales) * 0.19;
        $ivaAPagar = max(0, $ivaDebito - $ivaCredito);
        $ppm = $ingresosNetos * 0.0025; // Tasa PPM inicial para Pymes
        
        // Calcular flujo de caja
        $pagosTotales = $costoVariableTotal + $costosFijosOperacionales + $sueldosTotales;
        $provisionImpuestos = $ivaAPagar + $ppm;
        $flujoCajaNeto = $ingresosBrutosTotales - $pagosTotales - $provisionImpuestos;
        
        // Calcular valores diarios
        $ingresosBrutosDiarios = $ingresosBrutosTotales / $diasTrabajo;
        $margenBrutoDiario = $margenBruto / $diasTrabajo;
        $utilidadNetaDiaria = $utilidadNeta / $diasTrabajo;
        $flujoCajaDiario = $flujoCajaNeto / $diasTrabajo;
        
        // Calcular valores anuales
        $mesesPorAnio = 12;
        $ingresosBrutosAnuales = $ingresosBrutosTotales * $mesesPorAnio;
        $margenBrutoAnual = $margenBruto * $mesesPorAnio;
        $utilidadNetaAnual = $utilidadNeta * $mesesPorAnio;
        $flujoCajaAnual = $flujoCajaNeto * $mesesPorAnio;
        
        // Calcular punto de equilibrio
        $equilibrioUnidadesMensual = $margenContribucionUnitario > 0 ? ceil($costosFijosTotales / $margenContribucionUnitario) : 0;
        $porcentajeMesEquilibrio = $ingresosBrutosTotales > 0 ? 
                                  min(100, round(($equilibrioUnidadesMensual * $precioPromedioUnitario / $ingresosBrutosTotales) * 100)) : 
                                  100;
        $mesesParaEquilibrio = $ingresosBrutosAnuales > 0 ? 
                             ceil(($equilibrioUnidadesMensual * $precioPromedioUnitario * 12 / $ingresosBrutosAnuales) * $mesesPorAnio) : 
                             PHP_INT_MAX;
        
        // Calcular impuestos anuales
        $ivaAPagarAnual = $ivaAPagar * $mesesPorAnio;
        $ppmAnual = $ppm * $mesesPorAnio;
        $utilidadAntesImpuestoAnual = $utilidadAntesImpuesto * $mesesPorAnio;
        $rentaAnual = $utilidadAntesImpuestoAnual > 0 ? $utilidadAntesImpuestoAnual * 0.25 : 0;
        $pagoFinalAbril = max(0, $rentaAnual - $ppmAnual);
        
        // Preparar datos para el análisis
        $datosDashboard = [
            'nombre_negocio' => 'La Ruta11 Foodtrucks',
            'propietarios' => 'Yojhans, Karina y Ricardo',
            'ubicacion' => 'Arica, Chile',
            'tipo_negocio' => 'Foodtrucks',
            'numero_carros' => $numeroCarros,
            
            // Costos y precios unitarios
            'precio_neto' => number_format($precioPromedioUnitario, 0, '.', ','),
            'precio_bruto' => number_format($precioPromedioUnitario * 1.19, 0, '.', ','),
            'costo_neto' => number_format($costoVariableUnitario, 0, '.', ','),
            'costo_bruto' => number_format($costoVariableUnitario * 1.19, 0, '.', ','),
            'utilidad_bruta' => number_format(($precioPromedioUnitario * 1.19) - ($costoVariableUnitario * 1.19), 0, '.', ','),
            'utilidad_neta' => number_format($precioPromedioUnitario - $costoVariableUnitario, 0, '.', ','),
            'costo_promedio' => round($costoVariablePorcentajePromedio),
            
            // Resumen diario
            'ingreso_bruto_diario' => number_format($ingresosBrutosDiarios, 0, '.', ','),
            'margen_bruto_diario' => number_format($margenBrutoDiario, 0, '.', ','),
            'utilidad_diaria' => number_format($utilidadNetaDiaria, 0, '.', ','),
            'flujo_caja_diario' => number_format($flujoCajaDiario, 0, '.', ','),
            
            // Resumen mensual
            'ingreso_bruto_mensual' => number_format($ingresosBrutosTotales, 0, '.', ','),
            'margen_bruto_mensual' => number_format($margenBruto, 0, '.', ','),
            'utilidad_mensual' => number_format($utilidadNeta, 0, '.', ','),
            'flujo_caja_mensual' => number_format($flujoCajaNeto, 0, '.', ','),
            
            // Resumen anual
            'ingreso_bruto_anual' => number_format($ingresosBrutosAnuales, 0, '.', ','),
            'margen_bruto_anual' => number_format($margenBrutoAnual, 0, '.', ','),
            'utilidad_anual' => number_format($utilidadNetaAnual, 0, '.', ','),
            'flujo_caja_anual' => number_format($flujoCajaAnual, 0, '.', ','),
            
            // Activos
            'valor_activos' => number_format($valorActivos, 0, '.', ','),
            'vida_util' => $vidaUtilAnios,
            'depreciacion_mensual' => number_format($depreciacionMensual, 0, '.', ','),
            
            // Costos fijos
            'permisos' => number_format($permisos, 0, '.', ','),
            'servicios' => number_format($servicios, 0, '.', ','),
            'otros_fijos' => number_format($otrosFijos, 0, '.', ','),
            'cargas_sociales' => number_format($sueldosTotales - array_sum(array_column($sueldosDetalle, 'sueldo_bruto')), 0, '.', ','),
            'costos_fijos_totales' => number_format($costosFijosTotales, 0, '.', ','),
            
            // Ventas
            'ventas_diarias' => number_format($cantidadVendidaTotal, 0, '.', ','),
            'punto_equilibrio_mensual' => number_format($equilibrioUnidadesMensual, 0, '.', ','),
            'porcentaje_equilibrio' => $porcentajeMesEquilibrio,
            'meses_equilibrio' => $mesesParaEquilibrio == PHP_INT_MAX ? 0 : $mesesParaEquilibrio,
            
            // Impuestos
            'iva_mensual' => number_format($ivaAPagar, 0, '.', ','),
            'ppm_mensual' => number_format($ppm, 0, '.', ','),
            'renta_anual' => number_format($rentaAnual, 0, '.', ','),
            'pago_final_abril' => number_format($pagoFinalAbril, 0, '.', ',')
        ];
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Error al obtener datos: ' . $e->getMessage()
        ]);
        exit;
    }
    
    // Verificar API key de Gemini
    if (!isset($config['gemini_api_key']) || empty($config['gemini_api_key'])) {
        echo json_encode(['success' => false, 'error' => 'La clave API de Gemini no está configurada']);
        exit;
    }
    
    // Obtener análisis existentes para evitar repeticiones
    $analisisExistentes = [];
    $sqlExistentes = "SELECT tipo, contenido FROM ia_analisis";
    $resultExistentes = $conn->query($sqlExistentes);
    if ($resultExistentes && $resultExistentes->num_rows > 0) {
        while ($row = $resultExistentes->fetch_assoc()) {
            $analisisExistentes[$row['tipo']] = $row['contenido'];
        }
    }
    
    // Generar análisis con Gemini
    $apiKey = $config['gemini_api_key'];
    $resultado = generarAnalisisGemini($datosDashboard, $tipo, $apiKey, $analisisExistentes);
    
    if (!$resultado['success']) {
        echo json_encode(['success' => false, 'error' => $resultado['error']]);
        exit;
    }
    
    // Guardar el análisis en la base de datos
    if (guardarAnalisis($conn, $tipo, $resultado['contenido'])) {
        // Si es una solicitud para regenerar todos los análisis
        if (isset($data['regenerar_todos']) && ($data['regenerar_todos'] === true || $data['regenerar_todos'] === 'true')) {
            $todosAnalisis = [];
            $todosAnalisis[$tipo] = $resultado['contenido'];
            
            // Generar los otros tipos de análisis
            foreach ($tiposValidos as $otroTipo) {
                if ($otroTipo !== $tipo) {
                    // Actualizar los análisis existentes con los que ya se han generado
                    $analisisExistentesActualizados = $analisisExistentes;
                    $analisisExistentesActualizados[$tipo] = $resultado['contenido'];
                    
                    // Generar el siguiente análisis con los anteriores como contexto
                    $otroResultado = generarAnalisisGemini($datosDashboard, $otroTipo, $apiKey, $analisisExistentesActualizados);
                    if ($otroResultado['success']) {
                        guardarAnalisis($conn, $otroTipo, $otroResultado['contenido']);
                        $todosAnalisis[$otroTipo] = $otroResultado['contenido'];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Todos los análisis generados y guardados correctamente',
                'analisis' => $todosAnalisis,
                'generation_time' => $resultado['generation_time'] ?? 0,
                'modelo_usado' => $resultado['modelo_usado'] ?? ''
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Análisis generado y guardado correctamente',
                'analisis' => $resultado['contenido'],
                'generation_time' => $resultado['generation_time'] ?? 0,
                'modelo_usado' => $resultado['modelo_usado'] ?? ''
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar el análisis']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

// Función para generar análisis con Gemini
function generarAnalisisGemini($datos, $tipo, $apiKey, $analisisExistentes = []) {
    $startTime = microtime(true);
    
    // Preparar el prompt según el tipo de análisis
    $prompt = "Genera un análisis financiero {$tipo} basado en proyecciones para el negocio 'La Ruta11 Foodtrucks'.\n\n";
    $prompt .= "INSTRUCCIONES IMPORTANTES:\n";
    $prompt .= "1. Usa punto como separador decimal y enfatiza los porcentajes y desviaciones relevantes.\n";
    $prompt .= "2. NO CALCULES TUS PROPIOS VALORES. Usa EXACTAMENTE los valores proporcionados.\n";
    $prompt .= "3. Usa formato HTML con etiquetas <h3> para títulos y <p> para párrafos.\n";
    $prompt .= "4. Escribe MÁXIMO 2 PÁRRAFOS concisos y directos. Destaca solo los números más relevantes.\n";
    $prompt .= "5. Usa un español neutro con un toque de humor sarcasmo profesional. EVITA modismos chilenos como 'cabros'.\n";
    $prompt .= "6. NO USES frases como 'Análisis Proyectado: Foodtrucks La Rueda Sabrosa' o 'felicitaciones Yojhans, Karina y Ricardo'.\n";
    $prompt .= "7. Enfócate en insights útiles y tendencias, no en repetir datos. Destaca desviaciones y porcentajes.\n";
    $prompt .= "8. IMPORTANTE: NO REPITAS información o análisis que ya se hayan mencionado en otros análisis.\n\n";
    $prompt .= "DATOS FINANCIEROS:\n";
    
    // Datos clave para el análisis
    $datosRelevantes = [
        'precio_neto' => '$' . $datos['precio_neto'],
        'precio_bruto' => '$' . $datos['precio_bruto'],
        'costo_neto' => '$' . $datos['costo_neto'],
        'costo_bruto' => '$' . $datos['costo_bruto'],
        'costo_promedio' => $datos['costo_promedio'] . '%',
        'utilidad_bruta' => '$' . $datos['utilidad_bruta'],
        'utilidad_neta' => '$' . $datos['utilidad_neta'],
        'ventas_diarias' => $datos['ventas_diarias'] . ' unidades',
        'ingreso_bruto_diario' => '$' . $datos['ingreso_bruto_diario'],
        'margen_bruto_diario' => '$' . $datos['margen_bruto_diario'],
        'utilidad_diaria' => '$' . $datos['utilidad_diaria'],
        'flujo_caja_diario' => '$' . $datos['flujo_caja_diario'],
        'ingreso_bruto_mensual' => '$' . $datos['ingreso_bruto_mensual'],
        'margen_bruto_mensual' => '$' . $datos['margen_bruto_mensual'],
        'utilidad_mensual' => '$' . $datos['utilidad_mensual'],
        'flujo_caja_mensual' => '$' . $datos['flujo_caja_mensual'],
        'ingreso_bruto_anual' => '$' . $datos['ingreso_bruto_anual'],
        'margen_bruto_anual' => '$' . $datos['margen_bruto_anual'],
        'utilidad_anual' => '$' . $datos['utilidad_anual'],
        'flujo_caja_anual' => '$' . $datos['flujo_caja_anual'],
        'valor_activos' => '$' . $datos['valor_activos'],
        'vida_util' => $datos['vida_util'] . ' años',
        'depreciacion_mensual' => '$' . $datos['depreciacion_mensual'],
        'costos_fijos_totales' => '$' . $datos['costos_fijos_totales'],
        'punto_equilibrio_mensual' => $datos['punto_equilibrio_mensual'] . ' unidades',
        'porcentaje_equilibrio' => $datos['porcentaje_equilibrio'] . '%',
        'meses_equilibrio' => $datos['meses_equilibrio'] . ' meses',
        'iva_mensual' => '$' . $datos['iva_mensual'],
        'ppm_mensual' => '$' . $datos['ppm_mensual'],
        'renta_anual' => '$' . $datos['renta_anual'],
        'pago_final_abril' => '$' . $datos['pago_final_abril']
    ];
    
    // Agregar datos relevantes al prompt
    foreach ($datosRelevantes as $clave => $valor) {
        $nombreLegible = str_replace('_', ' ', $clave);
        $nombreLegible = ucfirst($nombreLegible);
        $prompt .= "- {$nombreLegible}: {$valor}\n";
    }
    
    // Añadir análisis existentes al prompt para evitar repeticiones
    if (!empty($analisisExistentes)) {
        $prompt .= "ANÁLISIS EXISTENTES (NO REPITAS ESTA INFORMACIÓN):\n";
        foreach ($analisisExistentes as $tipoExistente => $contenidoExistente) {
            if ($tipoExistente != $tipo) {
                // Limpiar HTML y extraer solo el texto
                $textoLimpio = strip_tags($contenidoExistente);
                // Limitar a 200 caracteres para no sobrecargar el prompt
                $textoResumido = substr($textoLimpio, 0, 200) . "...";
                $prompt .= "- Análisis {$tipoExistente}: {$textoResumido}\n";
            }
        }
        $prompt .= "\n";
    }
    
    // Instrucciones específicas según el tipo
    switch ($tipo) {
        case 'descriptivo':
            $prompt .= "\nRealiza un análisis descriptivo conciso de la proyección financiera. Enfocate en la estructura de costos y la relación entre ingresos y gastos. Destaca porcentajes y tendencias clave que NO se hayan mencionado en otros análisis.";
            break;
        case 'diagnostico':
            $prompt .= "\nRealiza un diagnóstico directo de las principales fortalezas y debilidades financieras según la proyección. Enfatiza puntos de atención y oportunidades que NO se hayan mencionado en el análisis descriptivo. Usa un toque de humor sutil.";
            break;
        case 'predictivo':
            $prompt .= "\nRealiza un análisis predictivo sobre las tendencias financieras futuras. Enfocate en aspectos que NO se hayan cubierto en los análisis descriptivo y diagnóstico. Destaca patrones de crecimiento y riesgos potenciales con porcentajes y comparativas.";
            break;
        case 'prescriptivo':
            $prompt .= "\nEntrega 2-3 recomendaciones estratégicas basadas en la proyección financiera que NO se hayan sugerido en los otros análisis. Sé directo, práctico y un poco sarcástico (con profesionalismo). Enfatiza el impacto porcentual esperado de cada recomendación.";
            break;
    }
    
    // Configurar la solicitud a la API de Gemini
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key={$apiKey}";
    
    $data = [
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
            "temperature" => 0.7,
            "maxOutputTokens" => 512,
            "topK" => 40,
            "topP" => 0.9
        ]
    ];
    
    // Realizar la solicitud
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Calcular tiempo de generación
    $generationTime = round(microtime(true) - $startTime, 2);
    
    if ($error) {
        return ['success' => false, 'error' => "Error de cURL: {$error}"];
    }
    
    if ($httpCode != 200) {
        return ['success' => false, 'error' => "Error HTTP {$httpCode}"];
    }
    
    $responseData = json_decode($response, true);
    
    if (!$responseData) {
        return ['success' => false, 'error' => "Error al decodificar JSON"];
    }
    
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $contenido = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Procesar el contenido para extraer HTML
        if (preg_match('/```html\s*([\s\S]*?)```/i', $contenido, $matches)) {
            $contenido = trim($matches[1]);
        } else if (preg_match('/```([\s\S]*?)```/i', $contenido, $matches)) {
            $contenido = trim($matches[1]);
        }
        
        // Si no parece HTML, convertir markdown a HTML básico
        if (strlen($contenido) < 100 || (strpos($contenido, '<') === false && strpos($contenido, '>') === false)) {
            $contenido = preg_replace('/```[\s\S]*?```/', '', $contenido);
            $contenido = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $contenido);
            $contenido = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $contenido);
            $contenido = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $contenido);
            $contenido = preg_replace('/\*\*([^*]*)\*\*/', '<strong>$1</strong>', $contenido);
            $contenido = '<p>' . str_replace("\n\n", '</p><p>', $contenido) . '</p>';
        }
        
        // Asegurar que todas las negritas de Markdown se conviertan a HTML
        $contenido = preg_replace('/\*\*([^*]*)\*\*/', '<strong>$1</strong>', $contenido);
        
        return [
            'success' => true,
            'contenido' => $contenido,
            'generation_time' => $generationTime,
            'modelo_usado' => 'gemini-2.0-flash'
        ];
    }
    
    return ['success' => false, 'error' => "No se pudo generar el análisis"];
}

$conn->close();
?>