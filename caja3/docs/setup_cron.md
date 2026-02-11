# Configuración Cron para Auto-renovación Gmail Token

## Comando para agregar al crontab del servidor:

```bash
# Editar crontab
crontab -e

# Agregar esta línea (renovar cada 50 minutos):
*/50 * * * * /usr/bin/php /home/u958525313/domains/agenterag.com/public_html/ruta11app/api/cron/refresh_gmail_token.php

# Verificar crontab
crontab -l
```

## Alternativa: Renovación automática en cada uso

El sistema ya incluye auto-renovación en `send_candidate_email.php` que verifica y renueva el token automáticamente antes de enviar emails.

## Verificar logs:

```bash
tail -f /home/u958525313/domains/agenterag.com/public_html/ruta11app/api/cron/gmail_refresh.log
```