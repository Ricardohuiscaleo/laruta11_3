<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir archivo de configuración
require_once __DIR__ . '/../config.php';

// Si no se pudo conectar con la configuración principal, intentar con alternativas
if (!isset($conn) || $conn === false) {
    // Si no existe, intentamos con la configuración global
    $config_path = __DIR__ . '/../../../config.php';
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

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Verificar si se recibieron los datos necesarios
if (!isset($data['fecha_venta']) || !isset($data['monto_total']) || !isset($data['carro_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos requeridos"]);
    exit;
}

// Preparar los datos
$fecha_venta = $data['fecha_venta'];
$hora_venta = isset($data['hora_venta']) ? $data['hora_venta'] : date('H:i:s');
$monto_total = $data['monto_total'];
$monto_neto = $monto_total / 1.19; // Calcular monto neto (sin IVA)
$iva = $monto_total - $monto_neto; // Calcular IVA
$metodo_pago = isset($data['metodo_pago']) ? $data['metodo_pago'] : 'efectivo';
$carro_id = $data['carro_id'];
$empleado_id = isset($data['empleado_id']) ? $data['empleado_id'] : null;
$notas = isset($data['notas']) ? $data['notas'] : null;

// Iniciar transacción
mysqli_begin_transaction($conn);

try {
    // Insertar venta
    $sql = "INSERT INTO ventas (fecha_venta, hora_venta, monto_total, monto_neto, iva, metodo_pago, carro_id, empleado_id, notas) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssdddsiis", $fecha_venta, $hora_venta, $monto_total, $monto_neto, $iva, $metodo_pago, $carro_id, $empleado_id, $notas);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al registrar la venta: " . mysqli_stmt_error($stmt));
    }
    
    $venta_id = mysqli_insert_id($conn);
    
    // Insertar detalles de venta si se proporcionaron
    if (isset($data['detalles']) && is_array($data['detalles'])) {
        $sql_detalle = "INSERT INTO detalles_venta (venta_id, producto_id, cantidad, precio_unitario, subtotal) 
                        VALUES (?, ?, ?, ?, ?)";
        
        $stmt_detalle = mysqli_prepare($conn, $sql_detalle);
        
        foreach ($data['detalles'] as $detalle) {
            if (!isset($detalle['producto_id']) || !isset($detalle['cantidad']) || !isset($detalle['precio_unitario'])) {
                continue; // Saltar detalles incompletos
            }
            
            $producto_id = $detalle['producto_id'];
            $cantidad = $detalle['cantidad'];
            $precio_unitario = $detalle['precio_unitario'];
            $subtotal = $cantidad * $precio_unitario;
            
            mysqli_stmt_bind_param($stmt_detalle, "iidd", $venta_id, $producto_id, $cantidad, $precio_unitario, $subtotal);
            
            if (!mysqli_stmt_execute($stmt_detalle)) {
                throw new Exception("Error al registrar detalle de venta: " . mysqli_stmt_error($stmt_detalle));
            }
        }
        
        mysqli_stmt_close($stmt_detalle);
    }
    
    // Actualizar estadísticas diarias
    $fecha = $data['fecha_venta'];
    
    // Verificar si ya existe un registro para esta fecha y carro
    $sql_check = "SELECT id, total_ventas, cantidad_ventas FROM estadisticas_diarias 
                 WHERE fecha = ? AND carro_id = ?";
    
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "si", $fecha, $carro_id);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Actualizar registro existente
        $nuevo_total = $row['total_ventas'] + $monto_total;
        $nueva_cantidad = $row['cantidad_ventas'] + 1;
        $ticket_promedio = $nuevo_total / $nueva_cantidad;
        
        $sql_update = "UPDATE estadisticas_diarias 
                      SET total_ventas = ?, cantidad_ventas = ?, ticket_promedio = ? 
                      WHERE id = ?";
        
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "dddi", $nuevo_total, $nueva_cantidad, $ticket_promedio, $row['id']);
        
        if (!mysqli_stmt_execute($stmt_update)) {
            throw new Exception("Error al actualizar estadísticas: " . mysqli_stmt_error($stmt_update));
        }
        
        mysqli_stmt_close($stmt_update);
    } else {
        // Crear nuevo registro
        $sql_insert = "INSERT INTO estadisticas_diarias (fecha, carro_id, total_ventas, cantidad_ventas, ticket_promedio) 
                      VALUES (?, ?, ?, 1, ?)";
        
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, "sidd", $fecha, $carro_id, $monto_total, $monto_total);
        
        if (!mysqli_stmt_execute($stmt_insert)) {
            throw new Exception("Error al insertar estadísticas: " . mysqli_stmt_error($stmt_insert));
        }
        
        mysqli_stmt_close($stmt_insert);
    }
    
    mysqli_stmt_close($stmt_check);
    
    // Confirmar transacción
    mysqli_commit($conn);
    
    // Devolver respuesta exitosa
    http_response_code(201);
    echo json_encode([
        "message" => "Venta registrada exitosamente",
        "venta_id" => $venta_id
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    mysqli_rollback($conn);
    
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>