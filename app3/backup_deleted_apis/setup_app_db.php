<?php
$config = require_once __DIR__ . '/../../../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('../setup_app_database.sql');
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            $pdo->exec($statement);
        }
    }

    echo "✅ Base de datos u958525313_app configurada exitosamente\n";
    echo "📊 Tablas creadas: categories, products, ingredients, orders, tuu_payments, admin_users\n";
    echo "👤 Usuario admin creado: admin@laruta11.cl (password: password)\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}