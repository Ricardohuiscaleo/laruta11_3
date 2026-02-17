#!/bin/bash

# Script para instalar cron job de refresh de token Gmail
# Ejecutar: bash install_gmail_cron.sh

SCRIPT_PATH="/var/www/html/api/gmail/refresh_token_cron.php"
CRON_COMMAND="*/40 * * * * /usr/bin/php $SCRIPT_PATH >> /var/log/gmail_token_refresh.log 2>&1"

echo "🔧 Instalando cron job para refresh de token Gmail..."

# Verificar si el cron job ya existe
if crontab -l 2>/dev/null | grep -q "refresh_token_cron.php"; then
    echo "⚠️  Cron job ya existe. Eliminando versión anterior..."
    crontab -l | grep -v "refresh_token_cron.php" | crontab -
fi

# Agregar nuevo cron job
(crontab -l 2>/dev/null; echo "$CRON_COMMAND") | crontab -

echo "✅ Cron job instalado exitosamente!"
echo "📋 El token se refrescará cada 40 minutos"
echo "📝 Logs en: /var/log/gmail_token_refresh.log"
echo ""
echo "Para verificar: crontab -l"
echo "Para ver logs: tail -f /var/log/gmail_token_refresh.log"
