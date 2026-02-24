<?php
/**
 * Test script to verify bot logic without Telegram interaction
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
$pdo = require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/telegram_helper.php';

echo "--- Testing generateInventoryReport ---\n";
try {
    $report = generateInventoryReport($pdo);
    echo "SUCCESS: Report generated (" . strlen($report) . " chars)\n";
}
catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Testing generateGeneralInventoryReport (All) ---\n";
try {
    $report = generateGeneralInventoryReport($pdo, false);
    echo "SUCCESS: General report generated (" . strlen($report) . " chars)\n";
}
catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Testing generateGeneralInventoryReport (Critical) ---\n";
try {
    $report = generateGeneralInventoryReport($pdo, true);
    echo "SUCCESS: Critical report generated (" . strlen($report) . " chars)\n";
}
catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Testing generateShoppingList (Ingredientes) ---\n";
try {
    $report = generateShoppingList($pdo, 'ingredientes');
    echo "SUCCESS: Shopping list (ing) generated (" . strlen($report) . " chars)\n";
}
catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Testing generateShoppingList (Bebidas) ---\n";
try {
    $report = generateShoppingList($pdo, 'bebidas');
    echo "SUCCESS: Shopping list (beb) generated (" . strlen($report) . " chars)\n";
}
catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Done ---\n";