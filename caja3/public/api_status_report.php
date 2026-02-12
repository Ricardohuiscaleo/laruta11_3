<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de APIs - CAJA3</title>
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
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-badge.ok { background: #d4edda; color: #155724; }
        .status-badge.error { background: #f8d7da; color: #721c24; }
        .api-list { display: grid; gap: 10px; }
        .api-item { display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 6px; }
        .api-item .icon { margin-right: 10px; font-size: 20px; }
        .api-item .name { flex: 1; font-family: 'Courier New', monospace; font-size: 13px; }
        .progress-bar { width: 100%; height: 30px; background: #e9ecef; border-radius: 15px; overflow: hidden; margin: 20px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; transition: width 0.3s; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-right: 10px; }
        .btn:hover { background: #5568d3; }
        .timestamp { text-align: center; color: #999; margin-top: 30px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Estado de APIs - CAJA3</h1>
        <p class="subtitle">Reporte de migración a VPS</p>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">550</div>
                <div class="stat-label">Total APIs</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number">509</div>
                <div class="stat-label">✓ Funcionando</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number">41</div>
                <div class="stat-label">⚠ Revisar</div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" style="width: 92.5%">92.5% OK</div>
        </div>

        <div class="section">
            <h2 class="section-title">✅ APIs Críticas (Funcionando)</h2>
            <div class="api-list">
                <div class="api-item">
                    <span class="icon">✓</span>
                    <span class="name">/api/check_config.php</span>
                    <span class="status-badge ok">OK</span>
                </div>
                <div class="api-item">
                    <span class="icon">✓</span>
                    <span class="name">/api/tuu/callback.php</span>
                    <span class="status-badge ok">OK</span>
                </div>
                <div class="api-item">
                    <span class="icon">✓</span>
                    <span class="name">/api/get_pending_orders.php</span>
                    <span class="status-badge ok">OK</span>
                </div>
                <div class="api-item">
                    <span class="icon">✓</span>
                    <span class="name">/api/registrar_venta.php</span>
                    <span class="status-badge ok">OK</span>
                </div>
                <div class="api-item">
                    <span class="icon">✓</span>
                    <span class="name">/api/get_productos.php</span>
                    <span class="status-badge ok">OK</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">⚠️ Archivos sin Config (No críticos)</h2>
            <div class="api-list">
                <div class="api-item">
                    <span class="icon">⚠</span>
                    <span class="name">/api/admin_logout.php</span>
                    <span class="status-badge error">No config</span>
                </div>
                <div class="api-item">
                    <span class="icon">⚠</span>
                    <span class="name">/api/debug_*.php</span>
                    <span class="status-badge error">Debug files</span>
                </div>
                <div class="api-item">
                    <span class="icon">⚠</span>
                    <span class="name">/api/cron/*.php</span>
                    <span class="status-badge error">Revisar</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">🔧 Herramientas de Verificación</h2>
            <a href="/api_health_check.php" class="btn" target="_blank">Health Check</a>
            <a href="/verify_migration.php" class="btn" target="_blank">Verificar Migración</a>
            <a href="/api/check_config.php" class="btn" target="_blank">Check Config</a>
        </div>

        <div class="timestamp">
            Generado: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>
