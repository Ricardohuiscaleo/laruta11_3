<?php
// Configuración de DB leyendo variables de entorno de Coolify
// En producción Coolify inyecta estas vars automáticamente
return [
    'db_host' => getenv('APP_DB_HOST') ?: 'zs00occ8kcks40w4c88ogo08',
    'db_name' => getenv('APP_DB_NAME') ?: 'laruta11',
    'db_user' => getenv('APP_DB_USER') ?: 'laruta11_user',
    'db_pass' => getenv('APP_DB_PASS') ?: 'CCoonn22kk11@',
];
