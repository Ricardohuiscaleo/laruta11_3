<?php
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = 8000;
$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_GET['mes'] = '2026-02';

require 'caja3/api/personal/get_monthly_cashflow.php';

echo "\n";
?>
