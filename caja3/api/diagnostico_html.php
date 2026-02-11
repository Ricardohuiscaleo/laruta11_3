<?php
require_once '../config.php';

// Función para formatear bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Función para obtener estado de conexión
function getConnectionStatus() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        return ['status' => 'error', 'message' => $conn->connect_error];
    }
    $conn->close();
    return ['status' => 'success', 'message' => 'Conectado correctamente'];
}

// Función para obtener información de tablas
function getTableInfo() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        return [];
    }
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    
    while ($row = $result->fetch_array()) {
        $tableName = $row[0];
        
        // Obtener número de registros
        $countResult = $conn->query("SELECT COUNT(*) as count FROM `$tableName`");
        $count = $countResult->fetch_assoc()['count'];
        
        // Obtener información de la tabla
        $infoResult = $conn->query("SHOW TABLE STATUS LIKE '$tableName'");
        $info = $infoResult->fetch_assoc();
        
        $tables[] = [
            'name' => $tableName,
            'rows' => $count,
            'size' => $info['Data_length'] + $info['Index_length'],
            'engine' => $info['Engine'],
            'created' => $info['Create_time']
        ];
    }
    
    $conn->close();
    return $tables;
}

// Función para obtener variables del sistema
function getSystemVariables() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        return [];
    }
    
    $variables = [];
    $result = $conn->query("SHOW VARIABLES WHERE Variable_name IN ('version', 'version_comment', 'max_connections', 'thread_cache_size', 'table_open_cache', 'innodb_buffer_pool_size')");
    
    while ($row = $result->fetch_assoc()) {
        $variables[$row['Variable_name']] = $row['Value'];
    }
    
    $conn->close();
    return $variables;
}

$connectionStatus = getConnectionStatus();
$tables = getTableInfo();
$systemVars = getSystemVariables();
$timestamp = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico del Sistema - La Ruta 11</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-success { background-color: #10b981; }
        .status-error { background-color: #ef4444; }
        .status-warning { background-color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">🔍 Diagnóstico del Sistema</h1>
                    <p class="text-gray-600 mt-2">Radiografía completa de la base de datos</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Generado el</p>
                    <p class="text-lg font-semibold text-gray-800"><?php echo $timestamp; ?></p>
                </div>
            </div>
        </div>

        <!-- Estado de Conexión -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">📡 Estado de Conexión</h2>
            <div class="flex items-center">
                <span class="status-dot <?php echo $connectionStatus['status'] === 'success' ? 'status-success' : 'status-error'; ?>"></span>
                <span class="text-lg <?php echo $connectionStatus['status'] === 'success' ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo $connectionStatus['message']; ?>
                </span>
            </div>
            
            <?php if ($connectionStatus['status'] === 'success'): ?>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm font-medium text-blue-800">Servidor</p>
                    <p class="text-lg text-blue-600"><?php echo DB_SERVER; ?></p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <p class="text-sm font-medium text-green-800">Base de Datos</p>
                    <p class="text-lg text-green-600"><?php echo DB_NAME; ?></p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <p class="text-sm font-medium text-purple-800">Usuario</p>
                    <p class="text-lg text-purple-600"><?php echo DB_USERNAME; ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($connectionStatus['status'] === 'success'): ?>
        <!-- Variables del Sistema -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">⚙️ Variables del Sistema</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($systemVars as $name => $value): ?>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700"><?php echo ucfirst(str_replace('_', ' ', $name)); ?></span>
                    <span class="text-gray-600 font-mono text-sm"><?php echo $value; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Información de Tablas -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">📊 Tablas de la Base de Datos</h2>
            
            <?php if (empty($tables)): ?>
            <div class="text-center py-8">
                <p class="text-gray-500">No se encontraron tablas en la base de datos</p>
            </div>
            <?php else: ?>
            
            <!-- Resumen -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-blue-600"><?php echo count($tables); ?></p>
                    <p class="text-sm text-blue-800">Total Tablas</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-green-600"><?php echo array_sum(array_column($tables, 'rows')); ?></p>
                    <p class="text-sm text-green-800">Total Registros</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-purple-600"><?php echo formatBytes(array_sum(array_column($tables, 'size'))); ?></p>
                    <p class="text-sm text-purple-800">Tamaño Total</p>
                </div>
                <div class="bg-orange-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-orange-600"><?php echo count(array_unique(array_column($tables, 'engine'))); ?></p>
                    <p class="text-sm text-orange-800">Motores</p>
                </div>
            </div>

            <!-- Tabla detallada -->
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tabla</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registros</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Motor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creada</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tables as $table): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="status-dot status-success"></span>
                                    <span class="font-medium text-gray-900"><?php echo $table['name']; ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo number_format($table['rows']); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo formatBytes($table['size']); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?php echo $table['engine']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo $table['created'] ? date('d/m/Y H:i', strtotime($table['created'])) : 'N/A'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-sm text-gray-500">
                ⚡ Powered by agenterag.com <?php echo date('Y'); ?> | 
                <a href="javascript:window.close()" class="text-blue-600 hover:text-blue-800">Cerrar ventana</a>
            </p>
        </div>
    </div>
</body>
</html>