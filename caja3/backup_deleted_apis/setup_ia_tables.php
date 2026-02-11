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
    tipo ENUM('descriptivo', 'predictivo', 'prescriptivo') NOT NULL,
    contenido TEXT NOT NULL,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Verificar si ya existe el prompt
SELECT COUNT(*) as count FROM ia_prompts WHERE nombre = 'analisis_contable';
";

// Ejecutar la consulta para crear las tablas
if ($conn->multi_query($sql)) {
    // Procesar resultados
    do {
        if ($result = $conn->store_result()) {
            $row = $result->fetch_assoc();
            $result->free();
            
            // Si es el resultado del SELECT, verificamos si ya existe el prompt
            if (isset($row['count'])) {
                $promptExists = $row['count'] > 0;
                
                // Si no existe el prompt, lo insertamos
                if (!$promptExists) {
                    $insertPrompt = "
                    INSERT INTO ia_prompts (nombre, contenido) VALUES 
                    ('analisis_contable', 'Eres un experto en contabilidad en Chile. Analiza los datos del dashboard y proporciona un análisis detallado en lenguaje simple, no técnico, que incluya pros y contras, estrategias recomendadas y aspectos a prever. El análisis debe ser comprensible para personas sin conocimientos técnicos de contabilidad.');
                    ";
                    
                    if ($conn->query($insertPrompt)) {
                        echo json_encode(['success' => true, 'message' => 'Prompt creado correctamente']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Error al crear el prompt: ' . $conn->error]);
                    }
                } else {
                    echo json_encode(['success' => true, 'message' => 'Las tablas ya existen y el prompt ya está configurado']);
                }
            }
        }
    } while ($conn->next_result());
    
    // Insertar datos de ejemplo para los análisis si no existen
    $checkAnalisis = "SELECT COUNT(*) as count FROM ia_analisis";
    $result = $conn->query($checkAnalisis);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $insertAnalisis = "
        INSERT INTO ia_analisis (tipo, contenido) VALUES 
        ('descriptivo', '<h3>Análisis Actual del Negocio</h3><p>Tu negocio de sándwiches muestra un margen bruto promedio del 65%, lo que es muy positivo para el sector gastronómico. <strong>Pros:</strong> Los costos de ingredientes están bien controlados, representando solo el 35% del precio de venta. <strong>Contras:</strong> Los costos fijos parecen elevados en relación a tus ventas actuales, especialmente en arriendo y permisos.</p><p>El flujo de caja mensual es positivo, pero podría optimizarse mejor. La depreciación de tus activos está correctamente calculada, lo que te permite tener una visión real de tu utilidad.</p>'),
        ('predictivo', '<h3>Proyección a Futuro</h3><p>Si mantienes el ritmo actual de ventas, tu negocio alcanzará el punto de equilibrio en aproximadamente 3 meses. <strong>Oportunidades:</strong> Aumentar el volumen de ventas en un 20% mejoraría significativamente tu flujo de caja sin incrementar los costos fijos. <strong>Riesgos:</strong> La inflación podría aumentar el costo de tus ingredientes en los próximos meses, reduciendo tu margen si no ajustas precios.</p><p>Considera que el segundo semestre suele tener un incremento del 15% en ventas de alimentos según datos del sector.</p>'),
        ('prescriptivo', '<h3>Recomendaciones Estratégicas</h3><p><strong>1. Optimización de Costos:</strong> Negocia con proveedores para obtener descuentos por volumen en tus 5 ingredientes más utilizados.</p><p><strong>2. Estrategia de Precios:</strong> Evalúa aumentar el precio de tus productos premium en un 5-8% sin afectar significativamente la demanda.</p><p><strong>3. Gestión Tributaria:</strong> Aprovecha el crédito fiscal del IVA de tus compras para reducir el impuesto a pagar mensualmente.</p><p><strong>4. Flujo de Caja:</strong> Establece un fondo de reserva equivalente a 2 meses de costos fijos para enfrentar temporadas bajas.</p>');
        ";
        
        if ($conn->query($insertAnalisis)) {
            echo json_encode(['success' => true, 'message' => 'Tablas creadas y datos de ejemplo insertados correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al insertar datos de ejemplo: ' . $conn->error]);
        }
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al crear las tablas: ' . $conn->error]);
}

$conn->close();
?>