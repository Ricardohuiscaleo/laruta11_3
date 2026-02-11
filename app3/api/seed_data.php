<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

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

// Función para ejecutar consultas SQL y manejar errores
function executeQuery($conn, $sql, $message) {
    if (mysqli_query($conn, $sql)) {
        return ["success" => true, "message" => $message];
    } else {
        return ["success" => false, "message" => "Error: " . mysqli_error($conn)];
    }
}

// Array para almacenar resultados
$results = [];

// Limpiar datos existentes (opcional, comentar si no se desea limpiar)
$truncate_queries = [
    "TRUNCATE TABLE proyecciones_financieras",
    "TRUNCATE TABLE detalles_proyeccion",
    "TRUNCATE TABLE activos",
    "TRUNCATE TABLE empleados"
];

foreach ($truncate_queries as $query) {
    $results[] = executeQuery($conn, $query, "Tabla limpiada correctamente");
}

// 1. Insertar datos de activos
$activos = [
    [
        'nombre' => 'Food Truck Principal',
        'descripcion' => 'Vehículo principal para venta de sándwiches',
        'valor_adquisicion' => 15000000,
        'fecha_adquisicion' => '2022-01-15',
        'vida_util_anios' => 10,
        'valor_residual' => 3000000,
        'estado' => 'activo',
        'ubicacion' => 'Santiago Centro'
    ],
    [
        'nombre' => 'Food Truck Secundario',
        'descripcion' => 'Vehículo secundario para eventos especiales',
        'valor_adquisicion' => 12000000,
        'fecha_adquisicion' => '2022-03-10',
        'vida_util_anios' => 10,
        'valor_residual' => 2500000,
        'estado' => 'activo',
        'ubicacion' => 'Providencia'
    ],
    [
        'nombre' => 'Equipamiento de Cocina',
        'descripcion' => 'Equipos de cocina para preparación de alimentos',
        'valor_adquisicion' => 3500000,
        'fecha_adquisicion' => '2022-01-20',
        'vida_util_anios' => 5,
        'valor_residual' => 500000,
        'estado' => 'activo',
        'ubicacion' => 'Bodega Central'
    ]
];

foreach ($activos as $activo) {
    $sql = "INSERT INTO activos (nombre, descripcion, valor_adquisicion, fecha_adquisicion, vida_util_anios, valor_residual, estado, ubicacion) 
            VALUES ('{$activo['nombre']}', '{$activo['descripcion']}', {$activo['valor_adquisicion']}, '{$activo['fecha_adquisicion']}', 
                    {$activo['vida_util_anios']}, {$activo['valor_residual']}, '{$activo['estado']}', '{$activo['ubicacion']}')";
    $results[] = executeQuery($conn, $sql, "Activo '{$activo['nombre']}' insertado correctamente");
}

// 2. Insertar datos de empleados
$empleados = [
    [
        'rut' => '12345678-9',
        'nombre' => 'Juan',
        'apellido' => 'Pérez',
        'fecha_nacimiento' => '1990-05-15',
        'fecha_contratacion' => '2022-01-10',
        'cargo' => 'Cocinero',
        'sueldo_base' => 550000,
        'afp' => 'Habitat',
        'sistema_salud' => 'FONASA'
    ],
    [
        'rut' => '98765432-1',
        'nombre' => 'María',
        'apellido' => 'González',
        'fecha_nacimiento' => '1992-08-20',
        'fecha_contratacion' => '2022-01-10',
        'cargo' => 'Cajera',
        'sueldo_base' => 500000,
        'afp' => 'Provida',
        'sistema_salud' => 'FONASA'
    ],
    [
        'rut' => '11222333-4',
        'nombre' => 'Pedro',
        'apellido' => 'Soto',
        'fecha_nacimiento' => '1988-12-03',
        'fecha_contratacion' => '2022-02-15',
        'cargo' => 'Cocinero',
        'sueldo_base' => 550000,
        'afp' => 'Modelo',
        'sistema_salud' => 'ISAPRE'
    ],
    [
        'rut' => '44555666-7',
        'nombre' => 'Ana',
        'apellido' => 'Muñoz',
        'fecha_nacimiento' => '1995-04-25',
        'fecha_contratacion' => '2022-02-15',
        'cargo' => 'Cajera',
        'sueldo_base' => 500000,
        'afp' => 'Capital',
        'sistema_salud' => 'FONASA'
    ]
];

foreach ($empleados as $empleado) {
    $sql = "INSERT INTO empleados (rut, nombre, apellido, fecha_nacimiento, fecha_contratacion, cargo, sueldo_base, afp, sistema_salud) 
            VALUES ('{$empleado['rut']}', '{$empleado['nombre']}', '{$empleado['apellido']}', '{$empleado['fecha_nacimiento']}', 
                    '{$empleado['fecha_contratacion']}', '{$empleado['cargo']}', {$empleado['sueldo_base']}, '{$empleado['afp']}', '{$empleado['sistema_salud']}')";
    $results[] = executeQuery($conn, $sql, "Empleado '{$empleado['nombre']} {$empleado['apellido']}' insertado correctamente");
}

// 3. Insertar proyecciones financieras históricas (últimos 12 meses)
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$anio_actual = date('Y');
$mes_actual = date('n');

// Generar datos para los últimos 12 meses
for ($i = 0; $i < 12; $i++) {
    $mes = $mes_actual - $i;
    $anio = $anio_actual;
    
    if ($mes <= 0) {
        $mes += 12;
        $anio--;
    }
    
    // Variación estacional: más ventas en verano (dic-feb) y menos en invierno (jun-ago)
    $factor_estacional = 1.0;
    if ($mes >= 12 || $mes <= 2) { // Verano
        $factor_estacional = 1.3;
    } elseif ($mes >= 6 && $mes <= 8) { // Invierno
        $factor_estacional = 0.8;
    }
    
    // Base de ventas con variación estacional
    $precio_promedio = 7000;
    $cantidad_diaria_base = 50 * $factor_estacional;
    $dias_trabajo = 24;
    $costo_variable_porcentaje = 45;
    
    // Calcular valores financieros
    $cantidad_vendida_mes = $cantidad_diaria_base * $dias_trabajo;
    $ingresos_brutos_totales = $precio_promedio * $cantidad_vendida_mes;
    $ingresos_netos = $ingresos_brutos_totales / 1.19; // Quitar IVA
    $costo_variable_total = ($ingresos_netos * $costo_variable_porcentaje / 100);
    $margen_bruto = $ingresos_netos - $costo_variable_total;
    
    // Costos fijos
    $valor_activos = 25000000;
    $vida_util_anios = 10;
    $numero_carros = 2;
    $sueldo_base = 529000;
    $cargas_sociales_porcentaje = 25;
    $permisos_por_carro = 150000;
    $servicios_por_carro = 100000;
    $otros_fijos = 50000;
    
    $costoRealSueldo = $sueldo_base * (1 + $cargas_sociales_porcentaje / 100);
    $sueldosTotales = $costoRealSueldo * 2 * $numero_carros; // 2 personas por carro
    $depreciacionMensual = $valor_activos / ($vida_util_anios * 12);
    $costosFijosOperacionales = ($permisos_por_carro * $numero_carros) + ($servicios_por_carro * $numero_carros) + $otros_fijos;
    $costos_fijos_totales = $sueldosTotales + $costosFijosOperacionales + $depreciacionMensual;
    
    // Resultados finales
    $utilidad_antes_impuesto = $margen_bruto - $costos_fijos_totales;
    
    // Impuestos
    $iva_debito = $ingresos_brutos_totales - $ingresos_netos;
    $iva_credito = ($costo_variable_total + $costosFijosOperacionales) * 0.19;
    $iva_a_pagar = max(0, $iva_debito - $iva_credito);
    $ppm = $ingresos_netos * 0.0025; // Tasa PPM inicial para Pymes
    
    // Flujo de caja
    $pagos_totales = $costo_variable_total + $costosFijosOperacionales + $sueldosTotales;
    $provision_impuestos = $iva_a_pagar + $ppm;
    $flujo_caja_neto = $ingresos_brutos_totales - $pagos_totales - $provision_impuestos;
    
    // Nombre de la proyección
    $nombre_proyeccion = "Proyección {$meses[$mes]} $anio";
    $fecha_creacion = "$anio-$mes-01";
    
    // Insertar proyección
    $sql = "INSERT INTO proyecciones_financieras (
                nombre, fecha_creacion, periodo_mes, periodo_anio, valor_activos, 
                vida_util_anios, numero_carros, sueldo_base, cargas_sociales_porcentaje, 
                permisos_por_carro, servicios_por_carro, otros_fijos, dias_trabajo, 
                ingresos_brutos_totales, ingresos_netos, costo_variable_total, 
                margen_bruto, costos_fijos_totales, utilidad_antes_impuesto, 
                iva_a_pagar, ppm, flujo_caja_neto
            ) VALUES (
                '$nombre_proyeccion', '$fecha_creacion', $mes, $anio, $valor_activos, 
                $vida_util_anios, $numero_carros, $sueldo_base, $cargas_sociales_porcentaje, 
                $permisos_por_carro, $servicios_por_carro, $otros_fijos, $dias_trabajo, 
                $ingresos_brutos_totales, $ingresos_netos, $costo_variable_total, 
                $margen_bruto, $costos_fijos_totales, $utilidad_antes_impuesto, 
                $iva_a_pagar, $ppm, $flujo_caja_neto
            )";
    
    $result = executeQuery($conn, $sql, "Proyección para {$meses[$mes]} $anio insertada correctamente");
    $results[] = $result;
    
    if ($result['success']) {
        $proyeccion_id = mysqli_insert_id($conn);
        
        // Insertar detalles por carro
        for ($carro = 1; $carro <= $numero_carros; $carro++) {
            // Pequeña variación entre carros
            $variacion = 0.9 + (mt_rand(0, 20) / 100); // Entre 0.9 y 1.1
            $precio_carro = $precio_promedio * $variacion;
            $cantidad_carro = $cantidad_diaria_base * $variacion;
            
            $sql_detalle = "INSERT INTO detalles_proyeccion (
                proyeccion_id, numero_carro, precio_promedio, 
                costo_variable_porcentaje, cantidad_vendida_dia
            ) VALUES (
                $proyeccion_id, $carro, $precio_carro, 
                $costo_variable_porcentaje, $cantidad_carro
            )";
            
            $results[] = executeQuery($conn, $sql_detalle, "Detalle para carro $carro de {$meses[$mes]} $anio insertado correctamente");
        }
    }
}

// Devolver resultados
echo json_encode([
    "message" => "Datos de ejemplo insertados correctamente",
    "results" => $results
]);

mysqli_close($conn);
?>