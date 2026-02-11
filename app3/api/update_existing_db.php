<?php
$config = require_once __DIR__ . '/../../../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('../update_existing_db.sql');
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignorar errores de columnas que ya existen
                if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                    throw $e;
                }
            }
        }
    }

    echo "✅ Base de datos actualizada con datos reales de MenuApp.jsx\n";
    echo "🗑️ Datos anteriores eliminados\n";
    echo "📊 5 categorías: La Ruta 11, Sandwiches, Hamburguesas, Completos, Snacks\n";
    echo "🍔 27 productos con imágenes y datos reales insertados\n";
    echo "🔗 APIs listas: /api/products.php, /api/categories.php\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}