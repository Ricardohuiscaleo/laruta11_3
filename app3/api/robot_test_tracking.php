<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🤖 Robot Test Tracking</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .test-btn { padding: 12px 24px; margin: 10px; background: #007cba; color: white; border: none; cursor: pointer; border-radius: 5px; }
        .test-btn:hover { background: #005a8b; }
        .result { margin: 10px 0; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .loading { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007cba; }
        .stat-label { color: #666; font-size: 0.9em; }
        #robot-status { position: fixed; top: 20px; right: 20px; padding: 10px; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Robot Test de Tracking</h1>
        <p>Este robot simula un usuario real navegando por la app y verifica si el tracking funciona.</p>
        
        <div id="robot-status" class="loading">🤖 Robot Inactivo</div>
        
        <button class="test-btn" onclick="runRobotTest()">🚀 Ejecutar Robot Test</button>
        <button class="test-btn" onclick="checkCurrentData()">📊 Ver Datos Actuales</button>
        <button class="test-btn" onclick="clearResults()">🗑️ Limpiar Resultados</button>
        
        <div class="stats" id="stats-container" style="display: none;">
            <div class="stat-card">
                <div class="stat-number" id="visits-count">0</div>
                <div class="stat-label">Visitas Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="interactions-count">0</div>
                <div class="stat-label">Interacciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="products-count">0</div>
                <div class="stat-label">Productos Vistos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="journey-count">0</div>
                <div class="stat-label">Journey Records</div>
            </div>
        </div>
        
        <div id="results"></div>
    </div>
    
    <script>
        let robotRunning = false;
        
        function updateStatus(message, type = 'loading') {
            const status = document.getElementById('robot-status');
            status.textContent = message;
            status.className = type;
        }
        
        function addResult(message, type = 'info') {
            const div = document.createElement('div');
            div.className = 'result ' + type;
            div.innerHTML = `<strong>${new Date().toLocaleTimeString()}</strong>: ${message}`;
            document.getElementById('results').appendChild(div);
            div.scrollIntoView({ behavior: 'smooth' });
        }
        
        function clearResults() {
            document.getElementById('results').innerHTML = '';
        }
        
        async function checkCurrentData() {
            try {
                const response = await fetch('/api/check_tracking_data.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('stats-container').style.display = 'grid';
                    document.getElementById('visits-count').textContent = data.data.visits_today;
                    document.getElementById('interactions-count').textContent = data.data.interactions_today;
                    document.getElementById('products-count').textContent = data.data.products_with_views;
                    document.getElementById('journey-count').textContent = data.data.journey_today;
                    
                    addResult(`📊 Datos actuales: ${data.data.visits_today} visitas, ${data.data.interactions_today} interacciones, ${data.data.products_with_views} productos con vistas`, 'info');
                } else {
                    addResult(`❌ Error obteniendo datos: ${data.error}`, 'error');
                }
            } catch (error) {
                addResult(`❌ Error de conexión: ${error.message}`, 'error');
            }
        }
        
        async function simulateUserBehavior() {
            const sessionId = Date.now() + '-robot-' + Math.random().toString(36).substr(2, 9);
            
            // 1. Simular visita inicial
            addResult('🏠 Simulando visita inicial...', 'info');
            await fetch('/api/app/track_visit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: sessionId,
                    page_url: 'https://app.laruta11.cl',
                    latitude: -33.4489,
                    longitude: -70.6693,
                    screen_resolution: '1920x1080',
                    viewport_size: '1200x800',
                    timezone: 'America/Santiago',
                    language: 'es-CL',
                    platform: 'MacIntel'
                })
            });
            
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // 2. Simular vista de productos
            const products = [
                { id: 101, name: 'Tomahawk Papa' },
                { id: 1, name: 'Churrasco Vacuno' },
                { id: 5, name: 'Completo Tradicional' },
                { id: 12, name: 'Papas Fritas' }
            ];
            
            for (const product of products) {
                addResult(`👀 Viendo producto: ${product.name}`, 'info');
                await fetch('/api/app/track_interaction.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        action_type: 'view',
                        element_type: 'product',
                        product_id: product.id,
                        product_name: product.name,
                        page_url: 'https://app.laruta11.cl'
                    })
                });
                
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Simular click en producto
                addResult(`👆 Haciendo click en: ${product.name}`, 'info');
                await fetch('/api/app/track_interaction.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        action_type: 'click',
                        element_type: 'product_image',
                        product_id: product.id,
                        product_name: product.name,
                        element_text: 'Product Image Click',
                        page_url: 'https://app.laruta11.cl'
                    })
                });
                
                await new Promise(resolve => setTimeout(resolve, 300));
            }
            
            // 3. Simular agregar al carrito
            const selectedProduct = products[Math.floor(Math.random() * products.length)];
            addResult(`🛒 Agregando al carrito: ${selectedProduct.name}`, 'info');
            await fetch('/api/app/track_interaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: sessionId,
                    action_type: 'add_to_cart',
                    element_type: 'product',
                    product_id: selectedProduct.id,
                    product_name: selectedProduct.name,
                    page_url: 'https://app.laruta11.cl'
                })
            });
            
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // 4. Simular journey/navegación
            addResult('🗺️ Simulando navegación y tiempo en página...', 'info');
            await fetch('/api/app/track_journey.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: sessionId,
                    page_url: 'https://app.laruta11.cl',
                    time_spent: Math.floor(Math.random() * 120) + 30, // 30-150 segundos
                    scroll_depth: Math.floor(Math.random() * 100) + 1, // 1-100%
                    exit_page: false
                })
            });
            
            return sessionId;
        }
        
        async function runRobotTest() {
            if (robotRunning) {
                addResult('⚠️ Robot ya está ejecutándose', 'error');
                return;
            }
            
            robotRunning = true;
            updateStatus('🤖 Robot Ejecutándose...', 'loading');
            
            try {
                // Obtener datos antes del test
                addResult('📊 Obteniendo datos iniciales...', 'info');
                const beforeResponse = await fetch('/api/check_tracking_data.php');
                const beforeData = await beforeResponse.json();
                
                if (!beforeData.success) {
                    throw new Error('No se pudieron obtener datos iniciales');
                }
                
                const initialCounts = {
                    visits: beforeData.data.visits_today,
                    interactions: beforeData.data.interactions_today,
                    journey: beforeData.data.journey_today,
                    products: beforeData.data.products_with_views
                };
                
                addResult(`📈 Datos iniciales: ${initialCounts.visits} visitas, ${initialCounts.interactions} interacciones`, 'info');
                
                // Ejecutar simulación
                addResult('🚀 Iniciando simulación de usuario...', 'info');
                const sessionId = await simulateUserBehavior();
                
                // Esperar un poco para que se procesen los datos
                addResult('⏳ Esperando procesamiento de datos...', 'info');
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Verificar datos después del test
                addResult('🔍 Verificando resultados...', 'info');
                const afterResponse = await fetch('/api/check_tracking_data.php');
                const afterData = await afterResponse.json();
                
                if (!afterData.success) {
                    throw new Error('No se pudieron obtener datos finales');
                }
                
                const finalCounts = {
                    visits: afterData.data.visits_today,
                    interactions: afterData.data.interactions_today,
                    journey: afterData.data.journey_today,
                    products: afterData.data.products_with_views
                };
                
                // Calcular diferencias
                const differences = {
                    visits: finalCounts.visits - initialCounts.visits,
                    interactions: finalCounts.interactions - initialCounts.interactions,
                    journey: finalCounts.journey - initialCounts.journey,
                    products: finalCounts.products - initialCounts.products
                };
                
                // Mostrar resultados
                addResult(`📊 Resultados finales: ${finalCounts.visits} visitas (+${differences.visits}), ${finalCounts.interactions} interacciones (+${differences.interactions})`, 'info');
                
                // Verificar si el tracking funciona
                if (differences.visits > 0 && differences.interactions > 0) {
                    addResult('✅ ¡TRACKING FUNCIONANDO CORRECTAMENTE! Se registraron nuevas visitas e interacciones.', 'success');
                    updateStatus('✅ Robot Completado - Tracking OK', 'success');
                } else if (differences.visits > 0) {
                    addResult('⚠️ TRACKING PARCIAL: Se registran visitas pero no interacciones.', 'error');
                    updateStatus('⚠️ Robot Completado - Tracking Parcial', 'error');
                } else {
                    addResult('❌ TRACKING NO FUNCIONA: No se registraron datos nuevos.', 'error');
                    updateStatus('❌ Robot Completado - Tracking Falla', 'error');
                }
                
                // Actualizar estadísticas
                checkCurrentData();
                
                addResult(`🔗 Sesión del robot: ${sessionId}`, 'info');
                
            } catch (error) {
                addResult(`❌ Error en robot test: ${error.message}`, 'error');
                updateStatus('❌ Robot Error', 'error');
            } finally {
                robotRunning = false;
            }
        }
        
        // Cargar datos iniciales
        checkCurrentData();
    </script>
</body>
</html>