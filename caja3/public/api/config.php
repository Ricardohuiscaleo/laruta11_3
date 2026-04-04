<?php
// Configuración de DB leyendo variables de entorno de Coolify
// En producción Coolify inyecta estas vars automáticamente
return [
    'db_host' => getenv('APP_DB_HOST') ?: 'zs00occ8kcks40w4c88ogo08',
    'db_name' => getenv('APP_DB_NAME') ?: 'laruta11',
    'db_user' => getenv('APP_DB_USER') ?: 'laruta11_user',
    'db_pass' => getenv('APP_DB_PASS') ?: 'CCoonn22kk11@',
    'app_db_host' => getenv('APP_DB_HOST') ?: 'zs00occ8kcks40w4c88ogo08',
    'app_db_name' => getenv('APP_DB_NAME') ?: 'laruta11',
    'app_db_user' => getenv('APP_DB_USER') ?: 'laruta11_user',
    'app_db_pass' => getenv('APP_DB_PASS') ?: 'CCoonn22kk11@',
    'caja_users' => [
        'admin'  => 'R11adm2025x7k9',
        'cajera' => 'ruta11caja'
    ],
    'admin_users' => [
        'admin'   => 'R11adm2025x7k9',
        'ricardo' => 'Ric4rd0R11x2025',
        'manager' => 'Mgr11x2025k7p',
        'ruta11'  => '@la_ruta_11'
    ],
];
