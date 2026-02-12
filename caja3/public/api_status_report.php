<?php
header('Content-Type: text/html; charset=utf-8');

// Escanear todas las APIs
function scanAPIs($dir) {
    $apis = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = str_replace(__DIR__ . '/', '', $file->getPathname());
            $apis[] = $path;
        }
    }
    return $apis;
}

$apis = scanAPIs(__DIR__ . '/api');
$total = count($apis);

// Verificar cuáles cargan config
$withConfig = 0;
$withoutConfig = 0;
$critical = [];

foreach ($apis as $api) {
    $content = @file_get_contents(__DIR__ . '/' . $api);
    if ($content && (strpos($content, 'config.php') !== false || strpos($content, 'config_loader.php') !== false)) {
        $withConfig++;
        
        // Identificar APIs críticas
        if (strpos($api, 'check_config') !== false ||
            strpos($api, 'callback') !== false ||
            strpos($api, 'get_productos') !== false ||
            strpos($api, 'get_ingredientes') !== false ||
            strpos($api, 'get_pending_orders') !== false) {
            $critical[] = $api;
        }
    } else {
        $withoutConfig++;
    }
}

$percentage = $total > 0 ? round(($withConfig / $total) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de APIs - CAJA3 (Dinámico)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; }
        .stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-number { font-size: 36px; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 14px; opacity: 0.9; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 20px; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        .api-list { display: grid; gap: 8px; max-height: 600px; overflow-y: auto; }
        .api-item { display: flex; align-items: center; padding: 10px; background: #f8f9fa; border-radius: 6px; font-size: 13px; }
        .api-item .icon { margin-right: 10px; font-size: 16px; }
        .api-item .name { flex: 1; font-family: 'Courier New', monospace; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-badge.ok { background: #d4edda; color: #155724; }
        .status-badge.error { background: #f8d7da; color: #721c24; }
        .progress-bar { width: 100%; height: 30px; background: #e9ecef; border-radius: 15px; overflow: hidden; margin: 20px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; transition: width 0.3s; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-right: 10px; }
        .btn:hover { background: #5568d3; }
        .timestamp { text-align: center; color: #999; margin-top: 30px; font-size: 14px; }
        .refresh-btn { background: #28a745; cursor: pointer; border: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Estado de APIs - CAJA3 (Dinámico)</h1>
        <p class="subtitle">Reporte en tiempo real</p>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total APIs</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php echo $withConfig; ?></div>
                <div class="stat-label">✓ Con Config</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $withoutConfig; ?></div>
                <div class="stat-label">⚠ Sin Config</div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"><?php echo $percentage; ?>% OK</div>
        </div>

        <div class="section">
            <h2 class="section-title">✅ APIs Críticas (<?php echo count($critical); ?>)</h2>
            <div class="api-list">
                <?php foreach ($critical as $api): ?>
                <div class="api-item">
                    <span class="icon">✓</span>
                    <span class="name">/<?php echo htmlspecialchars($api); ?></span>
                    <span class="status-badge ok">OK</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">📁 Todas las APIs (<?php echo $total; ?>)</h2>
            <div class="api-list">
                <?php foreach ($apis as $api): 
                    $content = @file_get_contents(__DIR__ . '/' . $api);
                    $hasConfig = $content && (strpos($content, 'config.php') !== false || strpos($content, 'config_loader.php') !== false);
                ?>
                <div class="api-item">
                    <span class="icon"><?php echo $hasConfig ? '✓' : '⚠'; ?></span>
                    <span class="name">/<?php echo htmlspecialchars($api); ?></span>
                    <span class="status-badge <?php echo $hasConfig ? 'ok' : 'error'; ?>">
                        <?php echo $hasConfig ? 'OK' : 'No config'; ?>
                    </span>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <div class="section">
            <h2 class="section-title">🔧 Herramientas</h2>
            <a href="/api_health_check.php" class="btn" target="_blank">Health Check</a>
            <a href="/verify_migration.php" class="btn" target="_blank">Verificar Migración</a>
            <a href="/api/check_config.php" class="btn" target="_blank">Check Config</a>
            <button class="btn refresh-btn" onclick="location.reload()">🔄 Actualizar</button>
        </div>

        <div class="timestamp">
            Generado: <?php echo date('Y-m-d H:i:s'); ?> | 
            Escaneo en tiempo real
        </div>
    </div>
</body>
</html>
