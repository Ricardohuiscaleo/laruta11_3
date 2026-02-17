<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once __DIR__ . '/get_token_db.php';

$result = getValidGmailToken();

echo json_encode($result);
?>
