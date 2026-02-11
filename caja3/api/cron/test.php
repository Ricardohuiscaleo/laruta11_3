<?php
file_put_contents(__DIR__ . '/test_execution.log', date('Y-m-d H:i:s') . " - Test ejecutado\n", FILE_APPEND);
echo "HOLA - El cronjob SI puede ejecutar PHP";
?>
