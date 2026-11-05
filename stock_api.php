<?php
/**
 * stock_api.php
 * Gibt den aktuellen Bestand aller Produkte als JSON zurück.
 */
session_name('KLEBEKUNST_SESSION');
session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/stock_helper.php';
require_once __DIR__ . '/products.php';

$stockData = stock_get_all();

echo json_encode([
    'status' => 'ok',
    'stock'  => $stockData,
    'stocks' => $stockData
]);
