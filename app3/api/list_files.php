<?php
echo "=== LISTADO DE ARCHIVOS ===\n\n";
echo "Archivos en /var/www/html/:\n";
system('ls -la /var/www/html/ | grep -E "config|load-env"');
echo "\n\nArchivos en /var/www/html/api/:\n";
system('ls -la /var/www/html/api/ | grep -E "config|load-env"');
echo "\n\nBuscar config.php en todo /var/www/html/:\n";
system('find /var/www/html/ -name "config.php" -o -name "load-env.php"');
