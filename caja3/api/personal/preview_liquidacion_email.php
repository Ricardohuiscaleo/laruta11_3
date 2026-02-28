<?php
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo "This endpoint now requires a POST request with structured JSON.";
  exit;
}
require_once __DIR__ . '/email_template.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
  echo "Invalid JSON payload.";
  exit;
}

echo buildPayrollEmailHtml($data);
?>