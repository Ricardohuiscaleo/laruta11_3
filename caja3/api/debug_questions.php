<?php
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Debug API Questions</h2>";

// Buscar config.php
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

echo "<h3>1. Buscando config.php:</h3>";
$config_found = false;
foreach ($config_paths as $path) {
    echo "Probando: $path - ";
    if (file_exists($path)) {
        echo "✅ ENCONTRADO<br>";
        include $path;
        $config_found = true;
        break;
    } else {
        echo "❌ No existe<br>";
    }
}

if (!$config_found) {
    echo "<strong>❌ ERROR: Config no encontrado</strong>";
    exit;
}

echo "<h3>2. Verificando conexión DB:</h3>";
if (isset($pdo)) {
    echo "✅ PDO disponible<br>";
} elseif (isset($conn)) {
    echo "✅ MySQLi disponible<br>";
} else {
    echo "❌ No hay conexión DB<br>";
    exit;
}

echo "<h3>3. Verificando tabla quality_questions:</h3>";
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SHOW TABLES LIKE 'quality_questions'");
        $exists = $stmt->rowCount() > 0;
    } else {
        $result = $conn->query("SHOW TABLES LIKE 'quality_questions'");
        $exists = $result->num_rows > 0;
    }
    
    if ($exists) {
        echo "✅ Tabla existe<br>";
        
        // Contar registros
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM quality_questions");
            $count = $stmt->fetch()['total'];
        } else {
            $result = $conn->query("SELECT COUNT(*) as total FROM quality_questions");
            $count = $result->fetch_assoc()['total'];
        }
        echo "📊 Total registros: $count<br>";
        
        // Mostrar algunos registros
        echo "<h4>Primeros 5 registros:</h4>";
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT * FROM quality_questions LIMIT 5");
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = $conn->query("SELECT * FROM quality_questions LIMIT 5");
            $questions = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        echo "<pre>";
        print_r($questions);
        echo "</pre>";
        
    } else {
        echo "❌ Tabla no existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Test API directo:</h3>";
$roles = ['planchero', 'cajero'];
foreach ($roles as $role) {
    echo "<h4>Role: $role</h4>";
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM quality_questions WHERE role = ? AND active = 1 ORDER BY order_index");
            $stmt->execute([$role]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare("SELECT * FROM quality_questions WHERE role = ? AND active = 1 ORDER BY order_index");
            $stmt->bind_param("s", $role);
            $stmt->execute();
            $result = $stmt->get_result();
            $questions = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        echo "Encontradas: " . count($questions) . " preguntas<br>";
        echo "<pre>";
        print_r($questions);
        echo "</pre>";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
}
