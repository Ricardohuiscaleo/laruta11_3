<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    $connectionError = $conn->connect_error;
    $connected = false;
} else {
    $connected = true;
    $connectionError = null;
}

$timestamp = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Diagnóstico del Sistema - La Ruta 11</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
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

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">📡 Estado de Conexión</h2>
            <div class="flex items-center">
                <div class="w-3 h-3 rounded-full mr-3 <?php echo $connected ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                <span class="text-lg <?php echo $connected ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo $connected ? 'Conectado correctamente' : 'Error: ' . $connectionError; ?>
                </span>
            </div>
            
            <?php if ($connected): ?>
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

        <?php if ($connected): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">📊 Información de la Base de Datos</h2>
            
            <?php
            $tables = [];
            $result = $conn->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $tableName = $row[0];
                    $countResult = $conn->query("SELECT COUNT(*) as count FROM `$tableName`");
                    $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
                    $tables[] = ['name' => $tableName, 'rows' => $count];
                }
            }
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-blue-600"><?php echo count($tables); ?></p>
                    <p class="text-sm text-blue-800">Total Tablas</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-green-600"><?php echo array_sum(array_column($tables, 'rows')); ?></p>
                    <p class="text-sm text-green-800">Total Registros</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-purple-600">MySQL</p>
                    <p class="text-sm text-purple-800">Motor BD</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tabla</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registros</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tables as $table): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                    <span class="font-medium text-gray-900"><?php echo $table['name']; ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo number_format($table['rows']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-500">
                ⚡ Powered by agenterag.com <?php echo date('Y'); ?> | 
                <a href="javascript:window.close()" class="text-blue-600 hover:text-blue-800">Cerrar ventana</a>
            </p>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>