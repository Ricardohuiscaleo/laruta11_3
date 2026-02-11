#!/bin/bash
# Script para corregir rutas de config en carpetas app y users

echo "🔧 Corrigiendo rutas de config..."

# Corregir archivos en users que aún usan 4 niveles
for file in api/users/*.php; do
    if [ -f "$file" ]; then
        sed -i '' 's|\$config = require_once __DIR__ \. "/../../../../config\.php";|require_once "../../config.php";|g' "$file"
        sed -i '' 's|\$config = require_once __DIR__ \. "/\.\./\.\./\.\./\.\./config\.php";|require_once "../../config.php";|g' "$file"
        echo "✅ Corregido: $file"
    fi
done

echo "✅ Corrección completada"