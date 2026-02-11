<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
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
    
    // Parámetros de cálculo
    $diasTrabajo = 24; // Días de trabajo al mes
    $mesesPorAnio = 12; // Meses por año
    
    // Extraer datos básicos
    $valorActivos = floatval($activos['valor_activos']);
    $vidaUtilAnios = intval($activos['vida_util']);
    $numeroCarros = intval($costosFijos['numero_carros']);
    $permisos = floatval($costosFijos['permisos']);
    $servicios = floatval($costosFijos['servicios']);
    $otrosFijos = floatval($costosFijos['otros_fijos']);
    
    // Calcular depreciación
    $depreciacionMensual = $vidaUtilAnios > 0 ? $valorActivos / ($vidaUtilAnios * 12) : 0;
    
    // Calcular sueldos totales
    $sueldosTotales = 0;
    $sueldosDetalle = [];
    
    // Obtener datos de ventas por carro
    $sqlVentasCarros = "SELECT * FROM ventas_v2 WHERE carro_id > 0 ORDER BY carro_id";
    $resultVentasCarros = $conn->query($sqlVentasCarros);
    
    $ventasCarros = [];
    $ingresosBrutosTotales = 0;
    $costoVariableTotal = 0;
    $precioPromedioTotal = 0;
    $costoVariablePorcentajeTotal = 0;
    $carrosConDatos = 0;
    
    if ($resultVentasCarros && $resultVentasCarros->num_rows > 0) {
        while ($ventasCarro = $resultVentasCarros->fetch_assoc()) {
            $carroId = $ventasCarro['carro_id'];
            $precioPromedio = floatval($ventasCarro['precio_promedio']);
            $costoVariablePorcentaje = floatval($ventasCarro['costo_variable']);
            $cantidadVendidaDia = intval($ventasCarro['cantidad_vendida']);
            
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
            
            $ventasCarros[] = [
                'carro_id' => $carroId,
                'precio_promedio' => $precioPromedio,
                'costo_variable' => $costoVariablePorcentaje,
                'cantidad_vendida' => $cantidadVendidaDia,
                'ventas_mensuales' => $ventasMensualesCarro,
                'costo_variable_mensual' => $costoVariableCarro
            ];
        }
    }
    
    // Calcular costos fijos
    $costosFijosOperacionales = ($permisos * $numeroCarros) + ($servicios * $numeroCarros) + $otrosFijos;
    $costosFijosTotales = $sueldosTotales + $costosFijosOperacionales;
    
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
    $ingresosBrutosAnuales = $ingresosBrutosTotales * $mesesPorAnio;
    $margenBrutoAnual = $margenBruto * $mesesPorAnio;
    $utilidadNetaAnual = $utilidadNeta * $mesesPorAnio;
    $flujoCajaAnual = $flujoCajaNeto * $mesesPorAnio;
    
    // Calcular impuestos diarios
    $ivaAPagarDiario = $ivaAPagar / $diasTrabajo;
    $ppmDiario = $ppm / $diasTrabajo;
    $totalImpuestosDiario = $ivaAPagarDiario + $ppmDiario;
    
    // Calcular impuestos anuales
    $ivaAPagarAnual = $ivaAPagar * $mesesPorAnio;
    $ppmAnual = $ppm * $mesesPorAnio;
    $utilidadAntesImpuestoAnual = $utilidadAntesImpuesto * $mesesPorAnio;
    $rentaAnual = $utilidadAntesImpuestoAnual > 0 ? $utilidadAntesImpuestoAnual * 0.25 : 0;
    $pagoFinalAbril = max(0, $rentaAnual - $ppmAnual);
    
    // Calcular punto de equilibrio
    $precioPromedioUnitario = $carrosConDatos > 0 ? $precioPromedioTotal / $carrosConDatos : 0;
    $costoVariablePorcentajePromedio = $carrosConDatos > 0 ? $costoVariablePorcentajeTotal / $carrosConDatos : 0;
    $costoVariableUnitario = $precioPromedioUnitario * ($costoVariablePorcentajePromedio / 100);
    $margenContribucionUnitario = $precioPromedioUnitario - $costoVariableUnitario;
    
    // Calcular punto de equilibrio en unidades
    $costosFijosDiarios = $costosFijosTotales / $diasTrabajo;
    $equilibrioUnidadesDiario = $margenContribucionUnitario > 0 ? ceil($costosFijosDiarios / $margenContribucionUnitario) : 0;
    $equilibrioVentasDiario = $equilibrioUnidadesDiario * $precioPromedioUnitario;
    
    // Calcular punto de equilibrio mensual
    $equilibrioUnidadesMensual = $margenContribucionUnitario > 0 ? ceil($costosFijosTotales / $margenContribucionUnitario) : 0;
    $equilibrioVentasMensual = $equilibrioUnidadesMensual * $precioPromedioUnitario;
    
    // Calcular punto de equilibrio anual
    $costosFijosAnuales = $costosFijosTotales * $mesesPorAnio;
    $equilibrioUnidadesAnual = $margenContribucionUnitario > 0 ? ceil($costosFijosAnuales / $margenContribucionUnitario) : 0;
    $equilibrioVentasAnual = $equilibrioUnidadesAnual * $precioPromedioUnitario;
    
    // Calcular métricas adicionales
    $ventasDiariasPromedio = 0;
    $cantidadVendidaTotal = 0;
    
    foreach ($ventasCarros as $ventasCarro) {
        $cantidadVendidaTotal += $ventasCarro['cantidad_vendida'];
    }
    
    $ventasDiariasPromedio = $cantidadVendidaTotal;
    
    // Calcular días para alcanzar el punto de equilibrio
    $diasParaEquilibrio = $ventasDiariasPromedio > 0 ? 
                         ceil($equilibrioUnidadesDiario / $ventasDiariasPromedio) : 
                         PHP_INT_MAX;
    
    // Calcular porcentaje del mes para alcanzar equilibrio
    $porcentajeMesEquilibrio = $ingresosBrutosTotales > 0 ? 
                              min(100, round(($equilibrioVentasMensual / $ingresosBrutosTotales) * 100)) : 
                              100;
    
    // Calcular meses para alcanzar equilibrio anual
    $mesesParaEquilibrio = $ingresosBrutosAnuales > 0 ? 
                         ceil(($equilibrioVentasAnual / $ingresosBrutosAnuales) * $mesesPorAnio) : 
                         PHP_INT_MAX;
    
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
            'costo_bruto' => number_format($costoVariableUnitario * 1.19, 0, '.', ','),
            'costo_neto' => number_format($costoVariableUnitario, 0, '.', ','),
            'precio_bruto' => number_format($precioPromedioUnitario * 1.19, 0, '.', ','),
            'precio_neto' => number_format($precioPromedioUnitario, 0, '.', ','),
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
            'ventas_diarias' => number_format($ventasDiariasPromedio, 0, '.', ','),
            'punto_equilibrio_diario' => number_format($equilibrioUnidadesDiario, 0, '.', ','),
            'punto_equilibrio_mensual' => number_format($equilibrioUnidadesMensual, 0, '.', ','),
            'punto_equilibrio_anual' => number_format($equilibrioUnidadesAnual, 0, '.', ','),
            'dias_equilibrio' => $diasParaEquilibrio == PHP_INT_MAX ? 0 : $diasParaEquilibrio,
            'porcentaje_equilibrio' => $porcentajeMesEquilibrio,
            'meses_equilibrio' => $mesesParaEquilibrio == PHP_INT_MAX ? 0 : $mesesParaEquilibrio,
            
            // Estado de resultados
            'ingresos_ventas_neto' => number_format($ingresosNetos, 0, '.', ','),
            'costo_mercaderia' => number_format($costoVariableTotal, 0, '.', ','),
            'margen_bruto_er' => number_format($margenBruto, 0, '.', ','),
            'costos_fijos_totales_er' => number_format($costosFijosTotales, 0, '.', ','),
            'depreciacion_er' => number_format($depreciacionMensual, 0, '.', ','),
            'utilidad_antes_impuesto' => number_format($utilidadAntesImpuesto, 0, '.', ','),
            'provision_impuesto_renta' => number_format($provisionImpuestoRenta, 0, '.', ','),
            'utilidad_neta_er' => number_format($utilidadNeta, 0, '.', ','),
            
            // Impuestos
            'iva_diario' => number_format($ivaAPagarDiario, 0, '.', ','),
            'ppm_diario' => number_format($ppmDiario, 0, '.', ','),
            'impuestos_diarios' => number_format($totalImpuestosDiario, 0, '.', ','),
            'iva_mensual' => number_format($ivaAPagar, 0, '.', ','),
            'ppm_mensual' => number_format($ppm, 0, '.', ','),
            'impuestos_mensuales' => number_format($ivaAPagar + $ppm, 0, '.', ','),
            'iva_anual' => number_format($ivaAPagarAnual, 0, '.', ','),
            'ppm_anual' => number_format($ppmAnual, 0, '.', ','),
            'renta_anual' => number_format($rentaAnual, 0, '.', ','),
            'pago_final_abril' => number_format($pagoFinalAbril, 0, '.', ','),
            
            // Flujo de caja
            'ingresos_totales_fc' => number_format($ingresosBrutosTotales, 0, '.', ','),
            'costo_mercaderia_fc' => number_format($costoVariableTotal, 0, '.', ','),
            'sueldos_cargas_fc' => number_format($sueldosTotales, 0, '.', ','),
            'otros_costos_fijos_fc' => number_format($costosFijosOperacionales, 0, '.', ','),
            'pago_impuestos_fc' => number_format($provisionImpuestos, 0, '.', ','),
            'dinero_disponible_fc' => number_format($flujoCajaNeto, 0, '.', ','),
            
            // Datos de personal
            'sueldos_detalle' => $sueldosDetalle,
            
            // Datos de ventas por carro
            'ventas_carros' => $ventasCarros,
            
            // Datos de activos
            'activos' => $activos,
            
            // Datos de costos fijos
            'costos_fijos' => $costosFijos
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>