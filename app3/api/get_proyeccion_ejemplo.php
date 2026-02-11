<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Crear datos de ejemplo para la proyección
$proyeccion = [
    "id" => "1",
    "nombre" => "Proyección Mensual",
    "fecha_creacion" => date("Y-m-d H:i:s"),
    "fecha_formateada" => date("d/m/Y"),
    "ingresos_brutos" => 10944000,
    "ingresos_netos" => 9196639,
    "costo_variable" => 2626560,
    "margen_bruto" => 6570079,
    "costos_fijos" => 4500000,
    "depreciacion" => 209708,
    "utilidad_antes_impuesto" => 1860371,
    "provision_impuesto" => 465093,
    "utilidad_neta" => 1395278,
    "iva_pagar" => 1579383,
    "ppm" => 22992,
    "flujo_caja" => 3231383,
    "diario" => [
        "ingresos" => 456000,
        "margen" => 273753,
        "utilidad" => 77515,
        "flujo" => 134641
    ],
    "anual" => [
        "ingresos" => 131328000,
        "margen" => 78840944,
        "utilidad" => 22324458,
        "flujo" => 38776602
    ],
    "detalles_carros" => [
        [
            "carro_id" => "1",
            "precio_promedio" => 5500,
            "costo_variable" => 40,
            "cantidad_vendida" => 35,
            "cantidad_vendida_dia" => 35
        ]
    ]
];

// Devolver respuesta
echo json_encode([
    "success" => true,
    "proyecciones" => [$proyeccion]
]);
?>