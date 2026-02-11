<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Analytics System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-btn { padding: 10px 20px; margin: 10px; background: #007cba; color: white; border: none; cursor: pointer; }
        .result { margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>Test Analytics System</h1>
    
    <button class="test-btn" onclick="testInteraction()">Test User Interaction</button>
    <button class="test-btn" onclick="testJourney()">Test User Journey</button>
    <button class="test-btn" onclick="testProductView()">Test Product View</button>
    <button class="test-btn" onclick="testAddToCart()">Test Add to Cart</button>
    
    <div id="results"></div>
    
    <script>
        const sessionId = Date.now() + '-test';
        
        function addResult(message, isSuccess = true) {
            const div = document.createElement('div');
            div.className = 'result ' + (isSuccess ? 'success' : 'error');
            div.textContent = new Date().toLocaleTimeString() + ': ' + message;
            document.getElementById('results').appendChild(div);
        }
        
        async function testInteraction() {
            try {
                const response = await fetch('/api/app/track_interaction.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        action_type: 'click',
                        element_type: 'button',
                        element_id: 'test-button',
                        element_text: 'Test Button Click',
                        page_url: window.location.href
                    })
                });
                
                const result = await response.json();
                addResult('Interaction: ' + (result.success ? 'SUCCESS' : 'FAILED - ' + result.error), result.success);
            } catch (error) {
                addResult('Interaction ERROR: ' + error.message, false);
            }
        }
        
        async function testJourney() {
            try {
                const response = await fetch('/api/app/track_journey.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        page_url: window.location.href,
                        time_spent: 30,
                        scroll_depth: 75,
                        exit_page: false
                    })
                });
                
                const result = await response.json();
                addResult('Journey: ' + (result.success ? 'SUCCESS' : 'FAILED - ' + result.error), result.success);
            } catch (error) {
                addResult('Journey ERROR: ' + error.message, false);
            }
        }
        
        async function testProductView() {
            try {
                const response = await fetch('/api/app/track_interaction.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        action_type: 'view',
                        element_type: 'product',
                        product_id: 1,
                        product_name: 'Completo Italiano',
                        page_url: window.location.href
                    })
                });
                
                const result = await response.json();
                addResult('Product View: ' + (result.success ? 'SUCCESS' : 'FAILED - ' + result.error), result.success);
            } catch (error) {
                addResult('Product View ERROR: ' + error.message, false);
            }
        }
        
        async function testAddToCart() {
            try {
                const response = await fetch('/api/app/track_interaction.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sessionId,
                        action_type: 'add_to_cart',
                        element_type: 'product',
                        product_id: 1,
                        product_name: 'Completo Italiano',
                        page_url: window.location.href
                    })
                });
                
                const result = await response.json();
                addResult('Add to Cart: ' + (result.success ? 'SUCCESS' : 'FAILED - ' + result.error), result.success);
            } catch (error) {
                addResult('Add to Cart ERROR: ' + error.message, false);
            }
        }
    </script>
</body>
</html>