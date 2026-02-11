<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir archivo de configuración
require_once __DIR__ . '/../config.php';

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Método no permitido"
    ]);
    exit;
}

// Obtener datos de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Verificar que se proporcionaron los datos necesarios
if (!isset($data['nombre']) || !isset($data['periodo_mes']) || !isset($data['periodo_anio'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Faltan datos requeridos"
    ]);
    exit;
}

// Extraer datos principales
$id = isset($data['id']) ? intval($data['id']) : null;
$nombre = mysqli_real_escape_string($conn, $data['nombre']);
$fecha_creacion = isset($data['fecha_creacion']) ? mysqli_real_escape_string($conn, $data['fecha_creacion']) : date('Y-m-d');
$periodo_mes = intval($data['periodo_mes']);
$periodo_anio = intval($data['periodo_anio']);
$valor_activos = floatval($data['valor_activos']);
$vida_util_anios = floatval($data['vida_util_anios']);
$numero_carros = intval($data['numero_carros']);
$sueldo_base = floatval($data['sueldo_base']);
$cargas_sociales_porcentaje = floatval($data['cargas_sociales_porcentaje']);
$permisos_por_carro = floatval($data['permisos_por_carro']);
$servicios_por_carro = floatval($data['servicios_por_carro']);
$otros_fijos = floatval($data['otros_fijos']);
$dias_trabajo = intval($data['dias_trabajo']);
$ingresos_brutos_totales = floatval($data['ingresos_brutos_totales']);
$ingresos_netos = floatval($data['ingresos_netos']);
$costo_variable_total = floatval($data['costo_variable_total']);
$margen_bruto = floatval($data['margen_bruto']);
$costos_fijos_totales = floatval($data['costos_fijos_totales']);
$utilidad_antes_impuesto = floatval($data['utilidad_antes_impuesto']);
$iva_a_pagar = floatval($data['iva_a_pagar']);
$ppm = floatval($data['ppm']);
$flujo_caja_neto = floatval($data['flujo_caja_neto']);
$notas = isset($data['notas']) ? mysqli_real_escape_string($conn, $data['notas']) : '';

// Iniciar transacción
mysqli_begin_transaction($conn);

try {
    // Insertar o actualizar la proyección
    if ($id) {
        // Actualizar proyección existente
        $sql = "UPDATE proyecciones_financieras SET 
                nombre = '$nombre',
                fecha_creacion = '$fecha_creacion',
                periodo_mes = $periodo_mes,
                periodo_anio = $periodo_anio,
                valor_activos = $valor_activos,
                vida_util_anios = $vida_util_anios,
                numero_carros = $numero_carros,
                sueldo_base = $sueldo_base,
                cargas_sociales_porcentaje = $cargas_sociales_porcentaje,
                permisos_por_carro = $permisos_por_carro,
                servicios_por_carro = $servicios_por_carro,
                otros_fijos = $otros_fijos,
                dias_trabajo = $dias_trabajo,
                ingresos_brutos_totales = $ingresos_brutos_totales,
                ingresos_netos = $ingresos_netos,
                costo_variable_total = $costo_variable_total,
                margen_bruto = $margen_bruto,
                costos_fijos_totales = $costos_fijos_totales,
                utilidad_antes_impuesto = $utilidad_antes_impuesto,
                iva_a_pagar = $iva_a_pagar,
                ppm = $ppm,
                flujo_caja_neto = $flujo_caja_neto,
                notas = '$notas',
                updated_at = NOW()
                WHERE id = $id";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Error al actualizar la proyección: " . mysqli_error($conn));
        }
        
        $proyeccion_id = $id;
    } else {
        // Insertar nueva proyección
        $sql = "INSERT INTO proyecciones_financieras (
                nombre, fecha_creacion, periodo_mes, periodo_anio, valor_activos,
                vida_util_anios, numero_carros, sueldo_base, cargas_sociales_porcentaje,
                permisos_por_carro, servicios_por_carro, otros_fijos, dias_trabajo,
                ingresos_brutos_totales, ingresos_netos, costo_variable_total,
                margen_bruto, costos_fijos_totales, utilidad_antes_impuesto,
                iva_a_pagar, ppm, flujo_caja_neto, notas
            ) VALUES (
                '$nombre', '$fecha_creacion', $periodo_mes, $periodo_anio, $valor_activos,
                $vida_util_anios, $numero_carros, $sueldo_base, $cargas_sociales_porcentaje,
                $permisos_por_carro, $servicios_por_carro, $otros_fijos, $dias_trabajo,
                $ingresos_brutos_totales, $ingresos_netos, $costo_variable_total,
                $margen_bruto, $costos_fijos_totales, $utilidad_antes_impuesto,
                $iva_a_pagar, $ppm, $flujo_caja_neto, '$notas'
            )";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Error al insertar la proyección: " . mysqli_error($conn));
        }
        
        $proyeccion_id = mysqli_insert_id($conn);
    }
    
    // Eliminar detalles existentes si es una actualización
    if ($id) {
        $sql_delete = "DELETE FROM detalles_proyeccion WHERE proyeccion_id = $id";
        if (!mysqli_query($conn, $sql_delete)) {
            throw new Exception("Error al eliminar detalles existentes: " . mysqli_error($conn));
        }
    }
    
    // Insertar detalles de la proyección (carros)
    if (isset($data['detalles_carros']) && is_array($data['detalles_carros'])) {
        foreach ($data['detalles_carros'] as $detalle) {
            $numero_carro = intval($detalle['numero_carro']);
            $precio_promedio = floatval($detalle['precio_promedio']);
            $costo_variable_porcentaje = floatval($detalle['costo_variable_porcentaje']);
            $cantidad_vendida_dia = intval($detalle['cantidad_vendida_dia']);
            
            $sql_detalle = "INSERT INTO detalles_proyeccion (
                    proyeccion_id, numero_carro, precio_promedio, 
                    costo_variable_porcentaje, cantidad_vendida_dia
                ) VALUES (
                    $proyeccion_id, $numero_carro, $precio_promedio, 
                    $costo_variable_porcentaje, $cantidad_vendida_dia
                )";
            
            if (!mysqli_query($conn, $sql_detalle)) {
                throw new Exception("Error al insertar detalle para carro $numero_carro: " . mysqli_error($conn));
            }
        }
    }
    
    // Confirmar transacción
    mysqli_commit($conn);
    
    // Devolver respuesta exitosa
    echo json_encode([
        "success" => true,
        "message" => $id ? "Proyección actualizada correctamente" : "Proyección creada correctamente",
        "id" => $proyeccion_id
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    mysqli_rollback($conn);
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>