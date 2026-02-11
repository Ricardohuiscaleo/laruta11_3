<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

try {
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
        throw new Exception('No hay datos suficientes para generar la proyección');
    }
    
    $ventas = $resultVentas->fetch_assoc();
    $costosFijos = $resultCostosFijos->fetch_assoc();
    $activos = $resultActivos->fetch_assoc();
    
    // Extraer datos básicos
    $precioPromedio = floatval($ventas['precio_promedio']);
    $costoVariable = floatval($ventas['costo_variable']) / 100; // Convertir a decimal
    $cantidadVendida = intval($ventas['cantidad_vendida']);
    $numeroCarros = intval($costosFijos['numero_carros']);
    $valorActivos = floatval($activos['valor_activos']);
    $vidaUtil = intval($activos['vida_util']);
    
    // Calcular sueldos totales
    $sueldosTotales = 0;
    $sueldosDetalle = [];
    
    for ($i = 1; $i <= 4; $i++) {
        if (isset($ventas["sueldo_{$i}"]) && $ventas["sueldo_{$i}"] > 0) {
            $sueldosTotales += $ventas["sueldo_{$i}"];
            $sueldosDetalle[] = [
                'cargo' => $ventas["cargo_{$i}"] ?? "Cargo {$i}",
                'sueldo_liquido' => $ventas["sueldo_{$i}"],
                'sueldo_bruto' => $ventas["sueldo_{$i}"] * 1.25
            ];
        }
    }
    
    // Calcular costos fijos
    $cargasSociales = $sueldosTotales * 0.25; // 25% de los sueldos
    $permisos = floatval($costosFijos['permisos']);
    $servicios = floatval($costosFijos['servicios']);
    $otrosFijos = floatval($costosFijos['otros_fijos']);
    $totalCostosFijos = $sueldosTotales + $cargasSociales + $permisos + $servicios + $otrosFijos;
    
    // Calcular depreciación
    $depreciacionMensual = ($vidaUtil > 0) ? $valorActivos / ($vidaUtil * 12) : 0;
    
    // Calcular valores diarios
    $ventasDiarias = $cantidadVendida;
    $ingresoBrutoDiario = $ventasDiarias * $precioPromedio * 1.19; // Con IVA
    $ingresoNetoDiario = $ventasDiarias * $precioPromedio;
    $costoVariableDiario = $ingresoNetoDiario * $costoVariable;
    $margenBrutoDiario = $ingresoNetoDiario - $costoVariableDiario;
    $costosFijosDiarios = $totalCostosFijos / 30;
    $depreciacionDiaria = $depreciacionMensual / 30;
    $utilidadAntesDiaria = $margenBrutoDiario - $costosFijosDiarios - $depreciacionDiaria;
    $impuestoRentaDiario = $utilidadAntesDiaria > 0 ? $utilidadAntesDiaria * 0.25 : 0;
    $utilidadDiaria = $utilidadAntesDiaria - $impuestoRentaDiario;
    
    // Calcular IVA y PPM diarios
    $ivaDiario = ($ingresoNetoDiario - $costoVariableDiario) * 0.19;
    $ppmDiario = $ingresoNetoDiario * 0.0025;
    $impuestosDiarios = $ivaDiario + $ppmDiario;
    
    // Calcular flujo de caja diario
    $flujoCajaDiario = $ingresoBrutoDiario - $costoVariableDiario - $costosFijosDiarios - $impuestosDiarios;
    
    // Calcular valores mensuales
    $ventasMensuales = $ventasDiarias * 30;
    $ingresoBrutoMensual = $ingresoBrutoDiario * 30;
    $ingresoNetoMensual = $ingresoNetoDiario * 30;
    $costoVariableMensual = $costoVariableDiario * 30;
    $margenBrutoMensual = $margenBrutoDiario * 30;
    $utilidadMensual = $utilidadDiaria * 30;
    
    // Calcular IVA y PPM mensuales
    $ivaMensual = $ivaDiario * 30;
    $ppmMensual = $ppmDiario * 30;
    $impuestosMensuales = $ivaMensual + $ppmMensual;
    
    // Calcular flujo de caja mensual
    $flujoCajaMensual = $flujoCajaDiario * 30;
    
    // Calcular valores anuales
    $ventasAnuales = $ventasMensuales * 12;
    $ingresoBrutoAnual = $ingresoBrutoMensual * 12;
    $ingresoNetoAnual = $ingresoNetoMensual * 12;
    $costoVariableAnual = $costoVariableMensual * 12;
    $margenBrutoAnual = $margenBrutoMensual * 12;
    $utilidadAnual = $utilidadMensual * 12;
    
    // Calcular impuestos anuales
    $ivaAnual = $ivaMensual * 12;
    $ppmAnual = $ppmMensual * 12;
    $rentaAnual = ($margenBrutoAnual - $totalCostosFijos * 12 - $depreciacionMensual * 12) * 0.25;
    $pagoFinalAbril = max(0, $rentaAnual - $ppmAnual);
    
    // Calcular flujo de caja anual
    $flujoCajaAnual = $flujoCajaMensual * 12;
    
    // Calcular punto de equilibrio
    $costoUnitario = $precioPromedio * $costoVariable;
    $margenContribucion = $precioPromedio - $costoUnitario;
    $puntoEquilibrioMensual = ($margenContribucion > 0) ? $totalCostosFijos / $margenContribucion : 0;
    $puntoEquilibrioDiario = $puntoEquilibrioMensual / 30;
    $puntoEquilibrioAnual = $puntoEquilibrioMensual * 12;
    
    // Calcular porcentaje del mes para alcanzar equilibrio
    $porcentajeEquilibrio = ($puntoEquilibrioMensual / $ventasMensuales) * 100;
    $diasEquilibrio = ceil($puntoEquilibrioDiario / $ventasDiarias);
    $mesesEquilibrio = ceil($puntoEquilibrioAnual / $ventasAnuales * 12);
    
    // Calcular valores para el estado de resultados
    $ingresosVentasNeto = $ingresoNetoMensual;
    $costoMercaderia = $costoVariableMensual;
    $margenBrutoER = $margenBrutoMensual;
    $costosFijosTotalesER = $totalCostosFijos;
    $depreciacionER = $depreciacionMensual;
    $utilidadAntesImpuestoER = $margenBrutoER - $costosFijosTotalesER - $depreciacionER;
    $provisionImpuestoRenta = $utilidadAntesImpuestoER > 0 ? $utilidadAntesImpuestoER * 0.25 : 0;
    $utilidadNetaER = $utilidadAntesImpuestoER - $provisionImpuestoRenta;
    
    // Calcular valores para el flujo de caja
    $ingresosTotalesFC = $ingresoBrutoMensual;
    $costoMercaderiaFC = $costoVariableMensual;
    $sueldosCargasFC = $sueldosTotales + $cargasSociales;
    $otrosCostosFijosFC = $permisos + $servicios + $otrosFijos;
    $pagoImpuestosFC = $ivaMensual + $ppmMensual;
    $dineroDisponibleFC = $ingresosTotalesFC - $costoMercaderiaFC - $sueldosCargasFC - $otrosCostosFijosFC - $pagoImpuestosFC;
    
    // Formatear todos los valores para la respuesta
    $response = [
        'success' => true,
        'data' => [
            // Datos generales
            'nombre_negocio' => 'La Ruta11 Foodtrucks',
            'propietarios' => 'Yojhans, Karina y Ricardo',
            'ubicacion' => 'Arica, Chile',
            'tipo_negocio' => 'Foodtrucks',
            'numero_carros' => $numeroCarros,
            
            // Costos y precios unitarios
            'costo_bruto' => number_format($precioPromedio * $costoVariable * 1.19, 0, '.', ','),
            'costo_neto' => number_format($precioPromedio * $costoVariable, 0, '.', ','),
            'precio_bruto' => number_format($precioPromedio * 1.19, 0, '.', ','),
            'precio_neto' => number_format($precioPromedio, 0, '.', ','),
            'utilidad_bruta' => number_format(($precioPromedio * 1.19) - ($precioPromedio * $costoVariable * 1.19), 0, '.', ','),
            'utilidad_neta' => number_format($precioPromedio - ($precioPromedio * $costoVariable), 0, '.', ','),
            'costo_promedio' => round($costoVariable * 100),
            
            // Resumen diario
            'ingreso_bruto_diario' => number_format($ingresoBrutoDiario, 0, '.', ','),
            'margen_bruto_diario' => number_format($margenBrutoDiario, 0, '.', ','),
            'utilidad_diaria' => number_format($utilidadDiaria, 0, '.', ','),
            'flujo_caja_diario' => number_format($flujoCajaDiario, 0, '.', ','),
            
            // Resumen mensual
            'ingreso_bruto_mensual' => number_format($ingresoBrutoMensual, 0, '.', ','),
            'margen_bruto_mensual' => number_format($margenBrutoMensual, 0, '.', ','),
            'utilidad_mensual' => number_format($utilidadMensual, 0, '.', ','),
            'flujo_caja_mensual' => number_format($flujoCajaMensual, 0, '.', ','),
            
            // Resumen anual
            'ingreso_bruto_anual' => number_format($ingresoBrutoAnual, 0, '.', ','),
            'margen_bruto_anual' => number_format($margenBrutoAnual, 0, '.', ','),
            'utilidad_anual' => number_format($utilidadAnual, 0, '.', ','),
            'flujo_caja_anual' => number_format($flujoCajaAnual, 0, '.', ','),
            
            // Activos
            'valor_activos' => number_format($valorActivos, 0, '.', ','),
            'vida_util' => $vidaUtil,
            'depreciacion_mensual' => number_format($depreciacionMensual, 0, '.', ','),
            
            // Costos fijos
            'permisos' => number_format($permisos, 0, '.', ','),
            'servicios' => number_format($servicios, 0, '.', ','),
            'otros_fijos' => number_format($otrosFijos, 0, '.', ','),
            'cargas_sociales' => number_format($cargasSociales, 0, '.', ','),
            'costos_fijos_totales' => number_format($totalCostosFijos, 0, '.', ','),
            
            // Ventas
            'ventas_diarias' => number_format($ventasDiarias, 0, '.', ','),
            'punto_equilibrio_diario' => number_format($puntoEquilibrioDiario, 0, '.', ','),
            'punto_equilibrio_mensual' => number_format($puntoEquilibrioMensual, 0, '.', ','),
            'punto_equilibrio_anual' => number_format($puntoEquilibrioAnual, 0, '.', ','),
            'dias_equilibrio' => $diasEquilibrio,
            'porcentaje_equilibrio' => round($porcentajeEquilibrio),
            'meses_equilibrio' => $mesesEquilibrio,
            
            // Estado de resultados
            'ingresos_ventas_neto' => number_format($ingresosVentasNeto, 0, '.', ','),
            'costo_mercaderia' => number_format($costoMercaderia, 0, '.', ','),
            'margen_bruto_er' => number_format($margenBrutoER, 0, '.', ','),
            'costos_fijos_totales_er' => number_format($costosFijosTotalesER, 0, '.', ','),
            'depreciacion_er' => number_format($depreciacionER, 0, '.', ','),
            'utilidad_antes_impuesto' => number_format($utilidadAntesImpuestoER, 0, '.', ','),
            'provision_impuesto_renta' => number_format($provisionImpuestoRenta, 0, '.', ','),
            'utilidad_neta_er' => number_format($utilidadNetaER, 0, '.', ','),
            
            // Impuestos
            'iva_diario' => number_format($ivaDiario, 0, '.', ','),
            'ppm_diario' => number_format($ppmDiario, 0, '.', ','),
            'impuestos_diarios' => number_format($impuestosDiarios, 0, '.', ','),
            'iva_mensual' => number_format($ivaMensual, 0, '.', ','),
            'ppm_mensual' => number_format($ppmMensual, 0, '.', ','),
            'impuestos_mensuales' => number_format($impuestosMensuales, 0, '.', ','),
            'iva_anual' => number_format($ivaAnual, 0, '.', ','),
            'ppm_anual' => number_format($ppmAnual, 0, '.', ','),
            'renta_anual' => number_format($rentaAnual, 0, '.', ','),
            'pago_final_abril' => number_format($pagoFinalAbril, 0, '.', ','),
            
            // Flujo de caja
            'ingresos_totales_fc' => number_format($ingresosTotalesFC, 0, '.', ','),
            'costo_mercaderia_fc' => number_format($costoMercaderiaFC, 0, '.', ','),
            'sueldos_cargas_fc' => number_format($sueldosCargasFC, 0, '.', ','),
            'otros_costos_fijos_fc' => number_format($otrosCostosFijosFC, 0, '.', ','),
            'pago_impuestos_fc' => number_format($pagoImpuestosFC, 0, '.', ','),
            'dinero_disponible_fc' => number_format($dineroDisponibleFC, 0, '.', ','),
            
            // Datos de personal
            'sueldos_detalle' => $sueldosDetalle
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>