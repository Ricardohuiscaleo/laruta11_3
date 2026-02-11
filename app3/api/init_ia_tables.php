<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]));
}

// SQL para crear las tablas
$sql = "
-- Tabla para almacenar los prompts de IA
CREATE TABLE IF NOT EXISTS ia_prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    contenido TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para almacenar los análisis generados por la IA
CREATE TABLE IF NOT EXISTS ia_analisis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('descriptivo', 'diagnostico', 'predictivo', 'prescriptivo') NOT NULL,
    contenido TEXT NOT NULL,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
";

// Ejecutar la consulta para crear las tablas
if ($conn->query($sql)) {
    // Verificar si ya existe el prompt
    $checkPrompt = "SELECT COUNT(*) as count FROM ia_prompts WHERE nombre = 'analisis_contable'";
    $result = $conn->query($checkPrompt);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insertar el prompt
        $insertPrompt = "
        INSERT INTO ia_prompts (nombre, contenido) VALUES 
        ('analisis_contable', 'Eres un experto en contabilidad en Chile. Analiza los datos del dashboard y proporciona un análisis detallado en lenguaje simple, no técnico, que incluya pros y contras, estrategias recomendadas y aspectos a prever. El análisis debe ser comprensible para personas sin conocimientos técnicos de contabilidad.');
        ";
        
        if (!$conn->query($insertPrompt)) {
            echo json_encode(['success' => false, 'error' => 'Error al crear el prompt: ' . $conn->error]);
            exit;
        }
    }
    
    // Verificar si ya existen análisis
    $checkAnalisis = "SELECT COUNT(*) as count FROM ia_analisis";
    $result = $conn->query($checkAnalisis);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insertar datos de ejemplo para los análisis
        $insertAnalisis = "
        INSERT INTO ia_analisis (tipo, contenido) VALUES 
        ('descriptivo', '<h3>Descripción General del Negocio</h3><p>Tu negocio de sándwiches opera con 2 carros de venta, ofreciendo una variedad de productos con un ticket promedio de $5.500. La estructura de costos muestra un margen bruto promedio del 65%, con costos variables representando el 35% del precio de venta.</p><p>Los productos más vendidos incluyen sándwiches tradicionales chilenos como el Italiano, Barros Luco y Chacarero, complementados con bebidas. El modelo de negocio se basa en ventas directas con un enfoque en calidad y rapidez de servicio.</p>'),
        ('diagnostico', '<h3>Diagnóstico de Situación Actual</h3><p><strong>Fortalezas:</strong> Los costos de ingredientes están bien controlados, representando solo el 35% del precio de venta, lo que es excelente para el sector gastronómico. La estructura operativa es eficiente con bajos costos variables.</p><p><strong>Debilidades:</strong> Los costos fijos parecen elevados en relación a tus ventas actuales, especialmente en arriendo y permisos. El punto de equilibrio requiere un volumen de ventas considerable para ser rentable.</p><p>El flujo de caja mensual es positivo, pero podría optimizarse mejor. La depreciación de tus activos está correctamente calculada, lo que te permite tener una visión real de tu utilidad.</p>'),
        ('predictivo', '<h3>Proyección a Futuro</h3><p>Si mantienes el ritmo actual de ventas, tu negocio alcanzará el punto de equilibrio en aproximadamente 3 meses. <strong>Oportunidades:</strong> Aumentar el volumen de ventas en un 20% mejoraría significativamente tu flujo de caja sin incrementar los costos fijos. <strong>Riesgos:</strong> La inflación podría aumentar el costo de tus ingredientes en los próximos meses, reduciendo tu margen si no ajustas precios.</p><p>Considera que el segundo semestre suele tener un incremento del 15% en ventas de alimentos según datos del sector.</p>'),
        ('prescriptivo', '<h3>Recomendaciones Estratégicas</h3><p><strong>1. Optimización de Costos:</strong> Negocia con proveedores para obtener descuentos por volumen en tus 5 ingredientes más utilizados.</p><p><strong>2. Estrategia de Precios:</strong> Evalúa aumentar el precio de tus productos premium en un 5-8% sin afectar significativamente la demanda.</p><p><strong>3. Gestión Tributaria:</strong> Aprovecha el crédito fiscal del IVA de tus compras para reducir el impuesto a pagar mensualmente.</p><p><strong>4. Flujo de Caja:</strong> Establece un fondo de reserva equivalente a 2 meses de costos fijos para enfrentar temporadas bajas.</p>');
        ";
        
        if (!$conn->query($insertAnalisis)) {
            echo json_encode(['success' => false, 'error' => 'Error al insertar datos de ejemplo: ' . $conn->error]);
            exit;
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Tablas de IA inicializadas correctamente']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al crear las tablas: ' . $conn->error]);
}

$conn->close();
?>