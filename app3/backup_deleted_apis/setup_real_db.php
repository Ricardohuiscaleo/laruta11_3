<?php
$config = require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('../setup_app_database_real.sql');
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            $pdo->exec($statement);
        }
    }

    echo "✅ Base de datos u958525313_app configurada con datos reales de MenuApp.jsx\n";
    echo "📊 Categorías: La Ruta 11, Sandwiches, Hamburguesas, Completos, Snacks\n";
    echo "🍔 Productos: 27 productos con imágenes y datos reales\n";
    echo "👤 Admin: admin@laruta11.cl / password\n";
    echo "🔗 APIs: products.php, categories.php, admin_dashboard.php\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}