<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logFolder = __DIR__ . '/logs/';
        if (!is_dir($logFolder)) @mkdir($logFolder, 0775, true);
        $msg = date('Y-m-d H:i:s') . " - FATAL CART_API ERROR: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
        @file_put_contents($logFolder . 'system_errors.log', $msg, FILE_APPEND);

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'status'  => 'error',
            'message' => 'Technischer Fehler beim Warenkorb: ' . $error['message']
        ]);
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_name('KLEBEKUNST_SESSION');
    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

try {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    require_once __DIR__ . '/products.php';
    require_once __DIR__ . '/stock_helper.php';
    $prodArray = isset($products) ? $products : (isset($GLOBALS['products']) ? $GLOBALS['products'] : []);

    $rawInput = file_get_contents('php://input');
    $data = (!empty($rawInput)) ? json_decode($rawInput, true) : null;
    if (!is_array($data)) {
        $data = !empty($_POST) ? $_POST : [];
    }

    $action = $data['action'] ?? ($_POST['action'] ?? 'get'); 
    $id = isset($data['id']) ? (string)$data['id'] : (isset($_POST['id']) ? (string)$_POST['id'] : '');
    $key = isset($data['key']) ? (string)$data['key'] : (isset($_POST['key']) ? (string)$_POST['key'] : $id); 

    if ($action === 'add' && $id !== '') {
        $qty = max(1, (int)($data['qty'] ?? 1));
        $size = !empty($data['size']) ? $data['size'] : '-';
        $cartKey = $id . '_' . $size; 

        $baseId = resolve_product_base_id($id, $prodArray) ?? $id;
        $prod = $prodArray[$baseId] ?? [];
        $qtyStep = isset($prod['qty_step']) ? max(1, (int)$prod['qty_step']) : 50;

        // Dynamischer Überbuchungsschutz: Restbestand in Packungen prüfen
        $availablePacks = stock_get_available_packs($baseId, $qtyStep);

        // Strenger Ausverkauft-Schutz (Backend)
        if ($availablePacks <= 0) {
            echo json_encode([
                'status'         => 'error',
                'code'           => 'sold_out',
                'message'        => 'Dieser Artikel ist leider ausverkauft.',
                'availablePacks' => 0,
                'stocks'         => stock_get_all()
            ]);
            exit;
        }

        // Packungen dieses Produkts im aktuellen Warenkorb summieren
        $alreadyInCart = 0;
        foreach ($_SESSION['cart'] as $cKey => $cQty) {
            $cBaseId = resolve_product_base_id($cKey, $prodArray);
            if ($cBaseId === $baseId) {
                $alreadyInCart += (int)$cQty;
            }
        }

        // Überbuchungsschutz: Maximal lagernde Packungen erlauben
        if ($alreadyInCart >= $availablePacks) {
            echo json_encode([
                'status'         => 'error',
                'code'           => 'stock_limit',
                'message'        => 'Die maximal verfügbare Menge (' . $availablePacks . ' Packungen) befindet sich bereits im Warenkorb.',
                'availablePacks' => $availablePacks,
                'stocks'         => stock_get_all()
            ]);
            exit;
        }

        // Wenn gewünschte Menge den Restbestand überschreitet, auf Restbestand begrenzen
        if ($alreadyInCart + $qty > $availablePacks) {
            $allowedAdd = max(1, $availablePacks - $alreadyInCart);
            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey] += $allowedAdd;
            } else {
                $_SESSION['cart'][$cartKey] = $allowedAdd;
            }
        } else {
            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey] += $qty;
            } else {
                $_SESSION['cart'][$cartKey] = $qty;
            }
        }
    } 
    elseif ($action === 'set_qty' && $key !== '') {
        $newQty = (int)($data['qty'] ?? 1);
        $baseId = resolve_product_base_id($key, $prodArray) ?? $key;
        $prod = $prodArray[$baseId] ?? [];
        $qtyStep = isset($prod['qty_step']) ? max(1, (int)$prod['qty_step']) : 50;
        $availablePacks = stock_get_available_packs($baseId, $qtyStep);

        if ($newQty > 0) {
            $cappedQty = min($newQty, max(0, $availablePacks));
            if ($cappedQty > 0) {
                $_SESSION['cart'][$key] = $cappedQty;
            } else {
                unset($_SESSION['cart'][$key]);
            }
        } else {
            unset($_SESSION['cart'][$key]);
        }
    }
    elseif ($action === 'update_qty' && $key !== '') {
        $change = (int)($data['change'] ?? 0);
        $baseId = resolve_product_base_id($key, $prodArray) ?? $key;
        $prod = $prodArray[$baseId] ?? [];
        $qtyStep = isset($prod['qty_step']) ? max(1, (int)$prod['qty_step']) : 50;
        $availablePacks = stock_get_available_packs($baseId, $qtyStep);

        if (isset($_SESSION['cart'][$key])) {
            $targetQty = $_SESSION['cart'][$key] + $change;
            if ($targetQty <= 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key] = min($targetQty, max(0, $availablePacks));
            }
        }
    }
    elseif ($action === 'remove' && $key !== '') {
        unset($_SESSION['cart'][$key]); 
    }

    // Warenkorb validieren & gegen aktuellen Bestand abgleichen
    $totalPrice = 0;
    $totalPacks = 0;

    foreach ($_SESSION['cart'] as $cartKey => $qty) {
        $baseId = resolve_product_base_id($cartKey, $prodArray);
        
        if ($baseId === null || !isset($prodArray[$baseId])) {
            unset($_SESSION['cart'][$cartKey]);
            continue;
        }
        
        $qtyStep = isset($prodArray[$baseId]['qty_step']) ? max(1, (int)$prodArray[$baseId]['qty_step']) : 50;
        $availablePacks = stock_get_available_packs($baseId, $qtyStep);

        if ($availablePacks <= 0) {
            unset($_SESSION['cart'][$cartKey]);
            continue;
        }

        if ($qty > $availablePacks) {
            $qty = $availablePacks;
            $_SESSION['cart'][$cartKey] = $qty;
        }

        $priceRaw = isset($prodArray[$baseId]['price']) ? (string)$prodArray[$baseId]['price'] : '0';
        $priceClean = get_clean_price($priceRaw);
        $totalPrice += ($priceClean * $qty);
        $totalPacks += (int)$qty;
    }

    $totalItems = array_sum($_SESSION['cart']);
    $formattedPrice = number_format($totalPrice, 2, ',', '.') . ' €';

    $shippingInfo = function_exists('calculate_shipping') 
        ? calculate_shipping($totalPacks, 'Deutschland')
        : ['price' => 0.00, 'label' => '', 'formatted' => '0,00 €'];

    echo json_encode([
        'status'     => 'ok', 
        'totalItems' => $totalItems,
        'totalPacks' => $totalPacks,
        'totalPrice' => $formattedPrice, 
        'items'      => $_SESSION['cart'],
        'stocks'     => stock_get_all(),
        'shipping'   => [
            'price'     => $shippingInfo['price'],
            'label'     => $shippingInfo['label'],
            'formatted' => $shippingInfo['formatted']
        ]
    ]);
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode([
        'status'  => 'error',
        'message' => 'Warenkorb-Fehler: ' . $e->getMessage()
    ]);
}
?>