<?php
header('Content-Type: application/json');

// Buscar config.php
$configPaths = ['../config.php', '../../config.php', '../../../config.php', '../../../../config.php'];
$configFound = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config = require $path;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['success' => false, 'error' => 'No se pudo encontrar config.php']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TEST SUBCATEGORÍAS COMBOS ===\n\n";
    
    // 1. Verificar que existe la categoría Combos
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = 8");
    $stmt->execute();
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "1. Categoría Combos (ID: 8):\n";
    if ($category) {
        echo "   ✓ Existe: " . $category['name'] . " (activa: " . $category['is_active'] . ")\n";
    } else {
        echo "   ✗ No existe la categoría con ID 8\n";
    }
    echo "\n";
    
    // 2. Verificar subcategorías para Combos
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = 8");
    $stmt->execute();
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "2. Subcategorías para Combos:\n";
    if (count($subcategories) > 0) {
        foreach ($subcategories as $sub) {
            echo "   - ID: {$sub['id']}, Nombre: {$sub['name']}, Activa: {$sub['is_active']}\n";
        }
    } else {
        echo "   ✗ No hay subcategorías para la categoría Combos\n";
    }
    echo "\n";
    
    // 3. Verificar la consulta exacta que usa get_subcategories.php
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = ? AND is_active = 1 ORDER BY sort_order, name");
    $stmt->execute([8]);
    $activeSubcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "3. Subcategorías activas para Combos (consulta exacta):\n";
    if (count($activeSubcategories) > 0) {
        foreach ($activeSubcategories as $sub) {
            echo "   ✓ ID: {$sub['id']}, Nombre: {$sub['name']}, Slug: {$sub['slug']}\n";
        }
    } else {
        echo "   ✗ No hay subcategorías activas para la categoría Combos\n";
    }
    echo "\n";
    
    // 4. Verificar todas las categorías
    $stmt = $pdo->query("SELECT id, name, is_active FROM categories ORDER BY id");
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "4. Todas las categorías:\n";
    foreach ($allCategories as $cat) {
        echo "   - ID: {$cat['id']}, Nombre: {$cat['name']}, Activa: {$cat['is_active']}\n";
    }
    echo "\n";
    
    // 5. Verificar todas las subcategorías
    $stmt = $pdo->query("SELECT s.*, c.name as category_name FROM subcategories s JOIN categories c ON s.category_id = c.id ORDER BY s.category_id, s.id");
    $allSubcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "5. Todas las subcategorías:\n";
    foreach ($allSubcategories as $sub) {
        echo "   - ID: {$sub['id']}, Categoría: {$sub['category_name']} ({$sub['category_id']}), Nombre: {$sub['name']}, Activa: {$sub['is_active']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error de base de datos: " . $e->getMessage() . "\n";
}
?>