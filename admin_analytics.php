<?php
// Output-Buffering nur für HTML-Rendering, nicht für Redirects
// (ob_start wird NACH POST-Handling gesetzt, um Header-Konflikte zu vermeiden)

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logFolder = __DIR__ . '/logs/';
        if (!is_dir($logFolder)) @mkdir($logFolder, 0775, true);
        $msg = date('Y-m-d H:i:s') . " - FATAL ANALYTICS ERROR: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
        @file_put_contents($logFolder . 'system_errors.log', $msg, FILE_APPEND);
        // Ensure a minimal response is sent so FastCGI does not return ERR_EMPTY_RESPONSE
        if (!headers_sent()) {
            header('Location: admin_analytics.php?msg=system_error', true, 302);
        }
    }
});

session_name('KLEBEKUNST_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_access']);
    header("Location: admin_analytics.php", true, 302);
    exit;
}

$_isAdminAuthenticated = isset($_SESSION['admin_access']) && $_SESSION['admin_access'] === true;
if (!$_isAdminAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(403);
    exit('Admin-Anmeldung erforderlich.');
}

require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/stock_helper.php';
require_once __DIR__ . '/products_helper.php';
$shopSettings = get_shop_settings();
$drops = get_all_drops();
$activeDrop = get_active_drop();
if ($_isAdminAuthenticated) {
    $allProducts = get_all_products();
    $stockOverview = stock_get_all();
} else {
    $productsPath = __DIR__ . '/products.json';
    $productData = is_file($productsPath) ? json_decode((string)@file_get_contents($productsPath), true) : [];
    $allProducts = is_array($productData) ? $productData : [];
    $stockPath = __DIR__ . '/stock.json';
    $stockData = is_file($stockPath) ? json_decode((string)@file_get_contents($stockPath), true) : [];
    $stockData = is_array($stockData) ? $stockData : [];
    $stockOverview = [];
    $productIds = array_unique(array_merge(array_keys($stockData), array_keys($allProducts)));
    foreach ($productIds as $productId) {
        $entry = $stockData[$productId] ?? null;
        $initial = is_array($entry) ? (int)($entry['initial'] ?? 5000) : 5000;
        $stock = is_array($entry)
            ? max(0, (int)($entry['stock'] ?? 5000))
            : (is_numeric($entry) ? max(0, (int)$entry) : 5000);
        $product = $allProducts[$productId] ?? [];
        $qtyStep = isset($product['qty_step']) ? max(1, (int)$product['qty_step']) : 50;
        $sold = max(0, $initial - $stock);
        $availablePacks = (int)floor($stock / $qtyStep);
        $stockOverview[$productId] = [
            'id' => $productId,
            'title' => $product['title'] ?? ucfirst((string)$productId),
            'stock' => $stock,
            'initial' => $initial,
            'sold' => $sold,
            'qty_step' => $qtyStep,
            'available_packs' => $availablePacks,
            'sold_packs' => (int)floor($sold / $qtyStep),
            'initial_packs' => (int)floor($initial / $qtyStep),
            'is_sold_out' => $availablePacks <= 0,
            'percent_remaining' => $initial > 0 ? round(($stock / $initial) * 100, 1) : 0,
            'percent_sold' => $initial > 0 ? round(($sold / $initial) * 100, 1) : 0
        ];
    }
}
$GLOBALS['products'] = $allProducts;
$serverImages = get_available_product_images();
$serverImagesDetailed = get_available_product_images_detailed();

// Action Handlers
$feedback_msg = "";
$feedback_type = "success";

// Dateirechte prüfen (Debugging & Validierung)
$permErrors = $_isAdminAuthenticated ? check_system_file_permissions() : [];
if (!empty($permErrors)) {
    $feedback_msg = "Achtung Dateiberechtigungen: " . implode(" | ", $permErrors);
    $feedback_type = "error";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['admin_access']) || $_SESSION['admin_access'] !== true) {
        http_response_code(403);
        exit('Admin-Anmeldung erforderlich.');
    }

    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    // Shop-Status explizit setzen oder togglen
    if ($action === 'set_shop_status' || $action === 'toggle_shop_status') {
        $freshSettings = get_shop_settings();
        if ($action === 'set_shop_status' && isset($_POST['shop_open'])) {
            $newOpen = ($_POST['shop_open'] === '1' || $_POST['shop_open'] === 1 || $_POST['shop_open'] === 'true');
        } else {
            $newOpen = !($freshSettings['shop_open'] ?? false);
        }
        $freshSettings['shop_open'] = $newOpen;
        $saveSuccess = save_shop_settings($freshSettings);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

        if ($isAjax) {
                        header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo json_encode([
                'success'   => $saveSuccess,
                'shop_open' => (bool)$newOpen,
                'error'     => $saveSuccess ? null : 'settings.json konnte nicht gespeichert werden (Dateirechte prüfen).'
            ]);
            exit;
        } else {
            header('Location: ' . $_SERVER['PHP_SELF'], true, 302);
            exit;
        }
    }

    // Neuer Drop anlegen
    if ($action === 'create_drop') {
        $name = trim($_POST['drop_name'] ?? '');
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $endDate = trim($_POST['end_date'] ?? '');
        $setActive = !empty($_POST['set_active']);

        if (!empty($name)) {
            $created = create_drop($name, $startDate, $endDate, $setActive);
            if ($created !== false) {
                header("Location: admin_analytics.php?msg=created", true, 302);
                exit;
            } else {
                $feedback_msg = "Fehler beim Speichern in drops.json (Dateirechte prüfen).";
                $feedback_type = "error";
            }
        } else {
            $feedback_msg = "Bitte einen Namen für den Drop angeben.";
            $feedback_type = "error";
        }
    }

    // Aktiven Drop beenden
    if ($action === 'end_active_drop') {
        end_active_drop();
        header("Location: admin_analytics.php?msg=ended", true, 302);
        exit;
    }

    // Drop als aktiv setzen
    if ($action === 'set_active_drop') {
        $dropId = trim($_POST['drop_id'] ?? '');
        if (!empty($dropId)) {
            set_active_drop($dropId);
            header("Location: admin_analytics.php?msg=activated", true, 302);
            exit;
        }
    }

    // Drop löschen
    if ($action === 'delete_drop') {
        $dropId = trim($_POST['drop_id'] ?? '');
        if (!empty($dropId)) {
            $deleted = delete_drop($dropId);
            if ($deleted) {
                header("Location: admin_analytics.php?msg=deleted", true, 302);
                exit;
            } else {
                $feedback_msg = "Fehler beim Löschen des Drops (Drop nicht gefunden oder Dateirechte prüfen).";
                $feedback_type = "error";
            }
        } else {
            $feedback_msg = "Keine Drop-ID zum Löschen übergeben.";
            $feedback_type = "error";
        }
    }

    // Produkt anlegen oder bearbeiten
    if ($action === 'save_product') {
        $pId = trim($_POST['product_id'] ?? '');
        $origId = trim($_POST['original_id'] ?? '');
        $title = trim($_POST['title'] ?? '');

        if (empty($title)) {
            $feedback_msg = "Bitte einen Produktnamen angeben.";
            $feedback_type = "error";
        } else {
            $uploadWarning = null;
            $mainImgPath = null;
            $prefix = !empty($pId) ? $pId : (!empty($origId) ? $origId : 'prod');

            // 1. Clientseitig komprimierte Base64-Daten für Hauptbild
            if (!empty($_POST['product_image_base64'])) {
                $savedBase64 = save_base64_image($_POST['product_image_base64'], $prefix);
                if ($savedBase64) {
                    $mainImgPath = $savedBase64;
                }
            }

            // 2. Regulärer Multipart-Upload für Hauptbild
            if (!$mainImgPath && isset($_FILES['product_image'])) {
                $f = $_FILES['product_image'];
                if ($f['error'] === UPLOAD_ERR_OK && !empty($f['tmp_name'])) {
                    $uploadedPath = upload_product_image($f, $prefix);
                    if ($uploadedPath) {
                        $mainImgPath = $uploadedPath;
                    } else {
                        $uploadWarning = "Hauptbild-Format ungültig (nur JPG, PNG, WEBP, GIF erlaubt).";
                    }
                } elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $uploadWarning = "Die Bilddatei überschreitet das Server-Upload-Limit. Bitte kleineres Bild wählen oder Vorschau-Komprimierung nutzen.";
                } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadWarning = "Upload-Fehler beim Bild (Code: " . (int)$f['error'] . ").";
                }
            }

            // 3. Ausgewählter Server-Bildpfad oder manuelle Eingabe
            if (!$mainImgPath && !empty($_POST['image_url'])) {
                $mainImgPath = trim($_POST['image_url']);
            }

            // 4. Vorhandenes Bild des Produkts beibehalten
            if (!$mainImgPath && !empty($_POST['existing_image'])) {
                $mainImgPath = trim($_POST['existing_image']);
            }

            // 5. Falls Bearbeitung und immer noch kein Bild festgelegt:
            // Hole das existierende Bild aus dem aktiven Katalog
            if (!$mainImgPath && !empty($origId) && isset($allProducts[$origId]['image'])) {
                $mainImgPath = $allProducts[$origId]['image'];
            }

            // 6. Absoluter Fallback: placeholder.png
            if (!$mainImgPath) {
                $mainImgPath = 'images/placeholder.png';
            }

            // Detailbilder / Galerie behandeln
            $galleryPaths = [];
            if (!empty($_POST['existing_gallery_json'])) {
                $decoded = json_decode($_POST['existing_gallery_json'], true);
                if (is_array($decoded)) {
                    $galleryPaths = array_values(array_filter(array_map('trim', $decoded)));
                }
            }
            if (!empty($_POST['new_gallery_base64_json'])) {
                $newGalleryB64 = json_decode($_POST['new_gallery_base64_json'], true);
                if (is_array($newGalleryB64)) {
                    foreach ($newGalleryB64 as $gIdx => $gB64) {
                        $savedG = save_base64_image($gB64, $prefix . '_d' . ($gIdx + 1));
                        if ($savedG) {
                            $galleryPaths[] = $savedG;
                        }
                    }
                }
            }
            if (!empty($_FILES['product_gallery']['name'][0])) {
                $newGallery = upload_product_gallery($_FILES['product_gallery'], $prefix);
                if (!empty($newGallery)) {
                    $galleryPaths = array_merge($galleryPaths, $newGallery);
                }
            }

            // Sicherstellen, dass das Hauptbild in der Galerie enthalten ist
            if (!empty($mainImgPath) && empty($galleryPaths)) {
                $galleryPaths[] = $mainImgPath;
            }

            // Dubletten entfernen
            $galleryPaths = array_values(array_unique($galleryPaths));

            // Stückzahl / Lagerbestand ermitteln
            $stockPieces = isset($_POST['stock_pieces']) ? max(0, (int)$_POST['stock_pieces']) : 5000;
            $stockInitial = isset($_POST['stock_initial']) ? max(0, (int)$_POST['stock_initial']) : 5000;

            $productData = [
                'id'            => $pId,
                'title'         => $title,
                'price'         => $_POST['price'] ?? 0,
                'image'         => $mainImgPath,
                'gallery'       => $galleryPaths,
                'description'   => trim($_POST['description'] ?? ''),
                'size'          => trim($_POST['size'] ?? ''),
                'has_sizes'     => !empty($_POST['has_sizes']),
                'sizes'         => trim($_POST['sizes'] ?? ''),
                'qty_step'      => max(1, (int)($_POST['qty_step'] ?? 50)),
                'qty_unit'      => trim($_POST['qty_unit'] ?? ' Stk.'),
                'drop_id'       => !empty($_POST['drop_id']) ? trim($_POST['drop_id']) : null,
                'status'        => (!empty($_POST['status']) && $_POST['status'] === 'hidden') ? 'hidden' : 'active',
                'stock_pieces'  => $stockPieces,
                'stock_initial' => $stockInitial,
                'ref_prefix'    => trim($_POST['ref_prefix'] ?? ''),
            ];

            $res = save_product($productData, $origId ?: null);
            if (!empty($res['success'])) {
                $redirUrl = "admin_analytics.php?msg=product_saved";
                if ($uploadWarning) {
                    $redirUrl .= "&upload_warning=" . urlencode($uploadWarning);
                }
                                header("Location: " . $redirUrl, true, 302);
                exit;
            } else {
                $feedback_msg = "Fehler beim Speichern des Produkts in products.json (Dateirechte prüfen).";
                $feedback_type = "error";
            }
        }
    }

    // Produkt löschen
    if ($action === 'delete_product') {
        $delId = trim($_POST['product_id'] ?? '');
        if (!empty($delId)) {
            $delRes = delete_product($delId);
            if ($delRes) {
                                header("Location: admin_analytics.php?msg=product_deleted", true, 302);
                exit;
            } else {
                $feedback_msg = "Fehler beim Löschen des Produkts (Dateirechte prüfen).";
                $feedback_type = "error";
            }
        } else {
            $feedback_msg = "Keine Produkt-ID zum Löschen angegeben.";
            $feedback_type = "error";
        }
    }

    // Schnell-Bestandsaktualisierung
    if ($action === 'update_product_stock') {
        $stockPId = trim($_POST['product_id'] ?? '');
        $newStock = isset($_POST['stock_pieces']) ? max(0, (int)$_POST['stock_pieces']) : 0;
        $newInit = isset($_POST['stock_initial']) ? max(0, (int)$_POST['stock_initial']) : null;
        if (!empty($stockPId)) {
            stock_set($stockPId, $newStock, $newInit);
                        header("Location: admin_analytics.php?msg=stock_updated", true, 302);
            exit;
        }
    }

    // Alle Bestellungen löschen
    if ($action === 'clear_orders') {
        $saved = save_all_orders([]);
                header("Location: admin_analytics.php?msg=" . ($saved ? 'orders_cleared' : 'orders_clear_error'), true, 302);
        exit;
    }

    // Verkaufte Stückzahl resetten (stock = initial für alle Produkte)
    if ($action === 'reset_stock_sold') {
        $stockData = stock_load();
        foreach ($stockData as $pid => &$entry) {
            if (is_array($entry)) {
                $entry['stock'] = $entry['initial'];
            }
        }
        unset($entry);
        $saved = stock_save($stockData);
                header("Location: admin_analytics.php?msg=" . ($saved ? 'stock_reset' : 'stock_reset_error'), true, 302);
        exit;
    }
}

// GET Feedback Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') { $feedback_msg = "Neuer Drop erfolgreich angelegt!"; $feedback_type = "success"; }
    if ($_GET['msg'] === 'ended') { $feedback_msg = "Aktiver Drop wurde erfolgreich beendet."; $feedback_type = "success"; }
    if ($_GET['msg'] === 'activated') { $feedback_msg = "Drop wurde als aktiv festgelegt."; $feedback_type = "success"; }
    if ($_GET['msg'] === 'deleted') { $feedback_msg = "Drop wurde gelöscht."; $feedback_type = "success"; }
    if ($_GET['msg'] === 'product_saved') { 
        $feedback_msg = "Produkt erfolgreich gespeichert und Lagerbestand synchronisiert!"; 
        if (!empty($_GET['upload_warning'])) {
            $feedback_msg .= " (Hinweis zum Bild: " . htmlspecialchars($_GET['upload_warning']) . ")";
            $feedback_type = "error";
        } else {
            $feedback_type = "success";
        }
    }
    if ($_GET['msg'] === 'product_deleted') { $feedback_msg = "Produkt wurde gelöscht."; $feedback_type = "success"; }
    if ($_GET['msg'] === 'stock_updated') { $feedback_msg = "Lagerbestand wurde erfolgreich aktualisiert!"; $feedback_type = "success"; }
    if ($_GET['msg'] === 'orders_cleared') { $feedback_msg = "✓ Alle Bestellungen wurden erfolgreich gelöscht."; $feedback_type = "success"; }
    if ($_GET['msg'] === 'orders_clear_error') { $feedback_msg = "✗ Fehler beim Löschen der Bestellungen (Dateirechte prüfen)."; $feedback_type = "error"; }
    if ($_GET['msg'] === 'stock_reset') { $feedback_msg = "✓ Verkaufte Stückzahl wurde für alle Produkte zurückgesetzt."; $feedback_type = "success"; }
    if ($_GET['msg'] === 'stock_reset_error') { $feedback_msg = "✗ Fehler beim Resetten des Lagerbestands (Dateirechte prüfen)."; $feedback_type = "error"; }
}

// Filter bestimmen
$filterDrop = $_GET['drop'] ?? 'all';
$analytics = get_sales_analytics($filterDrop);

// Filter-Titel ermitteln
$filterTitle = "Alle Drops (Gesamtübersicht)";
if ($filterDrop === 'unassigned') {
    $filterTitle = "Bestellungen ohne Drop-Zuweisung";
} elseif ($filterDrop !== 'all') {
    foreach ($drops as $d) {
        if ($d['id'] === $filterDrop) {
            $filterTitle = "Drop: " . htmlspecialchars($d['name']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLEBEKUNST | DROPS & ANALYTICS</title>
    <link rel="icon" type="image/png" href="images/favicon-black.png?v=2" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" href="images/favicon-white.png?v=2" media="(prefers-color-scheme: dark)">
    <style>
        @font-face { font-family: 'Klebekunst Headline'; src: url('fonts/Klebekunst-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Inter'; src: url('fonts/Inter-Variable.woff2') format('woff2-variations'), url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); }
        
        :root {
            --bg-color: #f0f0e8;
            --black: #000000;
            --accent: #ff2a00;
            --green: #28a745;
            --border-thick: 4px solid var(--black);
        }

        *, *:before, *:after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg-color); color: var(--black); font-family: 'Inter', sans-serif; min-height: 100vh; padding: 20px 4vw; }

        /* =========================================================
           HEADER
           ========================================================= */
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: var(--border-thick); padding-bottom: 20px; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; }
        .header-left { display: flex; flex-direction: column; gap: 12px; }
        .header-right { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .header h1 { font-family: 'Klebekunst Headline', sans-serif; font-size: 3.5rem; text-transform: uppercase; margin: 0; line-height: 1; }

        .admin-nav-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .admin-nav-tab { font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.95rem; text-transform: uppercase; padding: 8px 16px; border: 3px solid var(--black); background: #fff; color: var(--black); text-decoration: none; box-shadow: 4px 4px 0 var(--black); transition: all 0.1s; }
        .admin-nav-tab.active { background: var(--black); color: #fff; }
        .admin-nav-tab:hover:not(.active) { transform: translate(-1px, -1px); box-shadow: 5px 5px 0 var(--black); background: var(--bg-color); }

        .shop-toggle-card { display: flex; align-items: center; gap: 10px; background: #fff; border: 3px solid var(--black); padding: 6px 12px; box-shadow: 5px 5px 0 var(--black); }
        .toggle-card-label { font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.8rem; letter-spacing: 0.05em; opacity: 0.7; }
        .status-toggle-btn { display: inline-flex; align-items: center; gap: 8px; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.3rem; padding: 6px 14px; border: 3px solid var(--black); cursor: pointer; text-transform: uppercase; box-shadow: 3px 3px 0 var(--black); transition: all 0.1s; }
        .status-toggle-btn:hover { transform: translate(-1px, -1px); box-shadow: 4px 4px 0 var(--black); }
        .status-toggle-btn:active { transform: translate(2px, 2px); box-shadow: none; }
        .status-toggle-btn.is-open { background: var(--green); color: #fff; }
        .status-toggle-btn.is-closed { background: var(--black); color: #fff; }
        .status-indicator-dot { width: 10px; height: 10px; border-radius: 50%; border: 2px solid #000; display: inline-block; }
        .status-toggle-btn.is-open .status-indicator-dot { background: #00ff66; box-shadow: 0 0 6px #00ff66; }
        .status-toggle-btn.is-closed .status-indicator-dot { background: var(--accent); }

        .logout-btn { background: var(--black); color: #fff; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.4rem; padding: 6px 16px; border: 3px solid var(--black); text-decoration: none; cursor: pointer; box-shadow: 4px 4px 0 var(--accent); transition: all 0.1s; }
        .logout-btn:hover { background: var(--accent); color: #fff; transform: translate(-2px,-2px); box-shadow: 6px 6px 0 var(--black); }

        /* Banner Feedback */
        .feedback-banner { background: #fff; border: 3px solid var(--black); padding: 12px 20px; margin-bottom: 25px; box-shadow: 6px 6px 0 var(--black); font-weight: 800; display: flex; justify-content: space-between; align-items: center; }
        .feedback-banner.success { border-left: 10px solid var(--green); }
        .feedback-banner.error { border-left: 10px solid var(--accent); }

        /* =========================================================
           SECTIONS & CARDS
           ========================================================= */
        .section-title {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 2.2rem;
            text-transform: uppercase;
            margin: 30px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title::after {
            content: "";
            flex: 1;
            height: 4px;
            background: var(--black);
        }

        .drop-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 35px;
        }

        .neo-card {
            background: #fff;
            border: var(--border-thick);
            padding: 24px;
            box-shadow: 8px 8px 0 var(--black);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .neo-card h3 {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 1.8rem;
            text-transform: uppercase;
            margin-bottom: 15px;
            border-bottom: 3px solid var(--black);
            padding-bottom: 8px;
        }

        /* Aktiver Drop Card */
        .active-drop-status-box {
            background: var(--bg-color);
            border: 3px solid var(--black);
            padding: 16px;
            margin-bottom: 20px;
        }
        .active-drop-name {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 2.5rem;
            line-height: 1;
            color: var(--accent);
            margin-bottom: 6px;
        }
        .active-drop-meta {
            font-size: 0.9rem;
            font-weight: 700;
            opacity: 0.8;
            text-transform: uppercase;
        }
        .active-drop-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Formular Drop Anlegen */
        .drop-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }
        .drop-form-group label {
            font-weight: 900;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .neo-input {
            border: 3px solid var(--black);
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            padding: 10px 14px;
            outline: none;
            background: #fff;
            font-weight: 600;
        }
        .neo-input:focus {
            background: #fffdf5;
        }
        .neo-checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            margin: 10px 0 16px 0;
        }
        .neo-checkbox {
            width: 22px;
            height: 22px;
            border: 3px solid var(--black);
            accent-color: var(--black);
            cursor: pointer;
        }

        .neo-btn-primary {
            background: var(--accent);
            color: #fff;
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 1.6rem;
            padding: 10px 20px;
            border: 3px solid var(--black);
            cursor: pointer;
            box-shadow: 4px 4px 0 var(--black);
            transition: all 0.1s;
            text-transform: uppercase;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }
        .neo-btn-primary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 var(--black);
            background: var(--black);
            color: var(--accent);
        }
        .neo-btn-primary:active {
            transform: translate(2px, 2px);
            box-shadow: none;
        }

        .neo-btn-secondary {
            background: #fff;
            color: var(--black);
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            font-size: 0.9rem;
            padding: 10px 16px;
            border: 2px solid var(--black);
            cursor: pointer;
            box-shadow: 3px 3px 0 var(--black);
            transition: all 0.1s;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .neo-btn-secondary:hover {
            transform: translate(-1px, -1px);
            box-shadow: 4px 4px 0 var(--black);
            background: var(--bg-color);
        }

        .neo-btn-danger {
            background: #ffe5e5;
            color: #cc0000;
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            font-size: 0.85rem;
            padding: 6px 12px;
            border: 2px solid var(--black);
            cursor: pointer;
            box-shadow: 2px 2px 0 var(--black);
            text-transform: uppercase;
        }
        .neo-btn-danger:hover {
            background: #cc0000;
            color: #fff;
        }

        /* =========================================================
           TABELLEN (Drops & Sales)
           ========================================================= */
        .table-container {
            background: #fff;
            border: var(--border-thick);
            box-shadow: 12px 12px 0 var(--black);
            overflow-x: auto;
            margin-bottom: 40px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }
        th, td {
            border: 2px solid var(--black);
            padding: 12px 16px;
            text-align: left;
        }
        th {
            background: var(--black);
            color: #fff;
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 1.3rem;
            text-transform: uppercase;
            white-space: nowrap;
        }
        td {
            font-size: 0.95rem;
            font-weight: 500;
        }
        tr:hover {
            background: rgba(0,0,0,0.02);
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 0.8rem;
            font-weight: 900;
            text-transform: uppercase;
            border: 2px solid var(--black);
            box-shadow: 2px 2px 0 var(--black);
        }
        .badge.active { background: #00ff66; color: var(--black); }
        .badge.ended { background: #e0e0e0; color: #555; }
        .badge.open { background: #ffd700; }

        /* =========================================================
           ANALYTICS KPI GRID
           ========================================================= */
        .analytics-filter-bar {
            background: #fff;
            border: var(--border-thick);
            padding: 16px 20px;
            margin-bottom: 25px;
            box-shadow: 6px 6px 0 var(--black);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: var(--bg-color);
            border: 2px solid var(--black);
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            padding: 8px 14px;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.85rem;
            text-decoration: none;
            color: var(--black);
            box-shadow: 2px 2px 0 var(--black);
            transition: all 0.1s;
        }
        .filter-btn.active {
            background: var(--accent);
            color: #fff;
            transform: translate(-1px, -1px);
            box-shadow: 4px 4px 0 var(--black);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }
        .kpi-box {
            background: #fff;
            border: var(--border-thick);
            padding: 22px;
            box-shadow: 8px 8px 0 var(--black);
            border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px;
            display: flex;
            flex-direction: column;
        }
        .kpi-title {
            font-weight: 900;
            text-transform: uppercase;
            font-size: 1rem;
            opacity: 0.75;
            margin-bottom: 8px;
            letter-spacing: 0.05em;
        }
        .kpi-value {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 3.8rem;
            line-height: 0.95;
            color: var(--black);
        }

        /* Produkt-Zahlen Visual */
        .progress-bar-bg {
            width: 100%;
            height: 12px;
            background: #e5e5e5;
            border: 2px solid var(--black);
            margin-top: 6px;
        }
        .progress-bar-fill {
            height: 100%;
            background: var(--accent);
        }
        .size-pills {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .size-pill {
            background: var(--bg-color);
            border: 1px solid var(--black);
            padding: 2px 6px;
            font-size: 0.75rem;
            font-weight: 800;
        }

        /* =========================================================
           PRODUKT-MANAGEMENT (CARDS & MODALS)
           ========================================================= */
        .product-admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
            margin-bottom: 35px;
        }
        .product-admin-card {
            background: #fff;
            border: var(--border-thick);
            padding: 20px;
            box-shadow: 8px 8px 0 var(--black);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px;
            position: relative;
            transition: transform 0.1s;
        }
        .product-admin-card:hover {
            transform: translate(-1px, -1px);
            box-shadow: 10px 10px 0 var(--black);
        }
        .prod-header-row {
            display: flex;
            gap: 14px;
            margin-bottom: 12px;
            align-items: flex-start;
        }
        .prod-thumb-box {
            width: 84px;
            height: 84px;
            border: 3px solid var(--black);
            overflow: hidden;
            background: var(--bg-color);
            flex-shrink: 0;
            box-shadow: 3px 3px 0 var(--black);
        }
        .prod-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .prod-info-col {
            flex: 1;
            min-width: 0;
        }
        .prod-title-heading {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 1.6rem;
            line-height: 1.05;
            text-transform: uppercase;
            margin: 0 0 4px 0;
            word-break: break-word;
        }
        .prod-price-tag {
            font-family: monospace;
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--accent);
            background: var(--black);
            display: inline-block;
            padding: 2px 8px;
            transform: rotate(-1.5deg);
            margin-bottom: 6px;
        }
        .prod-badges-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        .prod-desc-box {
            font-size: 0.85rem;
            line-height: 1.35;
            color: #333;
            background: var(--bg-color);
            border: 2px solid var(--black);
            padding: 8px 10px;
            margin: 10px 0;
            font-weight: 500;
            max-height: 85px;
            overflow-y: auto;
        }
        .prod-details-meta {
            font-size: 0.8rem;
            font-weight: 800;
            color: #444;
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .prod-actions-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
            border-top: 2px solid var(--black);
            padding-top: 12px;
        }

        /* MODAL DIALOGS */
        .neo-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overscroll-behavior: contain;
        }
        .neo-modal-backdrop.is-active {
            display: flex;
        }
        .neo-modal-card {
            background: #fff;
            border: var(--border-thick);
            box-shadow: 14px 14px 0 var(--black);
            max-width: 840px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border-radius: 2px;
        }
        .neo-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 3px solid var(--black);
            background: var(--bg-color);
        }
        .neo-modal-title {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 2.2rem;
            margin: 0;
            text-transform: uppercase;
            line-height: 1;
        }
        .neo-modal-close {
            background: var(--accent);
            color: #fff;
            border: 3px solid var(--black);
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 1.8rem;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 3px 3px 0 var(--black);
            transition: all 0.1s;
        }
        .neo-modal-close:hover {
            transform: translate(-1px, -1px);
            box-shadow: 5px 5px 0 var(--black);
        }
        .neo-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group-full {
            grid-column: 1 / -1;
        }
        .form-help-hint {
            font-size: 0.78rem;
            font-weight: 700;
            color: #666;
            margin-top: 4px;
        }

        @media (max-width: 850px) {
            body { padding: 15px 4vw; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .header h1 { font-size: 2.2rem; }
            .drop-grid { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            .kpi-value { font-size: 2.6rem; }
            .section-title { font-size: 1.8rem; }
        }

        /* Kompakter & sortierter Server Image Picker */
        .server-img-drawer {
            display: none;
            margin-top: 10px;
            background: #fafaf5;
            border: 2.5px solid var(--black);
            padding: 10px 12px;
            box-shadow: 4px 4px 0 var(--black);
            border-radius: 2px;
            animation: drawerFadeIn 0.15s ease-out;
        }
        @keyframes drawerFadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .server-img-drawer.is-open {
            display: block;
        }
        .drawer-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
            border-bottom: 2px solid var(--black);
            padding-bottom: 8px;
        }
        .drawer-filter-tabs {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .drawer-tab-btn {
            background: #fff;
            border: 2px solid var(--black);
            font-family: 'Inter', sans-serif;
            font-size: 0.72rem;
            font-weight: 900;
            padding: 4px 8px;
            cursor: pointer;
            box-shadow: 2px 2px 0 var(--black);
            text-transform: uppercase;
            transition: all 0.1s;
        }
        .drawer-tab-btn:hover {
            background: var(--bg-color);
        }
        .drawer-tab-btn.active {
            background: var(--black);
            color: #fff;
            box-shadow: 1px 1px 0 var(--black);
            transform: translate(1px, 1px);
        }
        .drawer-search-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .drawer-search-input {
            border: 2px solid var(--black);
            font-family: 'Inter', sans-serif;
            font-size: 0.78rem;
            padding: 4px 8px;
            padding-left: 24px;
            width: 130px;
            outline: none;
            font-weight: 700;
            background: #fff;
        }
        .drawer-search-input:focus {
            border-color: var(--accent);
            width: 160px;
            transition: width 0.2s;
        }
        .drawer-search-icon {
            position: absolute;
            left: 6px;
            font-size: 0.75rem;
            pointer-events: none;
            opacity: 0.6;
        }
        .drawer-close-btn {
            background: transparent;
            border: 2px solid transparent;
            font-weight: 900;
            font-size: 0.85rem;
            cursor: pointer;
            padding: 3px 6px;
            color: #333;
        }
        .drawer-close-btn:hover {
            color: var(--accent);
            border-color: var(--black);
            background: #fff;
        }
        .server-img-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
            gap: 4px;
            max-height: 120px;
            overflow-y: auto;
            padding: 3px 2px;
            scrollbar-width: thin;
        }
        .server-img-choice {
            width: 100%;
            aspect-ratio: 1;
            border: 2px solid var(--black);
            background: #fff;
            cursor: pointer;
            overflow: hidden;
            position: relative;
            padding: 0;
            display: block;
            box-shadow: 2px 2px 0 var(--black);
            transition: transform 0.1s, box-shadow 0.1s, border-color 0.1s;
        }
        .server-img-choice:hover {
            transform: scale(1.08);
            border-color: var(--accent);
            box-shadow: 3px 3px 0 var(--black);
            z-index: 4;
        }
        .server-img-choice.selected {
            border-color: var(--accent);
            border-width: 2.5px;
            box-shadow: 0 0 0 2px var(--accent);
        }
        .server-img-choice img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #fdfdfd;
            display: block;
        }
        .server-img-choice .choice-badge {
            position: absolute;
            top: 2px;
            left: 2px;
            background: var(--accent);
            color: #fff;
            font-size: 0.52rem;
            font-weight: 900;
            padding: 1px 3px;
            line-height: 1;
            border: 1px solid var(--black);
            border-radius: 1px;
            text-transform: uppercase;
        }
        .server-img-choice .choice-badge.badge-detail {
            background: #2563eb;
        }
        .server-img-choice .choice-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.8);
            color: #fff;
            font-size: 0.52rem;
            font-weight: 700;
            padding: 1px;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            text-align: center;
            opacity: 0.9;
        }

        .gallery-item-card {
            position: relative;
            width: 75px;
            height: 75px;
            border: 2px solid var(--black);
            background: #fff;
            box-shadow: 2px 2px 0 var(--black);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .gallery-item-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .gallery-item-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.85);
            display: flex;
            justify-content: space-between;
            padding: 2px 4px;
        }
        .gallery-item-btn {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 0.75rem;
            cursor: pointer;
            padding: 1px 3px;
            line-height: 1;
        }
        .gallery-item-btn:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <h1>ADMIN DASHBOARD</h1>
            <div class="admin-nav-tabs">
                <a href="admin.php" class="admin-nav-tab">BESTELLUNGEN</a>
                <a href="admin_analytics.php" class="admin-nav-tab active">DROPS & ANALYTICS</a>
            </div>
            <?php if (!empty($_SESSION['admin_access'])): ?>
                <a href="?logout=1" class="logout-btn">LOGOUT</a>
            <?php else: ?>
                <a href="admin.php?login=1" class="logout-btn">ADMIN-LOGIN</a>
            <?php endif; ?>
        </div>
        <div class="header-right">
            <!-- SHOP STATUS TOGGLE -->
            <div class="shop-toggle-card">
                <span class="toggle-card-label">SHOP-STATUS:</span>
                <form method="POST" action="admin_analytics.php" id="shopStatusForm" style="margin:0; display:inline-flex;">
                    <input type="hidden" name="action" value="set_shop_status">
                    <input type="hidden" name="shop_open" id="shopStatusInput" value="<?= !empty($shopSettings['shop_open']) ? '0' : '1' ?>">
                    <button type="submit" id="shopStatusToggleBtn" class="status-toggle-btn <?= !empty($shopSettings['shop_open']) ? 'is-open' : 'is-closed' ?>">
                        <span class="status-indicator-dot"></span>
                        <span id="shopStatusText"><?= !empty($shopSettings['shop_open']) ? 'SHOP GEÖFFNET' : 'SHOP GESCHLOSSEN' ?></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($feedback_msg)): ?>
        <div class="feedback-banner <?= $feedback_type ?>">
            <span><?= htmlspecialchars($feedback_msg) ?></span>
            <span style="cursor:pointer;" onclick="this.parentElement.style.display='none';">&times;</span>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         SECTION 1: DROP-MANAGEMENT
         ========================================================= -->
    <div class="section-title">DROP MANAGEMENT</div>

    <div class="drop-grid">
        <!-- AKTIVER DROP -->
        <div class="neo-card">
            <div>
                <h3>AKTUELLER DROP</h3>
                <?php if ($activeDrop): ?>
                    <div class="active-drop-status-box">
                        <div class="active-drop-name"><?= htmlspecialchars($activeDrop['name']) ?></div>
                        <div class="active-drop-meta">
                            Gestartet am: <?= htmlspecialchars($activeDrop['date'] ?? $activeDrop['start_date'] ?? '-') ?> | Status: <strong>AKTIV</strong>
                        </div>
                    </div>
                    <p style="font-size:0.9rem; margin-bottom: 20px; font-weight:600;">
                        Neue Bestellungen im Shop werden diesem Drop automatisch zugeordnet.
                    </p>
                <?php else: ?>
                    <div class="active-drop-status-box" style="background:#fff3cd; border-color:#856404;">
                        <div class="active-drop-name" style="color:#856404; font-size:2rem;">KEIN DROP AKTIV</div>
                        <div class="active-drop-meta">Aktuell läuft kein bestimmter Drop.</div>
                    </div>
                    <p style="font-size:0.9rem; margin-bottom: 20px; font-weight:600;">
                        Lege rechts einen neuen Drop an oder wähle unten einen bestehenden Drop als aktiv aus.
                    </p>
                <?php endif; ?>
            </div>

            <div class="active-drop-actions">
                <?php if ($activeDrop): ?>
                    <form method="POST" action="admin_analytics.php" onsubmit="return confirm('Möchtest du den aktuellen Drop wirklich beenden?');">
                        <input type="hidden" name="action" value="end_active_drop">
                        <button type="submit" class="neo-btn-primary" style="background:var(--black); font-size:1.3rem;">
                            ⏹ DROP BEENDEN
                        </button>
                    </form>
                    <a href="admin_analytics.php?drop=<?= urlencode($activeDrop['id']) ?>" class="neo-btn-secondary">
                        📊 Drop in Verkaufsanalyse anzeigen
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- NEUEN DROP ANLEGEN -->
        <div class="neo-card">
            <div>
                <h3>NEUEN DROP ANLEGEN</h3>
                <form method="POST" action="admin_analytics.php">
                    <input type="hidden" name="action" value="create_drop">
                    
                    <div class="drop-form-group">
                        <label for="drop_name">Name des Drops</label>
                        <input type="text" id="drop_name" name="drop_name" class="neo-input" placeholder="z. B. Drop 2 - Shirts & Adler" required>
                    </div>

                    <div class="drop-form-group">
                        <label for="start_date">Startdatum</label>
                        <input type="date" id="start_date" name="start_date" class="neo-input" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="drop-form-group">
                        <label for="end_date">Enddatum (optional)</label>
                        <input type="date" id="end_date" name="end_date" class="neo-input" placeholder="YYYY-MM-DD">
                    </div>

                    <label class="neo-checkbox-label">
                        <input type="checkbox" name="set_active" value="1" class="neo-checkbox" checked>
                        <span>Diesen Drop sofort als AKTIV setzen (bisherigen Drop beenden)</span>
                    </label>

                    <button type="submit" class="neo-btn-primary" style="width:100%;">
                        + DROP ERSTELLEN
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TABELLE ALLER DROPS -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name des Drops</th>
                    <th>Startdatum</th>
                    <th>Enddatum</th>
                    <th>Status</th>
                    <th>Bestellungen</th>
                    <th>Gesamtumsatz</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($drops)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px;">Bisher keine Drops angelegt.</td></tr>
                <?php else: ?>
                    <?php foreach (array_reverse($drops) as $d): 
                        $isCurrentActive = ($activeDrop && $activeDrop['id'] === $d['id']);
                        $summary = $analytics['drops_summary'][$d['id']] ?? null;
                        $dropOrdersCount = $summary ? $summary['order_count'] : 0;
                        $dropRevenue = $summary ? $summary['revenue'] : 0.0;
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($d['name']) ?></strong>
                            <?php if ($isCurrentActive): ?>
                                <span class="badge active" style="margin-left: 8px;">AKTIV</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($d['start_date'] ?? $d['date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($d['end_date'] ?? ($isCurrentActive ? 'Laufend' : '-')) ?></td>
                        <td>
                            <?php if ($isCurrentActive): ?>
                                <span class="badge active">AKTIV</span>
                            <?php else: ?>
                                <span class="badge ended">BEENDET</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= $dropOrdersCount ?></strong></td>
                        <td><strong><?= number_format($dropRevenue, 2, ',', '.') ?> €</strong></td>
                        <td>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <a href="admin_analytics.php?drop=<?= urlencode($d['id']) ?>" class="neo-btn-secondary" style="padding:4px 8px; font-size:0.8rem;">
                                    📊 Analysieren
                                </a>
                                <?php if (!$isCurrentActive): ?>
                                    <form method="POST" action="admin_analytics.php" style="display:inline;">
                                        <input type="hidden" name="action" value="set_active_drop">
                                        <input type="hidden" name="drop_id" value="<?= htmlspecialchars($d['id']) ?>">
                                        <button type="submit" class="neo-btn-secondary" style="padding:4px 8px; font-size:0.8rem; background:#f0fff0;">
                                            ▶ Als aktiv setzen
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="admin_analytics.php" style="display:inline;">
                                        <input type="hidden" name="action" value="end_active_drop">
                                        <button type="submit" class="neo-btn-secondary" style="padding:4px 8px; font-size:0.8rem; background:#fff0f0;">
                                            ⏹ Beenden
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="admin_analytics.php" onsubmit="return confirm('Möchtest du den Drop &quot;<?= htmlspecialchars(addslashes($d['name'])) ?>&quot; wirklich löschen?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_drop">
                                    <input type="hidden" name="drop_id" value="<?= htmlspecialchars($d['id']) ?>">
                                    <button type="submit" class="neo-btn-danger" style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; cursor:pointer;" title="Drop löschen">🗑 LÖSCHEN</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- =========================================================
         SECTION: PRODUKT-MANAGEMENT (ARTIKEL HINZUFÜGEN & VERWALTEN)
         ========================================================= -->
    <div class="section-title" id="section-products" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <span>PRODUKT-MANAGEMENT (<?= count($allProducts) ?> ARTIKEL)</span>
        <button type="button" class="neo-btn-primary" onclick="openProductModal()" style="font-size:1.2rem; padding:8px 18px; margin-left:auto;">
            + NEUES PRODUKT HINZUFÜGEN
        </button>
    </div>

    <div class="product-admin-grid">
        <?php foreach ($allProducts as $pId => $prod): 
            $stockInfo = $stockOverview[$pId] ?? null;
            $pCurStock = (int)($stockInfo['stock'] ?? 5000);
            $pInitStock = (int)($stockInfo['initial'] ?? 5000);
            $pStep = max(1, (int)($prod['qty_step'] ?? 50));
            $pAvailPacks = (int)floor($pCurStock / $pStep);
            $pInitPacks = (int)floor($pInitStock / $pStep);
            $pSold = max(0, $pInitStock - $pCurStock);
            $pSoldPacks = (int)floor($pSold / $pStep);
            $pPercent = ($pInitStock > 0) ? round(($pCurStock / $pInitStock) * 100, 1) : 0;
            $pIsSoldOut = ($pAvailPacks <= 0);

            // Drop Name ermitteln
            $assignedDropName = 'Alle Drops / Immer aktiv';
            if (!empty($prod['drop_id'])) {
                foreach ($drops as $dr) {
                    if ($dr['id'] === $prod['drop_id']) {
                        $assignedDropName = $dr['name'];
                        break;
                    }
                }
            }

            $imgSrc = !empty($prod['image']) ? $prod['image'] : 'images/placeholder.png';
            $imgCacheBuster = file_exists(__DIR__ . '/' . $imgSrc) ? filemtime(__DIR__ . '/' . $imgSrc) : time();
            $statusIsActive = ($prod['status'] ?? 'active') === 'active';
        ?>
        <div class="product-admin-card" id="admin-card-<?= htmlspecialchars($pId) ?>">
            <div>
                <!-- OBERE REIHE: THUMBNAIL + BASISDATEN -->
                <div class="prod-header-row">
                    <div class="prod-thumb-box">
                        <img src="<?= htmlspecialchars($imgSrc) ?>?v=<?= $imgCacheBuster ?>" alt="<?= htmlspecialchars($prod['title']) ?>" class="prod-thumb-img" onerror="this.src='images/placeholder.png'; this.onerror=null;">
                    </div>
                    <div class="prod-info-col">
                        <h4 class="prod-title-heading"><?= htmlspecialchars($prod['title']) ?></h4>
                        <div class="prod-price-tag"><?= number_format((float)($prod['price'] ?? 0), 2, ',', '.') ?> €</div>
                        <div class="prod-badges-row">
                            <span class="badge" style="background:#000; color:#fff; font-size:0.7rem;">ID: <?= htmlspecialchars($pId) ?></span>
                            <?php if ($statusIsActive): ?>
                                <span class="badge active" style="font-size:0.7rem;">IM SHOP AKTIV</span>
                            <?php else: ?>
                                <span class="badge ended" style="font-size:0.7rem;">ENTWURF (VERSTECKT)</span>
                            <?php endif; ?>
                            <span class="badge" style="background:#fff; font-size:0.7rem; border-color:#000;">
                                📍 <?= htmlspecialchars($assignedDropName) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- DETAILS & FORMATE -->
                <div class="prod-details-meta" style="background:var(--bg-color); border:2px solid var(--black); padding:10px; margin-bottom:12px;">
                    <div><strong>Format / Maße:</strong> <?= !empty($prod['size']) ? htmlspecialchars($prod['size']) : 'Keine Angabe' ?></div>
                    <div><strong>Packung:</strong> <?= $pStep ?> <?= htmlspecialchars($prod['qty_unit'] ?? 'Stk.') ?> pro Packung</div>
                    <?php if (!empty($prod['has_sizes'])): ?>
                        <div>
                            <strong>Größen:</strong> 
                            <?php 
                                $sizesArr = is_array($prod['sizes'] ?? null) ? $prod['sizes'] : explode(',', (string)($prod['sizes'] ?? ''));
                                echo htmlspecialchars(implode(', ', array_filter(array_map('trim', $sizesArr))));
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- BESCHREIBUNG -->
                <?php if (!empty($prod['description'])): ?>
                    <div class="prod-desc-box">
                        <?= nl2br(htmlspecialchars($prod['description'])) ?>
                    </div>
                <?php endif; ?>

                <!-- BESTANDS-ÜBERSICHT & BALKEN -->
                <div style="background:#fff; border:2px solid var(--black); padding:10px; margin-bottom:12px; box-shadow:3px 3px 0 var(--black);">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-weight:900; font-size:0.8rem; text-transform:uppercase; margin-bottom:4px;">
                        <span>Lagerbestand:</span>
                        <strong style="color:<?= $pIsSoldOut ? 'var(--accent)' : '#000' ?>;">
                            <?= number_format($pCurStock, 0, ',', '.') ?> / <?= number_format($pInitStock, 0, ',', '.') ?> Stk.
                        </strong>
                    </div>
                    <div style="width:100%; height:14px; background:#e5e5e5; border:2px solid var(--black); overflow:hidden;">
                        <div style="height:100%; width:<?= min(100, max(0, $pPercent)) ?>%; background:<?= $pIsSoldOut ? 'var(--accent)' : ($pPercent <= 10 ? '#ffd700' : '#00cc55') ?>; border-right:2px solid var(--black);"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:800; opacity:0.8; margin-top:4px;">
                        <span><?= $pAvailPacks ?> Packungen bestellbar</span>
                        <span><?= $pSoldPacks ?> Packungen verkauft</span>
                    </div>
                </div>
            </div>

            <!-- AKTIONEN -->
            <div class="prod-actions-row">
                <button type="button" class="neo-btn-secondary" style="font-size:0.82rem; padding:6px 10px;" onclick="editProduct('<?= htmlspecialchars($pId, ENT_QUOTES, 'UTF-8') ?>')">
                    ✏️ Bearbeiten
                </button>
                <button type="button" class="neo-btn-secondary" style="font-size:0.82rem; padding:6px 10px;" onclick="openStockModal('<?= htmlspecialchars($pId) ?>', '<?= htmlspecialchars(addslashes($prod['title'])) ?>', <?= $pCurStock ?>, <?= $pInitStock ?>, <?= $pStep ?>)">
                    📦 Bestand (<?= $pCurStock ?> Stk.)
                </button>
                <form method="POST" action="admin_analytics.php" onsubmit="return confirm('Möchtest du das Produkt &quot;<?= htmlspecialchars(addslashes($prod['title'])) ?>&quot; wirklich löschen?');" style="margin-left:auto; display:inline;">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($pId) ?>">
                    <button type="submit" class="neo-btn-danger" style="padding:6px 10px; font-size:0.82rem;">
                        🗑 Löschen
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- =========================================================
         SECTION 2: DATENANALYSE & VERKAUFSZAHLEN
         ========================================================= -->
    <div class="section-title">VERKAUFSANALYSE</div>

    <!-- FILTERLEISTE -->
    <div class="analytics-filter-bar">
        <div>
            <span style="font-weight:900; text-transform:uppercase; font-size:0.85rem; opacity:0.7; display:block; margin-bottom:6px;">
                Aktiver Filter:
            </span>
            <strong style="font-family:'Klebekunst Headline', sans-serif; font-size:1.8rem; color:var(--accent);">
                <?= $filterTitle ?>
            </strong>
        </div>
        <div class="filter-buttons">
            <a href="admin_analytics.php?drop=all" class="filter-btn <?= $filterDrop === 'all' ? 'active' : '' ?>">
                Alle Drops (Gesamt)
            </a>
            <?php foreach ($drops as $d): ?>
                <a href="admin_analytics.php?drop=<?= urlencode($d['id']) ?>" class="filter-btn <?= $filterDrop === $d['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($d['name']) ?>
                    <?php if ($activeDrop && $activeDrop['id'] === $d['id']): ?>
                        &bull;
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if (isset($analytics['drops_summary']['unassigned']) && $analytics['drops_summary']['unassigned']['order_count'] > 0): ?>
                <a href="admin_analytics.php?drop=unassigned" class="filter-btn <?= $filterDrop === 'unassigned' ? 'active' : '' ?>">
                    Ohne Drop (<?= $analytics['drops_summary']['unassigned']['order_count'] ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI CARDS -->
    <div class="kpi-grid">
        <div class="kpi-box" style="border-color: var(--black);">
            <span class="kpi-title">Gesamtumsatz</span>
            <span class="kpi-value" style="color:var(--accent);"><?= number_format($analytics['kpi']['total_revenue'], 2, ',', '.') ?> €</span>
        </div>
        <div class="kpi-box" style="border-color: #1e90ff;">
            <span class="kpi-title">Bestellungen</span>
            <span class="kpi-value"><?= $analytics['kpi']['total_orders'] ?></span>
        </div>
        <div class="kpi-box" style="border-color: #32cd32;">
            <span class="kpi-title">Verkaufte Artikel</span>
            <span class="kpi-value"><?= $analytics['kpi']['total_units'] ?></span>
        </div>
        <div class="kpi-box" style="border-color: #d4af37;">
            <span class="kpi-title">Ø Bestellwert</span>
            <span class="kpi-value" style="font-size:3.2rem;"><?= number_format($analytics['kpi']['avg_order_value'], 2, ',', '.') ?> €</span>
        </div>
    </div>

    <!-- =========================================================
         SECTION: BESTANDSMANAGEMENT & LAGERBESTAND
         ========================================================= -->
    <h3 style="font-family:'Klebekunst Headline', sans-serif; font-size:1.8rem; text-transform:uppercase; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <span>LAGERBESTAND &amp; STOCK-ÜBERSICHT (5.000 STÜCK BASIS)</span>
        <span style="font-family:'Inter', sans-serif; font-size:0.85rem; font-weight:800; background:#fff; border:2px solid var(--black); padding:4px 10px; box-shadow:2px 2px 0 var(--black);">
            ECHTZEIT-SYNCHRONISIERT MIT STOCK.JSON
        </span>
    </h3>

    <div class="stock-dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:22px; margin-bottom:35px;">
        <?php foreach ($stockOverview as $sPid => $sData): 
            $sInitial = (int)($sData['initial'] ?? 5000);
            $sStock = (int)($sData['stock'] ?? 0);
            $sSold = (int)($sData['sold'] ?? 0);
            $sQtyStep = (int)($sData['qty_step'] ?? 50);
            $sAvailablePacks = (int)($sData['available_packs'] ?? 0);
            $sSoldPacks = (int)($sData['sold_packs'] ?? 0);
            $sInitialPacks = (int)($sData['initial_packs'] ?? 100);
            $sPercentRemaining = (float)($sData['percent_remaining'] ?? 0);
            $sIsSoldOut = !empty($sData['is_sold_out']);

            // Badge & Leisten Styling im Neo-Brutalismus
            if ($sIsSoldOut) {
                $badgeBg = 'var(--accent)';
                $badgeColor = '#ffffff';
                $badgeText = 'AUSVERKAUFT (0 / ' . number_format($sInitial, 0, ',', '.') . ' ÜBRIG)';
                $barFillColor = 'var(--accent)';
            } elseif ($sStock <= 500) {
                $badgeBg = '#ffd700';
                $badgeColor = '#000000';
                $badgeText = number_format($sStock, 0, ',', '.') . ' / ' . number_format($sInitial, 0, ',', '.') . ' ÜBRIG (KNAPP)';
                $barFillColor = '#f59e0b';
            } else {
                $badgeBg = '#00ff66';
                $badgeColor = '#000000';
                $badgeText = number_format($sStock, 0, ',', '.') . ' / ' . number_format($sInitial, 0, ',', '.') . ' ÜBRIG';
                $barFillColor = '#00cc55';
            }
        ?>
        <div class="neo-card" style="padding:22px; background:#fff; border:var(--border-thick); box-shadow:8px 8px 0 var(--black); border-radius:2px 255px 3px 225px / 255px 5px 225px 3px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; gap:10px;">
                <div>
                    <h4 style="font-family:'Klebekunst Headline', sans-serif; font-size:1.8rem; line-height:1; text-transform:uppercase; margin:0 0 4px 0;">
                        <?= htmlspecialchars($sData['title']) ?>
                    </h4>
                    <span style="font-size:0.78rem; font-weight:800; color:#555; text-transform:uppercase;">
                        <?= $sQtyStep ?> Stück pro Packung &bull; <?= $sInitialPacks ?> Packungen initial
                    </span>
                </div>
                <span class="badge" style="background:<?= $badgeBg ?>; color:<?= $badgeColor ?>; font-size:0.85rem; padding:6px 10px; white-space:nowrap; border:2px solid var(--black); box-shadow:2px 2px 0 var(--black);">
                    <?= $badgeText ?>
                </span>
            </div>

            <!-- 3 STATS BOXEN -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:16px;">
                <div style="background:var(--bg-color); border:2px solid var(--black); padding:10px 8px; text-align:center;">
                    <div style="font-size:0.7rem; font-weight:900; text-transform:uppercase; color:#666; margin-bottom:2px;">Maximalbestand</div>
                    <div style="font-family:'Klebekunst Headline', sans-serif; font-size:1.6rem; line-height:1;">
                        <?= number_format($sInitial, 0, ',', '.') ?>
                    </div>
                    <div style="font-size:0.72rem; font-weight:800; color:#444;">Stück (<?= $sInitialPacks ?> P.)</div>
                </div>

                <div style="background:#fff8e6; border:2px solid var(--black); padding:10px 8px; text-align:center;">
                    <div style="font-size:0.7rem; font-weight:900; text-transform:uppercase; color:#b45309; margin-bottom:2px;">Verkaufte Stückzahl</div>
                    <div style="font-family:'Klebekunst Headline', sans-serif; font-size:1.6rem; line-height:1; color:var(--accent);">
                        <?= number_format($sSold, 0, ',', '.') ?>
                    </div>
                    <div style="font-size:0.72rem; font-weight:800; color:#444;">Stück (<?= $sSoldPacks ?> P.)</div>
                </div>

                <div style="background:<?= $sIsSoldOut ? '#ffe5e5' : '#e6fffa' ?>; border:2px solid var(--black); padding:10px 8px; text-align:center;">
                    <div style="font-size:0.7rem; font-weight:900; text-transform:uppercase; color:<?= $sIsSoldOut ? '#cc0000' : '#047857' ?>; margin-bottom:2px;">Restbestand</div>
                    <div style="font-family:'Klebekunst Headline', sans-serif; font-size:1.6rem; line-height:1; color:<?= $sIsSoldOut ? '#cc0000' : '#047857' ?>;">
                        <?= number_format($sStock, 0, ',', '.') ?>
                    </div>
                    <div style="font-size:0.72rem; font-weight:800; color:#444;">Stück (<?= $sAvailablePacks ?> P.)</div>
                </div>
            </div>

            <!-- VISUELLE FORTSCHRITTSLEISTE IM NEO-BRUTALISMUS STIL -->
            <div style="background:#fff; border:2px solid var(--black); padding:10px; box-shadow:3px 3px 0 var(--black);">
                <div style="display:flex; justify-content:space-between; align-items:center; font-weight:900; font-size:0.8rem; text-transform:uppercase; margin-bottom:6px;">
                    <span>Bestandsfortschritt:</span>
                    <strong style="color:var(--black);"><?= number_format($sStock, 0, ',', '.') ?> / <?= number_format($sInitial, 0, ',', '.') ?> Stück übrig (<?= $sPercentRemaining ?>%)</strong>
                </div>
                <div style="width:100%; height:18px; background:#e5e5e5; border:2px solid var(--black); overflow:hidden; position:relative;">
                    <div style="height:100%; width:<?= min(100, max(0, $sPercentRemaining)) ?>%; background:<?= $barFillColor ?>; border-right:2px solid var(--black); transition:width 0.3s ease;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.72rem; font-weight:800; opacity:0.7; margin-top:4px; text-transform:uppercase;">
                    <span>0 Stk. (Ausverkauft)</span>
                    <span>Noch <?= $sAvailablePacks ?> Packungen bestellbar</span>
                    <span><?= number_format($sInitial, 0, ',', '.') ?> Stk.</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- VERKAUFSZAHLEN PRO PRODUKT -->
    <h3 style="font-family:'Klebekunst Headline', sans-serif; font-size:1.8rem; text-transform:uppercase; margin-bottom:15px;">
        VERKAUFTE STÜCKZAHLEN NACH PRODUKT
    </h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th>Lagerbestand (stock.json)</th>
                    <th>Stückzahl Verkauft</th>
                    <th>Größen / Details</th>
                    <th>Einzelpreis</th>
                    <th>Produkt-Umsatz</th>
                    <th>Umsatzanteil</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $hasProductsSold = false;
                foreach ($analytics['products_stats'] as $pStat):
                    if ($pStat['total_qty'] > 0) $hasProductsSold = true;
                    $stockItem = $stockOverview[$pStat['id']] ?? null;
                    $curSt = (int)($stockItem['stock'] ?? 5000);
                    $initSt = (int)($stockItem['initial'] ?? 5000);
                    $availP = (int)($stockItem['available_packs'] ?? floor($curSt / ($pStat['qty_step'] ?? 50)));
                    $badgeClass = ($curSt <= 0) ? 'ended' : (($curSt <= 500) ? 'open' : 'active');
                ?>
                <tr>
                    <td>
                        <strong style="font-size:1.1rem;"><?= htmlspecialchars($pStat['title']) ?></strong>
                    </td>
                    <td>
                        <span class="badge <?= $badgeClass ?>">
                            <?= number_format($curSt, 0, ',', '.') ?> / <?= number_format($initSt, 0, ',', '.') ?> übrig
                        </span>
                        <div style="font-size:0.75rem; font-weight:800; opacity:0.75; margin-top:3px;">
                            <?= $availP ?> Packungen verfügbar
                        </div>
                    </td>
                    <td>
                        <strong style="font-family:'Klebekunst Headline', sans-serif; font-size:1.8rem; color:var(--accent);">
                            <?= $pStat['total_qty'] ?>
                        </strong>
                        <span style="font-size:0.85rem; opacity:0.8;"> <?= htmlspecialchars($pStat['qty_unit'] ?? 'Stk.') ?></span>
                    </td>
                    <td>
                        <?php if (!empty($pStat['sizes'])): ?>
                            <div class="size-pills">
                                <?php foreach ($pStat['sizes'] as $sz => $sqty): ?>
                                    <span class="size-pill">
                                        <strong><?= htmlspecialchars($sz) ?>:</strong> <?= $sqty ?>x
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="opacity:0.6;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($pStat['price'], 2, ',', '.') ?> €</td>
                    <td>
                        <strong><?= number_format($pStat['revenue'], 2, ',', '.') ?> €</strong>
                    </td>
                    <td style="min-width: 140px;">
                        <strong><?= $pStat['percent'] ?>%</strong>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?= min(100, $pStat['percent']) ?>%;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (!$hasProductsSold): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px; font-weight:700;">
                            Im ausgewählten Zeitraum bzw. Drop wurden noch keine Produkte verkauft.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- BESTELLUNGEN DIESES DROPS -->
    <h3 style="font-family:'Klebekunst Headline', sans-serif; font-size:1.8rem; text-transform:uppercase; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
        <span>BESTELLUNGEN (<?= count($analytics['filtered_orders']) ?>)</span>
        <a href="admin.php" class="neo-btn-secondary" style="font-size:0.8rem;">Zu den Bestellungen &rarr;</a>
    </h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Datum</th>
                    <th>Drop</th>
                    <th>Kunde</th>
                    <th>Artikel</th>
                    <th>Gesamt</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($analytics['filtered_orders'])): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px;">Keine Bestellungen für diese Auswahl vorhanden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach (array_reverse($analytics['filtered_orders']) as $ord): 
                        $st = strtolower($ord['status'] ?? 'offen');
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ord['id']) ?></strong></td>
                        <td><?= htmlspecialchars($ord['date']) ?></td>
                        <td><span class="badge" style="background:#fff;"><?= htmlspecialchars($ord['resolved_drop_name'] ?? '-') ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($ord['name']) ?></strong><br>
                            <span style="font-size:0.8rem; opacity:0.7;"><?= htmlspecialchars($ord['email']) ?></span>
                        </td>
                        <td>
                            <div style="font-size:0.85rem; line-height:1.3; white-space:pre-wrap;"><?= htmlspecialchars($ord['artikel']) ?></div>
                        </td>
                        <td><strong><?= htmlspecialchars($ord['gesamt']) ?></strong></td>
                        <td>
                            <span class="badge <?= str_replace(' ', '-', $st) ?>"><?= htmlspecialchars(strtoupper($st)) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Shop Status Toggle per Form-Submit mit AJAX und automatischem Fallback
        const shopForm = document.getElementById('shopStatusForm');
        const shopBtn = document.getElementById('shopStatusToggleBtn');
        const shopTxt = document.getElementById('shopStatusText');
        const shopInput = document.getElementById('shopStatusInput');

        if (shopForm) {
            shopForm.addEventListener('submit', function(e) {
                e.preventDefault();
                shopBtn.disabled = true;
                const targetValue = shopInput.value; // '1' (öffnen) oder '0' (schließen)
                
                const params = new URLSearchParams();
                params.append('action', 'set_shop_status');
                params.append('shop_open', targetValue);
                params.append('ajax', '1');

                fetch('admin_analytics.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: params.toString()
                })
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(data => {
                    shopBtn.disabled = false;
                    if (data && data.success) {
                        if (data.shop_open) {
                            shopBtn.className = 'status-toggle-btn is-open';
                            shopTxt.innerText = 'SHOP GEÖFFNET';
                            shopInput.value = '0'; // Nächster Klick schließt
                        } else {
                            shopBtn.className = 'status-toggle-btn is-closed';
                            shopTxt.innerText = 'SHOP GESCHLOSSEN';
                            shopInput.value = '1'; // Nächster Klick öffnet
                        }
                    } else {
                        alert('Fehler: ' + (data && data.error ? data.error : 'Konnte Status nicht speichern.'));
                        shopForm.submit();
                    }
                })
                .catch(err => {
                    shopBtn.disabled = false;
                    console.warn('AJAX-Fehler beim Toggle, sende regulär per POST:', err);
                    shopForm.submit();
                });
            });
        }
    </script>

    <!-- =========================================================
         DANGER ZONE
         ========================================================= -->
    <div id="section-danger" style="margin-top: 60px; margin-bottom: 40px;">
        <div class="section-title" style="color: var(--accent);">⚠️ DANGER ZONE</div>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">

            <!-- BESTELLUNGEN LÖSCHEN -->
            <div style="flex: 1; min-width: 280px; background: #fff; border: 3px solid var(--black); padding: 22px; box-shadow: 8px 8px 0 var(--black); border-left: 6px solid var(--accent);">
                <div style="font-family: 'Klebekunst Headline', sans-serif; font-size: 1.5rem; text-transform: uppercase; margin-bottom: 8px;">🗑 Alle Bestellungen löschen</div>
                <p style="font-size: 0.85rem; font-weight: 700; color: #555; margin-bottom: 16px; line-height: 1.5;">
                    Löscht <strong>alle Einträge</strong> in <code>orders.json</code> unwiderruflich.
                    Die Produktdaten und der Lagerbestand bleiben unberührt.
                </p>
                <form method="POST" action="admin_analytics.php"
                      onsubmit="return confirm('⚠️ ACHTUNG\n\nAlle Bestellungen werden unwiderruflich gelöscht!\n\nBist du absolut sicher?');">
                    <input type="hidden" name="action" value="clear_orders">
                    <button type="submit" style="width: 100%; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.3rem; padding: 10px 20px; background: var(--accent); color: #fff; border: 3px solid var(--black); cursor: pointer; box-shadow: 4px 4px 0 var(--black); text-transform: uppercase; transition: all 0.1s;"
                            onmouseover="this.style.transform='translate(-2px,-2px)'; this.style.boxShadow='6px 6px 0 var(--black)';"
                            onmouseout="this.style.transform=''; this.style.boxShadow='4px 4px 0 var(--black)';"
                            onmousedown="this.style.transform='translate(2px,2px)'; this.style.boxShadow='none';"
                            onmouseup="this.style.transform='translate(-2px,-2px)'; this.style.boxShadow='6px 6px 0 var(--black)';"
                    >✕ ALLE BESTELLUNGEN LÖSCHEN</button>
                </form>
            </div>

            <!-- STÜCKZAHL RESETTEN -->
            <div style="flex: 1; min-width: 280px; background: #fff; border: 3px solid var(--black); padding: 22px; box-shadow: 8px 8px 0 var(--black); border-left: 6px solid #f59e0b;">
                <div style="font-family: 'Klebekunst Headline', sans-serif; font-size: 1.5rem; text-transform: uppercase; margin-bottom: 8px;">🔄 Verkaufte Stückzahl resetten</div>
                <p style="font-size: 0.85rem; font-weight: 700; color: #555; margin-bottom: 16px; line-height: 1.5;">
                    Setzt den <strong>aktuellen Bestand</strong> jedes Produkts auf den <strong>Initialwert</strong> zurück
                    (stock = initial). Bestellungen bleiben erhalten.
                </p>
                <form method="POST" action="admin_analytics.php"
                      onsubmit="return confirm('⚠️ ACHTUNG\n\nDie verkaufte Stückzahl aller Produkte wird zurückgesetzt!\n\nBist du sicher?');">
                    <input type="hidden" name="action" value="reset_stock_sold">
                    <button type="submit" style="width: 100%; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.3rem; padding: 10px 20px; background: #f59e0b; color: #000; border: 3px solid var(--black); cursor: pointer; box-shadow: 4px 4px 0 var(--black); text-transform: uppercase; transition: all 0.1s;"
                            onmouseover="this.style.transform='translate(-2px,-2px)'; this.style.boxShadow='6px 6px 0 var(--black)';"
                            onmouseout="this.style.transform=''; this.style.boxShadow='4px 4px 0 var(--black)';"
                            onmousedown="this.style.transform='translate(2px,2px)'; this.style.boxShadow='none';"
                            onmouseup="this.style.transform='translate(-2px,-2px)'; this.style.boxShadow='6px 6px 0 var(--black)';"
                    >↺ STÜCKZAHL RESETTEN</button>
                </form>
            </div>

        </div>
    </div>

    <!-- =========================================================
         MODAL 1: PRODUKT ANLEGEN / BEARBEITEN
         ========================================================= -->
    <div id="productModal" class="neo-modal-backdrop" onclick="if(event.target===this) closeProductModal();">
        <div class="neo-modal-card">
            <div class="neo-modal-header">
                <h2 id="productModalTitle" class="neo-modal-title">NEUES PRODUKT HINZUFÜGEN</h2>
                <button type="button" class="neo-modal-close" onclick="closeProductModal()">✕</button>
            </div>
            <div class="neo-modal-body">
                <form method="POST" action="admin_analytics.php" id="productForm">
                    <input type="hidden" name="action" id="form_action" value="save_product">
                    <input type="hidden" name="original_id" id="form_original_id" value="">
                    <input type="hidden" name="existing_image" id="prod_existing_image" value="">
                    <input type="hidden" name="existing_gallery_json" id="prod_existing_gallery_json" value="">
                    <input type="hidden" name="new_gallery_base64_json" id="prod_new_gallery_base64_json" value="">

                    <div class="form-grid-2">
                        <!-- TITEL -->
                        <div class="drop-form-group">
                            <label for="prod_title">Produktname / Titel *</label>
                            <input type="text" id="prod_title" name="title" class="neo-input" placeholder="z. B. Waldstadion (50 Stück)" required>
                        </div>

                        <!-- PRODUKT ID / SLUG -->
                        <div class="drop-form-group">
                            <label for="prod_id">Artikel-ID / Slug (optional)</label>
                            <input type="text" id="prod_id" name="product_id" class="neo-input" placeholder="z. B. sticker4 (leer = automatisch)">
                            <span class="form-help-hint">Eindeutige Kennung für Warenkorb & Lagerbestand.</span>
                        </div>

                        <!-- PREIS -->
                        <div class="drop-form-group">
                            <label for="prod_price">Preis (€) *</label>
                            <input type="number" step="0.01" min="0" id="prod_price" name="price" class="neo-input" placeholder="5.00" required>
                        </div>

                        <!-- DROP ZUWEISUNG -->
                        <div class="drop-form-group">
                            <label for="prod_drop_id">Drop-Zuweisung</label>
                            <select id="prod_drop_id" name="drop_id" class="neo-input" style="height:48px;">
                                <option value="">Alle Drops / Immer im Shop</option>
                                <?php foreach ($drops as $d): ?>
                                    <option value="<?= htmlspecialchars($d['id']) ?>">
                                        <?= htmlspecialchars($d['name']) ?> <?= ($activeDrop && $activeDrop['id'] === $d['id']) ? '(Aktuell Aktiv)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- LAGERBESTAND (ANFANG) -->
                        <div class="drop-form-group">
                            <label for="prod_stock_initial">Maximaler Anfangsbestand (Stück) *</label>
                            <input type="number" min="0" id="prod_stock_initial" name="stock_initial" class="neo-input" value="5000" required>
                            <span class="form-help-hint">z. B. 5.000 Stück als Basis für Prozentbalken.</span>
                        </div>

                        <!-- LAGERBESTAND (AKTUELL) -->
                        <div class="drop-form-group">
                            <label for="prod_stock_pieces">Aktueller Restbestand (Stück) *</label>
                            <input type="number" min="0" id="prod_stock_pieces" name="stock_pieces" class="neo-input" value="5000" required>
                            <span class="form-help-hint">Aktuell verfügbare physische Stückzahl.</span>
                        </div>

                        <!-- PACKUNGSGRÖSSE (QTY_STEP) -->
                        <div class="drop-form-group">
                            <label for="prod_qty_step">Stück pro Packung / Einheit *</label>
                            <input type="number" min="1" id="prod_qty_step" name="qty_step" class="neo-input" value="50" required>
                            <span class="form-help-hint">z. B. 50 bei Stickern (5000 Stk. = 100 Packungen), 1 bei Shirts.</span>
                        </div>

                        <!-- EINHEIT -->
                        <div class="drop-form-group">
                            <label for="prod_qty_unit">Mengeneinheit (Label)</label>
                            <input type="text" id="prod_qty_unit" name="qty_unit" class="neo-input" value=" Stk.">
                            <span class="form-help-hint">z. B. " Stk." oder " Pack"</span>
                        </div>

                        <!-- FORMAT / DETAILS -->
                        <div class="drop-form-group">
                            <label for="prod_size">Format / Maße / Details</label>
                            <input type="text" id="prod_size" name="size" class="neo-input" placeholder="z. B. Ø 8cm • Rund oder 21 × 3,1 cm">
                        </div>

                        <!-- SICHTBARKEIT STATUS -->
                        <div class="drop-form-group">
                            <label for="prod_status">Status im Shop</label>
                            <select id="prod_status" name="status" class="neo-input" style="height:48px;">
                                <option value="active">Aktiv (Im Shop sichtbar)</option>
                                <option value="hidden">Entwurf (Ausgeblendet)</option>
                            </select>
                        </div>

                        <!-- REF KÜRZEL FÜR VERSANDETIKETTEN -->
                        <div class="drop-form-group">
                            <label for="prod_ref_prefix">Versand-Kürzel (REF / Etikett)</label>
                            <input type="text" id="prod_ref_prefix" name="ref_prefix" class="neo-input" placeholder="z. B. Waldstadion, Schwenker oder RebellingKids">
                            <span class="form-help-hint">Format auf Etikett: [Anzahl]-Kürzel (z. B. 1-Waldstadion).</span>
                        </div>

                        <!-- GRÖSSEN OPTIONEN -->
                        <div class="form-group-full">
                            <label class="neo-checkbox-label" style="margin:4px 0 10px 0;">
                                <input type="checkbox" id="prod_has_sizes" name="has_sizes" value="1" class="neo-checkbox" onchange="toggleSizesInput(this.checked)">
                                <span>Produkt hat wählbare Größen (z. B. Textilien / Shirts)</span>
                            </label>
                            <div id="prod_sizes_wrap" style="display:none; margin-bottom:12px;">
                                <label for="prod_sizes" style="font-size:0.85rem; font-weight:900; text-transform:uppercase;">Verfügbare Größen (kommagetrennt)</label>
                                <input type="text" id="prod_sizes" name="sizes" class="neo-input" placeholder="S, M, L, XL, XXL" style="width:100%; margin-top:4px;">
                            </div>
                        </div>

                        <!-- BESCHREIBUNG -->
                        <div class="form-group-full drop-form-group">
                            <label for="prod_description">Beschreibung / Produkttext</label>
                            <textarea id="prod_description" name="description" class="neo-input" rows="3" placeholder="z. B. Runder Aufkleber Waldstadion-Motiv, 8cm Durchmesser. Wetterfestes Outdoor-Vinyl."></textarea>
                        </div>

                        <!-- BILDER-UPLOAD HAUPTBILD -->
                        <div class="form-group-full drop-form-group" style="background:var(--bg-color); border:2px solid var(--black); padding:16px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                                <label style="font-size:1.1rem; color:var(--accent); font-weight:900; margin:0;">HAUPTBILD DES PRODUKTS</label>
                                <span id="main_image_status_badge" class="badge" style="background:#fff; font-size:0.75rem; display:none;"></span>
                            </div>
                            <input type="hidden" name="product_image_base64" id="prod_image_base64" value="">
                            
                            <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">
                                <div style="flex:1; min-width:260px;">
                                    <label for="input_product_image" style="display:block; margin-bottom:6px; font-weight:700;">Bilddatei vom Computer/Handy auswählen:</label>
                                    <input type="file" id="input_product_image" accept="image/jpeg,image/png,image/webp,image/gif" class="neo-input" style="width:100%; background:#fff;">
                                    
                                    <div style="display:flex; gap:8px; margin-top:8px; flex-wrap:wrap;">
                                        <button type="button" class="neo-btn-secondary" style="font-size:0.78rem; padding:4px 8px;" onclick="toggleServerImageDrawer('main')">
                                            📁 Server-Bilder öffnen (<?= count($serverImagesDetailed) ?>)
                                        </button>
                                        <button type="button" class="neo-btn-secondary" style="font-size:0.78rem; padding:4px 8px; color:var(--accent);" onclick="removeMainImage()">
                                            ✕ Bild entfernen
                                        </button>
                                    </div>

                                    <!-- Server Image Drawer für Hauptbild -->
                                    <div id="server_image_drawer_main" class="server-img-drawer" data-drawer-type="main">
                                        <div class="drawer-top-bar">
                                            <div class="drawer-filter-tabs">
                                                <button type="button" class="drawer-tab-btn active" onclick="filterDrawerImages('main', 'all', this)">Alle (<?= count($serverImagesDetailed) ?>)</button>
                                                <button type="button" class="drawer-tab-btn" onclick="filterDrawerImages('main', 'main', this)">Motive</button>
                                                <button type="button" class="drawer-tab-btn" onclick="filterDrawerImages('main', 'detail', this)">Details</button>
                                                <button type="button" class="drawer-tab-btn" onclick="filterDrawerImages('main', 'new', this)">Neu</button>
                                            </div>
                                            <div class="drawer-search-wrap">
                                                <span class="drawer-search-icon">🔍</span>
                                                <input type="text" class="drawer-search-input" placeholder="Suchen..." oninput="searchDrawerImages('main', this.value)">
                                            </div>
                                            <button type="button" class="drawer-close-btn" onclick="toggleServerImageDrawer('main')" title="Schließen">✕</button>
                                        </div>
                                        <div class="server-img-grid" id="grid_server_img_main">
                                            <?php foreach ($serverImagesDetailed as $sImg): ?>
                                                <button type="button" class="server-img-choice" 
                                                    data-path="<?= htmlspecialchars($sImg['path']) ?>"
                                                    data-type="<?= $sImg['type'] ?>"
                                                    data-new="<?= $sImg['is_new'] ? '1' : '0' ?>"
                                                    data-name="<?= htmlspecialchars(strtolower($sImg['filename'])) ?>"
                                                    onclick="selectServerImage('<?= htmlspecialchars($sImg['path']) ?>', 'main', this)" 
                                                    title="<?= htmlspecialchars($sImg['filename']) ?> (<?= $sImg['size_human'] ?>, <?= $sImg['date_str'] ?>)">
                                                    <img src="<?= htmlspecialchars($sImg['path']) ?>" alt="<?= htmlspecialchars($sImg['filename']) ?>" loading="lazy" onerror="this.src='images/placeholder.png';">
                                                    <?php if ($sImg['is_new']): ?>
                                                        <span class="choice-badge">NEU</span>
                                                    <?php elseif ($sImg['is_detail']): ?>
                                                        <span class="choice-badge badge-detail">D</span>
                                                    <?php endif; ?>
                                                    <span class="choice-label"><?= htmlspecialchars($sImg['filename']) ?></span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div style="margin-top:10px;">
                                        <label for="prod_image_url" style="display:block; margin-bottom:4px; font-size:0.8rem; font-weight:800;">Oder direkten Bildpfad angeben:</label>
                                        <input type="text" id="prod_image_url" name="image_url" class="neo-input" placeholder="images/sticker1.png" style="width:100%; font-size:0.85rem;" oninput="onImageUrlInput(this.value)">
                                    </div>
                                </div>

                                <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                    <div style="width:120px; height:120px; border:3px solid var(--black); background:#fff; box-shadow:4px 4px 0 var(--black); display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;">
                                        <img id="preview_main_image" src="" alt="Vorschau" style="width:100%; height:100%; object-fit:contain; display:none;" onerror="this.src='images/placeholder.png'; this.onerror=null;">
                                        <span id="preview_placeholder_text" style="font-size:0.75rem; font-weight:800; text-align:center; opacity:0.5;">KEIN BILD</span>
                                    </div>
                                    <span style="font-size:0.7rem; font-weight:800; opacity:0.6; text-transform:uppercase;">Vorschau</span>
                                </div>
                            </div>
                        </div>

                        <!-- DETAILBILDER / GALERIE -->
                        <div class="form-group-full drop-form-group" style="background:#fff; border:2px solid var(--black); padding:16px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:8px;">
                                <label style="font-size:1rem; margin:0; font-weight:900;">DETAILBILDER / GALERIE (FÜR SHOP-LIGHTBOX)</label>
                                <button type="button" class="neo-btn-secondary" style="font-size:0.75rem; padding:3px 8px;" onclick="toggleServerImageDrawer('gallery')">
                                    📁 Server-Bilder zur Galerie hinzufügen (<?= count($serverImagesDetailed) ?>)
                                </button>
                            </div>
                            <span class="form-help-hint" style="display:block; margin-bottom:10px;">Bilder für die Vollbild-Detailansicht. Klicke auf ⭐, um ein Bild zum Hauptbild zu machen, oder auf ✕ zum Entfernen.</span>
                            
                            <input type="file" id="input_product_gallery" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="neo-input" style="width:100%; background:var(--bg-color);">
                            
                            <!-- Server Image Drawer für Galerie -->
                            <div id="server_image_drawer_gallery" class="server-img-drawer" data-drawer-type="gallery">
                                <div class="drawer-top-bar">
                                    <div class="drawer-filter-tabs">
                                        <button type="button" class="drawer-tab-btn active" onclick="filterDrawerImages('gallery', 'all', this)">Alle (<?= count($serverImagesDetailed) ?>)</button>
                                        <button type="button" class="drawer-tab-btn" onclick="filterDrawerImages('gallery', 'main', this)">Motive</button>
                                        <button type="button" class="drawer-tab-btn" onclick="filterDrawerImages('gallery', 'detail', this)">Details</button>
                                        <button type="button" class="drawer-tab-btn" onclick="filterDrawerImages('gallery', 'new', this)">Neu</button>
                                    </div>
                                    <div class="drawer-search-wrap">
                                        <span class="drawer-search-icon">🔍</span>
                                        <input type="text" class="drawer-search-input" placeholder="Suchen..." oninput="searchDrawerImages('gallery', this.value)">
                                    </div>
                                    <button type="button" class="drawer-close-btn" onclick="toggleServerImageDrawer('gallery')" title="Schließen">✕</button>
                                </div>
                                <div class="server-img-grid" id="grid_server_img_gallery">
                                    <?php foreach ($serverImagesDetailed as $sImg): ?>
                                        <button type="button" class="server-img-choice" 
                                            data-path="<?= htmlspecialchars($sImg['path']) ?>"
                                            data-type="<?= $sImg['type'] ?>"
                                            data-new="<?= $sImg['is_new'] ? '1' : '0' ?>"
                                            data-name="<?= htmlspecialchars(strtolower($sImg['filename'])) ?>"
                                            onclick="selectServerImage('<?= htmlspecialchars($sImg['path']) ?>', 'gallery', this)" 
                                            title="<?= htmlspecialchars($sImg['filename']) ?> (<?= $sImg['size_human'] ?>, <?= $sImg['date_str'] ?>)">
                                            <img src="<?= htmlspecialchars($sImg['path']) ?>" alt="<?= htmlspecialchars($sImg['filename']) ?>" loading="lazy" onerror="this.src='images/placeholder.png';">
                                            <?php if ($sImg['is_new']): ?>
                                                <span class="choice-badge">NEU</span>
                                            <?php elseif ($sImg['is_detail']): ?>
                                                <span class="choice-badge badge-detail">D</span>
                                            <?php endif; ?>
                                            <span class="choice-label"><?= htmlspecialchars($sImg['filename']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div id="gallery_preview_container" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;"></div>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; margin-top:20px; border-top:3px solid var(--black); padding-top:16px;">
                        <button type="submit" class="neo-btn-primary" style="flex:1; font-size:1.4rem;">
                            ✓ PRODUKT SPEICHERN
                        </button>
                        <button type="button" class="neo-btn-secondary" onclick="closeProductModal()">
                            ABBRECHEN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- =========================================================
         MODAL 2: SCHNELL-BESTANDSANPASSUNG
         ========================================================= -->
    <div id="stockModal" class="neo-modal-backdrop" onclick="if(event.target===this) closeStockModal();">
        <div class="neo-modal-card" style="max-width:500px;">
            <div class="neo-modal-header">
                <h2 class="neo-modal-title" style="font-size:1.8rem;">BESTAND ANPASSEN</h2>
                <button type="button" class="neo-modal-close" onclick="closeStockModal()">✕</button>
            </div>
            <div class="neo-modal-body">
                <form method="POST" action="admin_analytics.php">
                    <input type="hidden" name="action" value="update_product_stock">
                    <input type="hidden" name="product_id" id="quick_stock_pid" value="">

                    <div style="background:var(--bg-color); border:2px solid var(--black); padding:12px; margin-bottom:18px;">
                        <h4 id="quick_stock_title" style="font-family:'Klebekunst Headline', sans-serif; font-size:1.4rem; margin:0 0 4px 0; text-transform:uppercase;"></h4>
                        <span id="quick_stock_id_badge" class="badge" style="background:#000; color:#fff;"></span>
                    </div>

                    <div class="drop-form-group">
                        <label for="quick_stock_pieces">Aktueller Restbestand (Stück) *</label>
                        <input type="number" min="0" id="quick_stock_pieces" name="stock_pieces" class="neo-input" required oninput="calcQuickPacks()">
                        <span id="quick_packs_hint" class="form-help-hint" style="font-weight:900; color:var(--accent);"></span>
                    </div>

                    <div class="drop-form-group">
                        <label for="quick_stock_initial">Maximaler Anfangsbestand (Stück) *</label>
                        <input type="number" min="0" id="quick_stock_initial" name="stock_initial" class="neo-input" required>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="neo-btn-primary" style="flex:1;">
                            ✓ BESTAND SPEICHERN
                        </button>
                        <button type="button" class="neo-btn-secondary" onclick="closeStockModal()">
                            ABBRECHEN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.ALL_PRODUCTS = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?> || {};
        window.PRODUCT_STOCKS = <?= json_encode($stockOverview, JSON_UNESCAPED_UNICODE) ?> || {};
        window.SERVER_IMAGES = <?= json_encode($serverImages, JSON_UNESCAPED_UNICODE) ?> || [];

        let currentGallery = [];
        let newGalleryBase64 = [];

        function compressImageToDataUrl(file, maxDimension = 1600, quality = 0.88) {
            return new Promise((resolve, reject) => {
                if (!file || !file.type.startsWith('image/')) {
                    return reject(new Error('Keine gültige Bilddatei'));
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        let w = img.width;
                        let h = img.height;
                        if (w > maxDimension || h > maxDimension) {
                            if (w >= h) {
                                h = Math.round((h / w) * maxDimension);
                                w = maxDimension;
                            } else {
                                w = Math.round((w / h) * maxDimension);
                                h = maxDimension;
                            }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        const mime = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
                        const dataUrl = canvas.toDataURL(mime, quality);
                        resolve({ dataUrl, width: w, height: h });
                    };
                    img.onerror = () => reject(new Error('Bild konnte nicht dekodiert werden'));
                    img.src = e.target.result;
                };
                reader.onerror = () => reject(new Error('Datei konnte nicht gelesen werden'));
                reader.readAsDataURL(file);
            });
        }

        function setMainImagePreview(src, badgeText = '') {
            const preview = document.getElementById('preview_main_image');
            const placeholder = document.getElementById('preview_placeholder_text');
            const badge = document.getElementById('main_image_status_badge');

            if (src && src.trim() !== '') {
                preview.src = src;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                preview.src = '';
                preview.style.display = 'none';
                placeholder.style.display = 'block';
            }

            if (badgeText) {
                badge.innerText = badgeText;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        function removeMainImage() {
            document.getElementById('input_product_image').value = '';
            document.getElementById('prod_image_base64').value = '';
            document.getElementById('prod_image_url').value = '';
            document.getElementById('prod_existing_image').value = 'images/placeholder.png';
            setMainImagePreview('images/placeholder.png', 'Platzhalter aktiv');
        }

        function toggleServerImageDrawer(drawerType) {
            const mainDrawer = document.getElementById('server_image_drawer_main');
            const galDrawer = document.getElementById('server_image_drawer_gallery');
            if (drawerType === 'main') {
                if (mainDrawer) {
                    mainDrawer.classList.toggle('is-open');
                    if (mainDrawer.classList.contains('is-open')) {
                        const inp = mainDrawer.querySelector('.drawer-search-input');
                        if (inp) setTimeout(() => inp.focus(), 100);
                    }
                }
                if (galDrawer) galDrawer.classList.remove('is-open');
            } else {
                if (galDrawer) {
                    galDrawer.classList.toggle('is-open');
                    if (galDrawer.classList.contains('is-open')) {
                        const inp = galDrawer.querySelector('.drawer-search-input');
                        if (inp) setTimeout(() => inp.focus(), 100);
                    }
                }
                if (mainDrawer) mainDrawer.classList.remove('is-open');
            }
        }

        function filterDrawerImages(drawerType, filter, btnEl) {
            const drawer = document.getElementById('server_image_drawer_' + drawerType);
            if (!drawer) return;
            if (btnEl) {
                drawer.querySelectorAll('.drawer-tab-btn').forEach(b => b.classList.remove('active'));
                btnEl.classList.add('active');
            }
            const searchVal = (drawer.querySelector('.drawer-search-input')?.value || '').toLowerCase().trim();
            applyDrawerFilters(drawerType, filter, searchVal);
        }

        function searchDrawerImages(drawerType, query) {
            const drawer = document.getElementById('server_image_drawer_' + drawerType);
            if (!drawer) return;
            const activeTab = drawer.querySelector('.drawer-tab-btn.active');
            let filter = 'all';
            if (activeTab) {
                const txt = activeTab.innerText.toLowerCase();
                if (txt.includes('motiv')) filter = 'main';
                else if (txt.includes('detail')) filter = 'detail';
                else if (txt.includes('neu')) filter = 'new';
            }
            applyDrawerFilters(drawerType, filter, (query || '').toLowerCase().trim());
        }

        function applyDrawerFilters(drawerType, filter, searchVal) {
            const grid = document.getElementById('grid_server_img_' + drawerType);
            if (!grid) return;
            const items = grid.querySelectorAll('.server-img-choice');
            let visibleCount = 0;
            items.forEach(item => {
                const type = item.getAttribute('data-type') || 'main';
                const isNew = item.getAttribute('data-new') === '1';
                const name = (item.getAttribute('data-name') || '').toLowerCase();
                
                let matchFilter = true;
                if (filter === 'main') matchFilter = (type === 'main');
                else if (filter === 'detail') matchFilter = (type === 'detail');
                else if (filter === 'new') matchFilter = isNew;

                let matchSearch = true;
                if (searchVal) {
                    matchSearch = name.includes(searchVal);
                }

                const show = matchFilter && matchSearch;
                item.style.display = show ? 'block' : 'none';
                if (show) visibleCount++;
            });
        }

        function selectServerImage(path, target, btnEl) {
            if (target === 'main') {
                document.getElementById('prod_image_url').value = path;
                document.getElementById('prod_existing_image').value = path;
                document.getElementById('prod_image_base64').value = '';
                document.getElementById('input_product_image').value = '';
                setMainImagePreview(path + '?v=' + Date.now(), 'Server-Bild gewählt');

                const grid = document.getElementById('grid_server_img_main');
                if (grid) {
                    grid.querySelectorAll('.server-img-choice').forEach(b => b.classList.remove('selected'));
                }
                if (btnEl) btnEl.classList.add('selected');

                const drawer = document.getElementById('server_image_drawer_main');
                if (drawer) drawer.classList.remove('is-open');
            } else if (target === 'gallery') {
                if (!currentGallery.includes(path)) {
                    currentGallery.push(path);
                    updateGalleryUI();
                }
                if (btnEl) {
                    btnEl.classList.add('selected');
                    setTimeout(() => btnEl.classList.remove('selected'), 1200);
                }
            }
        }

        function onImageUrlInput(val) {
            val = (val || '').trim();
            if (val) {
                document.getElementById('prod_existing_image').value = val;
                document.getElementById('prod_image_base64').value = '';
                setMainImagePreview(val, 'Pfad aktiv');
            }
        }

        function makeMainImageFromGallery(imgSrc) {
            document.getElementById('prod_image_url').value = imgSrc;
            document.getElementById('prod_existing_image').value = imgSrc;
            document.getElementById('prod_image_base64').value = '';
            document.getElementById('input_product_image').value = '';
            setMainImagePreview(imgSrc + '?v=' + Date.now(), 'Aus Galerie als Hauptbild');
        }

        function removeGalleryImage(idx) {
            currentGallery.splice(idx, 1);
            updateGalleryUI();
        }

        function updateGalleryUI() {
            document.getElementById('prod_existing_gallery_json').value = JSON.stringify(currentGallery);
            document.getElementById('prod_new_gallery_base64_json').value = JSON.stringify(newGalleryBase64);
            const container = document.getElementById('gallery_preview_container');
            container.innerHTML = '';

            if (currentGallery.length === 0 && newGalleryBase64.length === 0) {
                container.innerHTML = '<span style="font-size:0.75rem; opacity:0.6; font-weight:700;">Keine zusätzlichen Galerie-Bilder vorhanden.</span>';
                return;
            }

            currentGallery.forEach((img, idx) => {
                const card = document.createElement('div');
                card.className = 'gallery-item-card';
                card.innerHTML = `
                    <img src="${img}" alt="Galerie ${idx+1}" onerror="this.src='images/placeholder.png'; this.onerror=null;">
                    <div class="gallery-item-actions">
                        <button type="button" class="gallery-item-btn" onclick="makeMainImageFromGallery(currentGallery[${idx}])" title="Als Hauptbild setzen">⭐</button>
                        <button type="button" class="gallery-item-btn" onclick="removeGalleryImage(${idx})" title="Entfernen">✕</button>
                    </div>
                `;
                container.appendChild(card);
            });

            newGalleryBase64.forEach((b64, idx) => {
                const card = document.createElement('div');
                card.className = 'gallery-item-card';
                card.style.borderColor = 'var(--accent)';
                card.innerHTML = `
                    <img src="${b64}" alt="Neu ${idx+1}">
                    <div class="gallery-item-actions">
                        <span style="font-size:0.6rem; color:#fff; font-weight:800; padding:1px 3px;">NEU</span>
                        <button type="button" class="gallery-item-btn" onclick="removeNewGalleryImage(${idx})" title="Entfernen">✕</button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function removeNewGalleryImage(idx) {
            newGalleryBase64.splice(idx, 1);
            updateGalleryUI();
        }

        function openProductModal() {
            document.getElementById('productModalTitle').innerText = 'NEUES PRODUKT HINZUFÜGEN';
            document.getElementById('form_action').value = 'save_product';
            document.getElementById('form_original_id').value = '';
            document.getElementById('prod_id').value = '';
            document.getElementById('prod_title').value = '';
            document.getElementById('prod_price').value = '5.00';
            document.getElementById('prod_drop_id').value = '<?= $activeDrop ? htmlspecialchars($activeDrop['id']) : '' ?>';
            document.getElementById('prod_status').value = 'active';
            document.getElementById('prod_stock_initial').value = '5000';
            document.getElementById('prod_stock_pieces').value = '5000';
            document.getElementById('prod_qty_step').value = '50';
            document.getElementById('prod_qty_unit').value = ' Stk.';
            document.getElementById('prod_size').value = '';
            document.getElementById('prod_ref_prefix').value = '';
            document.getElementById('prod_has_sizes').checked = false;
            document.getElementById('prod_sizes_wrap').style.display = 'none';
            document.getElementById('prod_sizes').value = '';
            document.getElementById('prod_description').value = '';
            document.getElementById('prod_existing_image').value = '';
            document.getElementById('prod_image_url').value = '';
            document.getElementById('prod_image_base64').value = '';
            document.getElementById('input_product_image').value = '';
            document.getElementById('input_product_gallery').value = '';

            const mainDrawer = document.getElementById('server_image_drawer_main');
            const galDrawer = document.getElementById('server_image_drawer_gallery');
            if (mainDrawer) mainDrawer.classList.remove('is-open');
            if (galDrawer) galDrawer.classList.remove('is-open');

            setMainImagePreview('', '');

            currentGallery = [];
            newGalleryBase64 = [];
            document.getElementById('prod_new_gallery_base64_json').value = '';
            updateGalleryUI();

            document.getElementById('productModal').classList.add('is-active');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.remove('is-active');
        }

        function editProduct(prodOrId) {
            const prod = (typeof prodOrId === 'string') ? (window.ALL_PRODUCTS[prodOrId] || {}) : (prodOrId || {});
            document.getElementById('productModalTitle').innerText = 'PRODUKT BEARBEITEN: ' + (prod.title || '');
            document.getElementById('form_action').value = 'save_product';
            document.getElementById('form_original_id').value = prod.id || '';
            document.getElementById('prod_id').value = prod.id || '';
            document.getElementById('prod_title').value = prod.title || '';
            document.getElementById('prod_price').value = prod.price || 0;
            document.getElementById('prod_drop_id').value = prod.drop_id || '';
            document.getElementById('prod_status').value = prod.status || 'active';
            document.getElementById('prod_qty_step').value = prod.qty_step || 50;
            document.getElementById('prod_qty_unit').value = prod.qty_unit || ' Stk.';
            document.getElementById('prod_size').value = prod.size || '';
            document.getElementById('prod_ref_prefix').value = prod.ref_prefix || '';
            
            const hasSizes = !!prod.has_sizes;
            document.getElementById('prod_has_sizes').checked = hasSizes;
            document.getElementById('prod_sizes_wrap').style.display = hasSizes ? 'block' : 'none';
            const sizesArr = Array.isArray(prod.sizes) ? prod.sizes : (prod.sizes ? prod.sizes.split(',') : []);
            document.getElementById('prod_sizes').value = sizesArr.join(', ');
            
            document.getElementById('prod_description').value = prod.description || '';

            // Stock Data
            const stocks = window.PRODUCT_STOCKS || {};
            const sInfo = stocks[prod.id] || {};
            const stockVal = (sInfo.stock !== undefined) ? sInfo.stock : ((prod.stock_pieces !== undefined) ? prod.stock_pieces : 5000);
            const initVal = (sInfo.initial !== undefined) ? sInfo.initial : ((prod.stock_initial !== undefined) ? prod.stock_initial : 5000);
            document.getElementById('prod_stock_pieces').value = stockVal;
            document.getElementById('prod_stock_initial').value = initVal;

            // Existing image
            const mainImg = prod.image || 'images/placeholder.png';
            document.getElementById('prod_existing_image').value = mainImg;
            document.getElementById('prod_image_url').value = mainImg;
            document.getElementById('prod_image_base64').value = '';
            document.getElementById('input_product_image').value = '';
            document.getElementById('input_product_gallery').value = '';
            newGalleryBase64 = [];
            document.getElementById('prod_new_gallery_base64_json').value = '';

            const mainDrawer = document.getElementById('server_image_drawer_main');
            const galDrawer = document.getElementById('server_image_drawer_gallery');
            if (mainDrawer) mainDrawer.classList.remove('is-open');
            if (galDrawer) galDrawer.classList.remove('is-open');

            const cacheBust = '?v=' + Date.now();
            setMainImagePreview(mainImg + cacheBust, 'Aktuelles Bild');

            // Gallery Preview
            currentGallery = Array.isArray(prod.gallery) ? [...prod.gallery] : (prod.image ? [prod.image] : []);
            updateGalleryUI();

            document.getElementById('productModal').classList.add('is-active');
        }

        function toggleSizesInput(isChecked) {
            document.getElementById('prod_sizes_wrap').style.display = isChecked ? 'block' : 'none';
        }

        // Live Image Preview & Compression on File Selection
        const fileInput = document.getElementById('input_product_image');
        if (fileInput) {
            fileInput.addEventListener('change', async function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    setMainImagePreview('', 'Wird verarbeitet...');
                    try {
                        const res = await compressImageToDataUrl(file, 1600, 0.88);
                        document.getElementById('prod_image_base64').value = res.dataUrl;
                        document.getElementById('prod_image_url').value = '';
                        setMainImagePreview(res.dataUrl, `✓ Bereit (${res.width}×${res.height})`);
                    } catch (err) {
                        console.warn('Canvas-Komprimierung fehlgeschlagen, nutze Direktleser:', err);
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            setMainImagePreview(e.target.result, '✓ Datei gewählt');
                        };
                        reader.readAsDataURL(file);
                    }
                }
            });
        }

        // Live Gallery Multiple File Selection
        const galFileInput = document.getElementById('input_product_gallery');
        if (galFileInput) {
            galFileInput.addEventListener('change', async function() {
                if (this.files && this.files.length > 0) {
                    for (let i = 0; i < this.files.length; i++) {
                        const f = this.files[i];
                        try {
                            const res = await compressImageToDataUrl(f, 1600, 0.88);
                            newGalleryBase64.push(res.dataUrl);
                        } catch (err) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                newGalleryBase64.push(e.target.result);
                                updateGalleryUI();
                            };
                            reader.readAsDataURL(f);
                            continue;
                        }
                    }
                    updateGalleryUI();
                }
            });
        }

        // Quick Stock Modal
        let quickStockStep = 50;
        function openStockModal(pid, title, currentStock, initialStock, qtyStep) {
            quickStockStep = qtyStep || 50;
            document.getElementById('quick_stock_pid').value = pid;
            document.getElementById('quick_stock_title').innerText = title;
            document.getElementById('quick_stock_id_badge').innerText = 'ID: ' + pid;
            document.getElementById('quick_stock_pieces').value = currentStock;
            document.getElementById('quick_stock_initial').value = initialStock;
            calcQuickPacks();
            document.getElementById('stockModal').classList.add('is-active');
        }

        function closeStockModal() {
            document.getElementById('stockModal').classList.remove('is-active');
        }

        function calcQuickPacks() {
            const pieces = parseInt(document.getElementById('quick_stock_pieces').value, 10) || 0;
            const packs = Math.floor(pieces / quickStockStep);
            document.getElementById('quick_packs_hint').innerText = `= entspricht ${packs} Packungen (bei ${quickStockStep} Stk./Packung)`;
        }

        // Nach Aktionen automatisch zu den Produkten scrollen
        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const msg = params.get('msg');
            if (msg && ['product_saved', 'product_deleted', 'stock_updated', 'stock_reset', 'stock_reset_error'].includes(msg)) {
                const target = document.getElementById('section-products');
                if (target) {
                    setTimeout(() => {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 80);
                }
            }
        });
    </script>
</body>
</html>
