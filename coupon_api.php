<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));

$couponsFile = 'coupons.json';
if (!file_exists($couponsFile)) {
    echo json_encode(['valid' => false]);
    exit;
}

$coupons = json_decode(file_get_contents($couponsFile), true);

if (isset($coupons[$code])) {
    echo json_encode([
        'valid' => true, 
        'type' => $coupons[$code]['type'], 
        'value' => $coupons[$code]['value']
    ]);
} else {
    echo json_encode(['valid' => false]);
}
?>