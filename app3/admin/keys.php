<?php
session_start();

// Verificar si ya está autenticado
$authenticated = isset($_SESSION['keys_admin']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keys - Admin La Ruta 11</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="https://laruta11-images.s3.amazonaws.com/menu/logo.png" />
</head>
<body class="bg-gray-100">
    <?php if (!$authenticated): ?>
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-md">
            <div class="text-center mb-6">
                <img src="https://laruta11-images.s3.amazonaws.com/menu/logo.png" alt="La Ruta 11" class="w-16 h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold text-gray-800">🔐 Acceso a Keys</h1>
                <p class="text-gray-600">Ingresa tus credenciales admin</p>
            </div>
            <form id="loginForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Usuario Admin</label>
                    <input type="text" id="username" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-orange-500" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Contraseña</label>
                    <input type="password" id="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-orange-500" required>
                </div>
                <button type="submit" class="w-full bg-orange-500 text-white py-2 px-4 rounded-lg hover:bg-orange-600 transition-colors font-bold">Acceder</button>
                <div id="error" class="text-red-600 text-sm text-center mt-4" style="display: none;"></div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="max-w-7xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <a href="/admin" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors inline-block mb-4">← Volver al Admin</a>
            <h1 class="text-3xl font-bold text-gray-800">🔑 Gestión de API Keys</h1>
            <p class="text-gray-600 mt-2">Administración segura de claves de API y configuraciones</p>
        </div>
        
        <div id="keys-container">
            <div class="text-center py-10 text-gray-600">Cargando keys...</div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        <?php if (!$authenticated): ?>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('error');
            
            try {
                const response = await fetch('/api/admin/verify_keys_access.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'Error de conexión';
                errorDiv.style.display = 'block';
            }
        });
        <?php else: ?>
        async function loadKeys() {
            try {
                const response = await fetch('/api/admin/get_keys.php');
                const data = await response.json();
                
                if (data.success) {
                    renderKeys(data.keys);
                }
            } catch (error) {
                console.error('Error cargando keys:', error);
            }
        }

        function renderKeys(keys) {
            const container = document.getElementById('keys-container');
            
            const sections = {
                'APIs Externas': ['gemini_api_key', 'unsplash_access_key', 'ruta11_google_maps_api_key'],
                'Google OAuth': ['google_client_id', 'google_client_secret', 'ruta11_google_client_id', 'ruta11_google_client_secret'],
                'TUU Payment': ['tuu_api_key', 'tuu_online_secret', 'tuu_device_serial'],
                'AWS S3': ['aws_access_key_id', 'aws_secret_access_key', 's3_bucket'],
                'Base de Datos': ['app_db_host', 'app_db_name', 'app_db_user', 'app_db_pass'],
                'Credenciales Admin': ['admin_users'],
                'Credenciales Externas': ['external_credentials']
            };
            
            let html = '';
            
            for (const [sectionName, keyNames] of Object.entries(sections)) {
                html += `<div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-blue-500">${sectionName}</h2>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">`;
                
                keyNames.forEach(keyName => {
                    if (keys[keyName]) {
                        if (keyName === 'external_credentials') {
                            // Mostrar credenciales externas por separado
                            const extCreds = keys[keyName];
                            Object.entries(extCreds).forEach(([platform, creds]) => {
                                html += `
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${platform}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="space-y-1">
                                                <div class="text-xs text-gray-600">Plataforma: ${creds.platform}</div>
                                                <div class="key-value blur-sm font-mono bg-gray-100 px-2 py-1 rounded text-xs" id="key-${platform}-email">Email: ${creds.email}</div>
                                                <div class="key-value blur-sm font-mono bg-gray-100 px-2 py-1 rounded text-xs" id="key-${platform}-password">Password: ${creds.password}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs mr-2 mb-1" onclick="toggleExternal('${platform}')">👁️ Mostrar</button>
                                            <button class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs" onclick="copyExternal('${platform}')">📋 Copiar</button>
                                        </td>
                                    </tr>`;
                            });
                        } else {
                            const value = typeof keys[keyName] === 'object' ? JSON.stringify(keys[keyName], null, 2) : keys[keyName];
                            html += `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${keyName}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="key-value blur-sm font-mono bg-gray-100 px-2 py-1 rounded text-xs max-w-xs overflow-hidden" id="key-${keyName}">${value}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs mr-2" onclick="toggleKey('${keyName}')">👁️ Mostrar</button>
                                        <button class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs" onclick="copyKey('${keyName}')">📋 Copiar</button>
                                    </td>
                                </tr>`;
                        }
                    }
                });
                
                html += `</tbody></table></div></div></div>`;
            }
            
            container.innerHTML = html;
        }

        function toggleKey(keyName) {
            const keyElement = document.getElementById(`key-${keyName}`);
            const button = event.target;
            
            if (keyElement.classList.contains('blur-sm')) {
                keyElement.classList.remove('blur-sm');
                button.textContent = '🙈 Ocultar';
                button.className = 'bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-xs mr-2';
            } else {
                keyElement.classList.add('blur-sm');
                button.textContent = '👁️ Mostrar';
                button.className = 'bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs mr-2';
            }
        }

        function copyKey(keyName) {
            const keyElement = document.getElementById(`key-${keyName}`);
            navigator.clipboard.writeText(keyElement.textContent).then(() => {
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '✅ Copiado';
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            });
        }

        function toggleExternal(platform) {
            const emailElement = document.getElementById(`key-${platform}-email`);
            const passwordElement = document.getElementById(`key-${platform}-password`);
            const button = event.target;
            
            if (emailElement.classList.contains('blur-sm')) {
                emailElement.classList.remove('blur-sm');
                passwordElement.classList.remove('blur-sm');
                button.textContent = '🙈 Ocultar';
                button.className = 'bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-xs mr-2 mb-1';
            } else {
                emailElement.classList.add('blur-sm');
                passwordElement.classList.add('blur-sm');
                button.textContent = '👁️ Mostrar';
                button.className = 'bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs mr-2 mb-1';
            }
        }

        function copyExternal(platform) {
            const emailElement = document.getElementById(`key-${platform}-email`);
            const passwordElement = document.getElementById(`key-${platform}-password`);
            const email = emailElement.textContent.replace('Email: ', '');
            const password = passwordElement.textContent.replace('Password: ', '');
            const copyText = `Email: ${email}\nPassword: ${password}`;
            
            navigator.clipboard.writeText(copyText).then(() => {
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '✅ Copiado';
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            });
        }

        loadKeys();
        <?php endif; ?>
    </script>
</body>
</html>