#!/bin/bash

echo "🚀 Iniciando deploy..."

# 1. Generar informe técnico
echo "📊 Generando informe técnico..."
node generate-tech-report.js

# 2. Guardar en base de datos (opcional)
echo "💾 Guardando en base de datos..."
php api/save_tech_report.php

# 3. Subir archivos
echo "📤 Subiendo archivos..."
# rsync -av --exclude 'node_modules' ./ user@server:/path/to/app/

echo "✅ Deploy completado"