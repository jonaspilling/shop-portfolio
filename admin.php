<?php
session_name('KLEBEKUNST_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

require_once __DIR__ . '/settings_helper.php';
$_adminSettings = get_shop_settings();
$geheim_passwort = $_adminSettings['admin_password'] ?? 'EljoeElmo';
$error_msg = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_access'], $_SESSION['admin_fail_count'], $_SESSION['admin_fail_time']);
    header('Location: admin.php');
    exit;
}

$_maxAttempts = 5;
$_lockoutSeconds = 900;
$_isLockedOut = false;
if (isset($_SESSION['admin_fail_count']) && $_SESSION['admin_fail_count'] >= $_maxAttempts) {
    $elapsed = time() - ($_SESSION['admin_fail_time'] ?? 0);
    if ($elapsed < $_lockoutSeconds) {
        $_isLockedOut = true;
        $error_msg = 'X ZU VIELE VERSUCHE – BITTE ' . ceil(($_lockoutSeconds - $elapsed) / 60) . ' MIN WARTEN X';
    } else {
        unset($_SESSION['admin_fail_count'], $_SESSION['admin_fail_time']);
    }
}

if (!$_isLockedOut && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pwd'])) {
    if ($_POST['pwd'] === $geheim_passwort) {
        $_SESSION['admin_access'] = true;
        unset($_SESSION['admin_fail_count'], $_SESSION['admin_fail_time']);
        header('Location: admin.php');
        exit;
    }
    $_SESSION['admin_fail_count'] = ($_SESSION['admin_fail_count'] ?? 0) + 1;
    $_SESSION['admin_fail_time'] = time();
    $error_msg = 'X FALSCHER CODE X';
}

if ((!isset($_SESSION['admin_access']) || $_SESSION['admin_access'] !== true)
    && (isset($_GET['login']) || $_SERVER['REQUEST_METHOD'] === 'POST')) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>ADMIN LOGIN</title>
        <link rel="icon" type="image/png" href="images/favicon-black.png?v=2" media="(prefers-color-scheme: light)">
        <link rel="icon" type="image/png" href="images/favicon-white.png?v=2" media="(prefers-color-scheme: dark)">
        <style>
            @font-face { font-family: 'Klebekunst Headline'; src: url('fonts/Klebekunst-Regular.ttf') format('truetype'); }
            @font-face { font-family: 'Inter'; src: url('fonts/Inter-Variable.woff2') format('woff2-variations'), url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); }
            :root { --bg-color: #f0f0e8; --black: #000000; --accent: #ff2a00; }
            body { background: var(--bg-color); color: var(--black); font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
            .login-box { border: 4px solid var(--black); padding: 40px; background: #fff; box-shadow: 12px 12px 0 var(--black); text-align: center; width: 100%; max-width: 400px; }
            input[type="password"] { border: 3px solid var(--black); font-size: 2rem; padding: 10px; width: 100%; box-sizing: border-box; text-align: center; margin-bottom: 20px; outline: none; }
            button { background: var(--accent); color: #fff; font-family: 'Klebekunst Headline', sans-serif; font-size: 2rem; padding: 10px 20px; border: 3px solid var(--black); cursor: pointer; box-shadow: 4px 4px 0 var(--black); width: 100%; transition: 0.1s; }
            button:hover { transform: translate(-2px,-2px); box-shadow: 6px 6px 0 var(--black); }
            h1 { font-family: 'Klebekunst Headline', sans-serif; font-size: 3rem; margin-top: 0; }
            @media (max-width: 480px) {
                .login-box { padding: 25px; box-shadow: 8px 8px 0 var(--black); border-width: 3px; }
                h1 { font-size: 2.5rem; }
                input[type="password"] { font-size: 1.5rem; height: 50px; }
                button { font-size: 1.5rem; height: 55px; box-shadow: 3px 3px 0 var(--black); }
            }
            .back-corner-btn { position: fixed; top: 20px; left: 20px; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.6rem; color: var(--black); background: #fff; text-decoration: none; border: 3px solid var(--black); padding: 6px 18px; box-shadow: 5px 5px 0 var(--black); transition: all 0.1s; z-index: 999; }
            .back-corner-btn:hover { background: var(--accent); color: #fff; transform: translate(-2px, -2px); box-shadow: 7px 7px 0 var(--black); }
            .back-corner-btn:active { transform: translate(2px, 2px); box-shadow: none; }
        </style>
    </head>
    <body>
        <a href="index.php" class="back-corner-btn">&larr; ZURÜCK</a>
        <div class="login-box">
            <h1>ADMIN</h1>
            <form method="POST">
                <input type="password" name="pwd" autofocus required>
                <button type="submit">LOGIN</button>
            </form>
            <?php if ($error_msg) echo "<p style='color:var(--accent); font-weight:bold; margin-top:15px;'>$error_msg</p>"; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

ob_start(); // Puffer gegen "headers already sent"-Fehler
require_once __DIR__ . '/settings_helper.php';
if (file_exists(__DIR__ . '/mail_templates.php')) require_once __DIR__ . '/mail_templates.php';
if (file_exists(__DIR__ . '/stock_helper.php'))    require_once __DIR__ . '/stock_helper.php';

// PHPMailer laden (identisch mit process.php)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    $phpmailerDir = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
    if (file_exists($phpmailerDir . 'PHPMailer.php')) {
        require_once $phpmailerDir . 'PHPMailer.php';
        require_once $phpmailerDir . 'SMTP.php';
        require_once $phpmailerDir . 'Exception.php';
    }
}

/**
 * Versendet die Stornierungsbestätigung per E-Mail an den Kunden.
 */
function send_order_cancellation_email(array $order, string $reason, string $customNote, string $refundStatus): bool {
    // Absende-Daten aufbauen
    $nameParts = explode(' ', trim($order['name'] ?? ''), 2);
    $vorname   = $nameParts[0] ?? 'Kunde';

    $itemsHtml = '';
    $itemsText = '';
    if (!empty($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            $pname = htmlspecialchars($item['name'] ?? $item['product_id'] ?? '?');
            $qty   = (int)($item['qty'] ?? 1);
            $price = htmlspecialchars($item['price'] ?? '');
            $itemsHtml .= "<tr><td style='padding:6px 8px; border-bottom:1px dashed #ccc;'>{$pname}</td>"
                        . "<td style='padding:6px 8px; border-bottom:1px dashed #ccc; text-align:center;'>{$qty}x</td>"
                        . "<td style='padding:6px 8px; border-bottom:1px dashed #ccc; text-align:right;'>{$price}</td></tr>";
            $itemsText .= "  - {$qty}x {$pname} ({$price})\n";
        }
    }
    $itemsSectionHtml = '';
    if ($itemsHtml) {
        $itemsSectionHtml = "<table width='100%' border='0' cellpadding='0' cellspacing='0' style='border:3px solid #111; margin-bottom:22px; font-size:13px;'>"
            . "<tr><th colspan='3' style='background:#111; color:#fff; padding:8px 10px; text-align:left; font-size:12px; text-transform:uppercase;'>Stornierte Artikel</th></tr>"
            . $itemsHtml
            . "</table>";
    }

    $mailData = [
        'order_id'     => $order['id'] ?? '???',
        'name'         => $order['name'] ?? '',
        'vorname'      => $vorname,
        'total'        => $order['gesamt'] ?? '0,00 €',
        'date'         => $order['date'] ?? date('d.m.Y'),
        'reason'       => $reason,
        'custom_note'  => $customNote,
        'refund_status'=> $refundStatus,
        'items_section_html' => $itemsSectionHtml,
        'items_text'   => $itemsText,
    ];

    $htmlBody = function_exists('generate_cancellation_email_html') ? generate_cancellation_email_html($mailData) : '';
    $textBody = function_exists('generate_cancellation_email_text') ? generate_cancellation_email_text($mailData) : '';

    if (empty($htmlBody)) {
        // Fallback: einfache Mail
        $htmlBody = "<p>Gude {$vorname},<br>deine Bestellung #{$mailData['order_id']} wurde storniert (Grund: {$reason}).<br>Das Geld wird zeitnah zurücküberwiesen.</p>";
        $textBody = "Gude {$vorname},\ndeine Bestellung #{$mailData['order_id']} wurde storniert (Grund: {$reason}).\nDas Geld wird zeitnah zurücküberwiesen.";
    }

    $toEmail = $order['email'] ?? '';
    if (empty($toEmail)) return false;

    $orderId = $mailData['order_id'];
    $subjectLine = "Stornierung deiner Bestellung #{$orderId} – KLEBEKUNST";

    try {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = 'smtp.gmail.com';
            $mailer->SMTPAuth   = true;
            $mailer->Username   = 'eintrachtfrankfurt.klebekunst@gmail.com';
            $mailer->Password   = 'xldv cfrg lhkv rkay';
            $mailer->SMTPSecure = 'tls';
            $mailer->Port       = 587;
            $mailer->CharSet    = 'UTF-8';
            $mailer->isHTML(true);
            $mailer->setFrom('eintrachtfrankfurt.klebekunst@gmail.com', 'Klebekunst Shop');
            $mailer->addAddress($toEmail, $order['name'] ?? '');
            $mailer->addReplyTo('eintrachtfrankfurt.klebekunst@gmail.com', 'Klebekunst Shop');
            $mailer->Subject = $subjectLine;
            $mailer->Body    = $htmlBody;
            $mailer->AltBody = $textBody;
            $mailer->send();
        } else {
            // mail()-Fallback
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Klebekunst Shop <eintrachtfrankfurt.klebekunst@gmail.com>\r\n";
            mail($toEmail, $subjectLine, $htmlBody, $headers);
        }
        return true;
    } catch (\Throwable $e) {
        $logFolder = __DIR__ . '/logs/';
        if (!is_dir($logFolder)) @mkdir($logFolder, 0775, true);
        @file_put_contents($logFolder . 'mail_errors.log',
            date('Y-m-d H:i:s') . " - Stornierungsmail FEHLER: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
$shopSettings = get_shop_settings();
$activeDrop = get_active_drop();

$permErrors = (!empty($_SESSION['admin_access']) && $_SESSION['admin_access'] === true)
    ? check_system_file_permissions()
    : [];

$orders = get_all_orders();
$ordersFile = get_orders_file_path();

// AJAX & POST Actions
$ajaxAction = $_POST['action'] ?? $_GET['action'] ?? '';
if (!empty($ajaxAction)) {
    if (!isset($_SESSION['admin_access']) || $_SESSION['admin_access'] !== true) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Admin-Anmeldung erforderlich.']);
        exit;
    }

    // Shop-Status explizit setzen oder togglen
    if ($ajaxAction === 'set_shop_status' || $ajaxAction === 'toggle_shop_status') {
        $freshSettings = get_shop_settings();
        if ($ajaxAction === 'set_shop_status' && isset($_POST['shop_open'])) {
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
            if (ob_get_length()) ob_end_clean();
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo json_encode([
                'success'   => $saveSuccess,
                'shop_open' => (bool)$newOpen,
                'error'     => $saveSuccess ? null : 'settings.json konnte nicht gespeichert werden (Dateirechte prüfen).'
            ]);
            exit;
        } else {
            if (ob_get_length()) ob_end_clean();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    if ($ajaxAction === 'update_vip_pwd') {
        $pwd = trim($_POST['vip_password'] ?? '');
        if (!empty($pwd)) {
            $shopSettings['vip_password'] = $pwd;
            save_shop_settings($shopSettings);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'vip_password' => $pwd]);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Passwort darf nicht leer sein']);
        exit;
    }
    if ($ajaxAction === 'update_status') {
        $id = $_POST['id'] ?? '';
        $status = $_POST['status'] ?? '';
        foreach ($orders as &$o) {
            if ($o['id'] === $id) { $o['status'] = $status; break; }
        }
        unset($o);
        $saved = save_all_orders($orders);
        header('Content-Type: application/json');
        echo json_encode(['success' => $saved]);
        exit;
    }
    if ($ajaxAction === 'cancel_order') {
        header('Content-Type: application/json; charset=UTF-8');
        $id           = trim($_POST['id'] ?? '');
        $reason       = trim($_POST['reason'] ?? 'Kein Grund angegeben');
        $customNote   = trim($_POST['custom_note'] ?? '');
        $refundStatus = trim($_POST['refund_status'] ?? 'offen'); // 'offen' oder 'erstattet'
        $restoreStock = !empty($_POST['restore_stock']); // '1' = ja
        $sendEmail    = !empty($_POST['send_email']);    // '1' = ja

        $targetOrder = null;
        foreach ($orders as &$o) {
            if ($o['id'] === $id) {
                $o['status']        = 'storniert';
                $o['refund_status'] = $refundStatus;
                $o['cancel_reason'] = $reason;
                $o['cancel_note']   = $customNote;
                $o['cancelled_at']  = date('Y-m-d H:i:s');
                $targetOrder = $o;
                break;
            }
        }
        unset($o);

        if (!$targetOrder) {
            echo json_encode(['success' => false, 'error' => 'Bestellung nicht gefunden.']);
            exit;
        }

        $saved = save_all_orders($orders);

        // Lagerbestand zurückbuchen?
        if ($restoreStock && function_exists('restore_order_stock')) {
            restore_order_stock($targetOrder);
        }

        // Kunden-Mail senden?
        $mailSent = false;
        if ($sendEmail) {
            $mailSent = send_order_cancellation_email($targetOrder, $reason, $customNote, $refundStatus);
        }

        echo json_encode([
            'success'       => $saved,
            'mail_sent'     => $mailSent,
            'refund_status' => $refundStatus,
            'cancel_reason' => $reason,
        ]);
        exit;
    }
    if ($ajaxAction === 'update_refund_status') {
        header('Content-Type: application/json; charset=UTF-8');
        $id           = trim($_POST['id'] ?? '');
        $refundStatus = trim($_POST['refund_status'] ?? 'offen');
        foreach ($orders as &$o) {
            if ($o['id'] === $id) {
                $o['refund_status'] = $refundStatus;
                break;
            }
        }
        unset($o);
        $saved = save_all_orders($orders);
        echo json_encode(['success' => $saved, 'refund_status' => $refundStatus]);
        exit;
    }
    if ($ajaxAction === 'bulk_status') {
        $ids = isset($_POST['ids']) ? explode(',', $_POST['ids']) : [];
        $status = $_POST['status'] ?? '';
        foreach ($orders as &$o) {
            if (in_array($o['id'], $ids)) { $o['status'] = $status; }
        }
        unset($o);
        $saved = save_all_orders($orders);
        echo json_encode(['success' => $saved]);
        exit;
    }
}

/**
 * Ermittelt den 3-stelligen ISO-Ländercode für die Deutsche Post / DHL
 */
if (!function_exists('get_country_iso3')) {
    function get_country_iso3($country) {
        $raw = (string)$country;
        $c = trim(function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw));
        if ($c === 'deutschland' || $c === 'de' || $c === 'germany') return 'DEU';
        if ($c === 'österreich' || $c === 'oesterreich' || $c === 'at' || $c === 'austria') return 'AUT';
        if ($c === 'schweiz' || $c === 'ch' || $c === 'switzerland') return 'CHE';
        if ($c === 'frankreich' || $c === 'fr' || $c === 'france') return 'FRA';
        if ($c === 'italien' || $c === 'it' || $c === 'italy') return 'ITA';
        if ($c === 'niederlande' || $c === 'nl' || $c === 'netherlands') return 'NLD';
        if ($c === 'belgien' || $c === 'be' || $c === 'belgium') return 'BEL';
        if ($c === 'luxemburg' || $c === 'lu' || $c === 'luxembourg') return 'LUX';
        if ($c === 'spanien' || $c === 'es' || $c === 'spain') return 'ESP';
        if ($c === 'polen' || $c === 'pl' || $c === 'poland') return 'POL';
        if ($c === 'dänemark' || $c === 'daenemark' || $c === 'dk' || $c === 'denmark') return 'DNK';
        if ($c === 'schweden' || $c === 'se' || $c === 'sweden') return 'SWE';
        if ($c === 'tschechien' || $c === 'cz' || $c === 'czech republic') return 'CZE';
        if (strlen($country) === 3) return strtoupper($country);
        return strtoupper(substr($country, 0, 3));
    }
}

/**
 * Ermittelt die Portoklasse / Versandgröße einer Bestellung:
 * - 'grossbrief': Großbrief (1,80 € DE / 3,30 € INT) - 1–4 Packungen
 * - 'maxibrief':  Maxibrief (2,90 € DE / 6,50 € INT) - 5–9 Packungen
 * - 'paeckchen':  2 kg Päckchen (4,19 € DE / 9,90 € INT) - ab 10 Packungen
 */
if (!function_exists('get_order_shipping_tier')) {
    function get_order_shipping_tier($order) {
        // 1. Gespeichertes shipping_tier Label prüfen
        if (!empty($order['shipping_tier'])) {
            $rawSt = (string)$order['shipping_tier'];
            $st = function_exists('mb_strtolower') ? mb_strtolower($rawSt, 'UTF-8') : strtolower($rawSt);
            if (strpos($st, 'großbrief') !== false || strpos($st, 'grossbrief') !== false) {
                return 'grossbrief';
            }
            if (strpos($st, 'maxibrief') !== false) {
                return 'maxibrief';
            }
            if (strpos($st, 'päckchen') !== false || strpos($st, 'paeckchen') !== false) {
                return 'paeckchen';
            }
        }

        // 2. shipping_numeric prüfen
        if (isset($order['shipping_numeric']) && is_numeric($order['shipping_numeric'])) {
            $sn = (float)$order['shipping_numeric'];
            if (abs($sn - 1.80) < 0.05 || abs($sn - 3.30) < 0.05) {
                return 'grossbrief';
            }
            if (abs($sn - 2.90) < 0.05 || abs($sn - 6.50) < 0.05) {
                return 'maxibrief';
            }
            if (abs($sn - 4.19) < 0.05 || abs($sn - 9.90) < 0.05 || abs($sn - 10.49) < 0.05) {
                return 'paeckchen';
            }
        }

        // 3. Aus strukturierten Items (Packungsanzahl) berechnen
        if (!empty($order['items']) && is_array($order['items'])) {
            $packs = 0;
            foreach ($order['items'] as $it) {
                $packs += max(1, (int)($it['qty'] ?? 1));
            }
            if ($packs > 0) {
                if ($packs <= 4) return 'grossbrief';
                if ($packs <= 9) return 'maxibrief';
                return 'paeckchen';
            }
        }

        // 4. Aus Artikeltext parsen
        if (!empty($order['artikel'])) {
            $packs = 0;
            $lines = explode("\n", (string)$order['artikel']);
            foreach ($lines as $line) {
                $l = trim($line);
                if (empty($l) || strpos($l, 'Gutscheincode') !== false) continue;
                if (preg_match('/^-\s*(\d+)\s*Stk\./i', $l, $m)) {
                    $pieces = (int)$m[1];
                    $packs += max(1, (int)round($pieces / 50));
                } elseif (preg_match('/^-\s*(\d+)\s*x/i', $l, $m)) {
                    $packs += (int)$m[1];
                }
            }
            if ($packs > 0) {
                if ($packs <= 4) return 'grossbrief';
                if ($packs <= 9) return 'maxibrief';
                return 'paeckchen';
            }
        }

        return 'grossbrief';
    }
}

/**
 * Liefert Metadaten, Farben und Bezeichnungen zur Portoklasse
 */
if (!function_exists('get_shipping_tier_meta')) {
    function get_shipping_tier_meta($tierKey, $country = 'Deutschland') {
        $rawCountry = (string)$country;
        $c = trim(function_exists('mb_strtolower') ? mb_strtolower($rawCountry, 'UTF-8') : strtolower($rawCountry));
        $isDomestic = ($c === 'deutschland' || $c === 'de' || $c === 'deu');

        switch ($tierKey) {
            case 'maxibrief':
                return [
                    'key'       => 'maxibrief',
                    'name'      => 'Maxibrief',
                    'sub'       => $isDomestic ? '2,90 € (DE)' : '6,50 € (INT)',
                    'badge_bg'  => '#fff3e0',
                    'badge_txt' => '#b45309',
                    'border'    => '#d97706',
                    'icon'      => '',
                    'range'     => '5–9 Packungen'
                ];
            case 'paeckchen':
                return [
                    'key'       => 'paeckchen',
                    'name'      => '2 kg Päckchen',
                    'sub'       => $isDomestic ? '4,19 € (DE)' : '9,90 € (INT)',
                    'badge_bg'  => '#f3e8ff',
                    'badge_txt' => '#6b21a8',
                    'border'    => '#7e22ce',
                    'icon'      => '',
                    'range'     => 'ab 10 Packungen'
                ];
            case 'grossbrief':
            default:
                return [
                    'key'       => 'grossbrief',
                    'name'      => 'Großbrief',
                    'sub'       => $isDomestic ? '1,80 € (DE)' : '3,30 € (INT)',
                    'badge_bg'  => '#ecfdf5',
                    'badge_txt' => '#047857',
                    'border'    => '#059669',
                    'icon'      => '',
                    'range'     => '1–4 Packungen'
                ];
        }
    }
}

// Exports (Unterstützt POST und GET für filter-synchronen Download)
if (isset($_GET['export']) || isset($_POST['export'])) {
    $exportType = $_POST['export'] ?? $_GET['export'];
    $ids = null;
    if (!empty($_POST['ids'])) {
        $ids = array_values(array_filter(array_map('trim', explode(',', (string)$_POST['ids']))));
    } elseif (!empty($_GET['ids'])) {
        $ids = array_values(array_filter(array_map('trim', explode(',', (string)$_GET['ids']))));
    }

    $shippingFilter = trim($_POST['shipping'] ?? ($_GET['shipping'] ?? 'all'));
    $statusFilter = trim($_POST['status'] ?? ($_GET['status'] ?? 'all'));
    $dropFilter = trim($_POST['drop'] ?? ($_GET['drop'] ?? 'all'));
    $countryFilter = trim($_POST['country'] ?? ($_GET['country'] ?? 'all')); // 'all' | 'inland' | 'ausland'

    $exportOrders = $orders;
    if ($ids !== null && !empty($ids)) {
        // Exakte Auswahl aus dem UI (übermittelt nur die aktuell sichtbaren/markierten Zeilen)
        $exportOrders = array_values(array_filter($exportOrders, function($o) use ($ids) {
            return in_array($o['id'], $ids);
        }));
    } else {
        // Serverseitiger Fallback nach aktiven Filtern
        if ($shippingFilter !== 'all' && !empty($shippingFilter)) {
            $exportOrders = array_values(array_filter($exportOrders, function($o) use ($shippingFilter) {
                return get_order_shipping_tier($o) === $shippingFilter;
            }));
        }
        if ($statusFilter !== 'all' && !empty($statusFilter)) {
            $exportOrders = array_values(array_filter($exportOrders, function($o) use ($statusFilter) {
                return strtolower($o['status'] ?? 'offen') === strtolower($statusFilter);
            }));
        }
        if ($dropFilter !== 'all' && !empty($dropFilter)) {
            $exportOrders = array_values(array_filter($exportOrders, function($o) use ($dropFilter) {
                $oDrop = $o['drop_id'] ?? 'unassigned';
                return $oDrop === $dropFilter;
            }));
        }
        if ($countryFilter !== 'all' && !empty($countryFilter)) {
            $exportOrders = array_values(array_filter($exportOrders, function($o) use ($countryFilter) {
                $c = strtolower(trim($o['land'] ?? 'deutschland'));
                $isInland = ($c === 'deutschland' || $c === 'de' || $c === 'deu');
                return $countryFilter === 'inland' ? $isInland : !$isInland;
            }));
        }
    }

    // Stornierte Bestellungen grundsätzlich aus allen Exporten ausschließen
    $exportOrders = array_values(array_filter($exportOrders, function($o) {
        $status = strtolower($o['status'] ?? 'offen');
        return $status !== 'storniert';
    }));


    if ($exportType === 'sendcloud') {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sendcloud_export_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        // Sendcloud typical CSV
        fputcsv($out, ['Name', 'Address', 'House number', 'Zipcode', 'City', 'Country', 'Email', 'Order number'], ';');
        foreach ($exportOrders as $o) {
            fputcsv($out, [
                $o['name'] ?? '',
                $o['strasse'] ?? '',
                $o['hausnummer'] ?? '',
                $o['plz'] ?? '',
                $o['ort'] ?? '',
                $o['land'] ?? '',
                $o['email'] ?? '',
                $o['referenz'] ?? ''
            ], ';');
        }
        fclose($out);
        exit;
    }

    if ($exportType === 'dp') {
        $isAusland = ($countryFilter === 'ausland');
        if ($shippingFilter === 'maxibrief') {
            $filename = $isAusland ? 'maxibrief_int_1.csv' : 'maxibrief_de_1.csv';
        } elseif ($shippingFilter === 'paeckchen') {
            $filename = $isAusland ? 'paeckchen_int_1.csv' : 'paeckchen_de_1.csv';
        } elseif ($shippingFilter === 'grossbrief') {
            $filename = $isAusland ? 'grossbrief_int_1.csv' : 'grossbrief_de_1.csv';
        } else {
            $filename = $isAusland ? 'post_adressen_ausland_' . date('Ymd_His') . '.csv' : 'post_adressen_export_' . date('Ymd_His') . '.csv';
        }

        // Hilfsfunktion: Zeichen entfernen die CSV-Zeilen brechen
        $dpSafe = function($val) {
            return str_replace([';', '"', "\n", "\r"], ['', '', '', ''], (string)$val);
        };
        $dpLine = function($fields) use ($dpSafe) {
            return implode(';', array_map($dpSafe, $fields)) . "\r\n";
        };

        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // Kein BOM – Deutsche Post Import akzeptiert ihn nicht
        echo $dpLine(['NAME','ZUSATZ','STRASSE','NUMMER','PLZ','STADT','LAND','ADRESS_TYP','REFERENZ']);
        // Absender-Zeile (REFERENZ leer lassen)
        echo $dpLine(['Jonas Pilling', '', 'Langwiesenweg', '41', '61267', 'Neu-Anspach', 'DEU', 'HOUSE', '']);
        foreach ($exportOrders as $o) {
            $land = get_country_iso3($o['land'] ?? 'Deutschland');
            echo $dpLine([
                $o['name']       ?? '',
                $o['zusatz']     ?? '',
                $o['strasse']    ?? '',
                $o['hausnummer'] ?? '',
                $o['plz']        ?? '',
                $o['ort']        ?? '',
                $land,
                'HOUSE',
                $o['referenz']   ?? ''
            ]);
        }
        exit;
    }
}

// KPI & Zähler-Berechnung
$countTotal = count($orders);
$countOffen = count(array_filter($orders, fn($o) => ($o['status'] ?? 'offen') === 'offen'));
$countBearbeitung = count(array_filter($orders, fn($o) => ($o['status'] ?? 'offen') === 'in bearbeitung'));
$countAbgeschlossen = count(array_filter($orders, fn($o) => ($o['status'] ?? 'offen') === 'abgeschlossen'));
$countStorniert = count(array_filter($orders, fn($o) => ($o['status'] ?? 'offen') === 'storniert'));
$countRueckzahlungOffen = count(array_filter($orders, fn($o) =>
    ($o['status'] ?? '') === 'storniert' && ($o['refund_status'] ?? 'offen') === 'offen'
));
$countErstattet = count(array_filter($orders, fn($o) =>
    ($o['status'] ?? '') === 'storniert' && ($o['refund_status'] ?? 'offen') === 'erstattet'
));

$countInland = count(array_filter($orders, function($o) {
    $c = strtolower(trim($o['land'] ?? 'deutschland'));
    return ($c === 'deutschland' || $c === 'de' || $c === 'deu');
}));
$countAusland = $countTotal - $countInland;

$countByShipping = [
    'all'        => $countTotal,
    'grossbrief' => 0,
    'maxibrief'  => 0,
    'paeckchen'  => 0,
];
foreach ($orders as $o) {
    $tier = get_order_shipping_tier($o);
    if (isset($countByShipping[$tier])) {
        $countByShipping[$tier]++;
    }
}
$drops = get_all_drops();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLEBEKUNST | ADMIN</title>
    <link rel="icon" type="image/png" href="images/favicon-black.png?v=2" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" href="images/favicon-white.png?v=2" media="(prefers-color-scheme: dark)">
    <style>
        @font-face { font-family: 'Klebekunst Headline'; src: url('fonts/Klebekunst-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Inter'; src: url('fonts/Inter-Variable.woff2') format('woff2-variations'), url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); }
        :root { --bg-color: #f0f0e8; --black: #000000; --accent: #ff2a00; --border-thick: 4px solid var(--black); }
        *, *:before, *:after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg-color); color: var(--black); font-family: 'Inter', sans-serif; min-height: 100vh; padding: 20px 4vw; }
        
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
        .status-toggle-btn.is-open { background: #28a745; color: #fff; }
        .status-toggle-btn.is-closed { background: var(--black); color: #fff; }
        .status-indicator-dot { width: 10px; height: 10px; border-radius: 50%; border: 2px solid #000; display: inline-block; }
        .status-toggle-btn.is-open .status-indicator-dot { background: #00ff66; box-shadow: 0 0 6px #00ff66; }
        .status-toggle-btn.is-closed .status-indicator-dot { background: var(--accent); }

        .active-drop-pill { display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 3px solid var(--black); padding: 8px 14px; color: var(--black); text-decoration: none; box-shadow: 4px 4px 0 var(--black); font-family: 'Inter', sans-serif; font-size: 0.9rem; transition: all 0.1s; }
        .active-drop-pill:hover { transform: translate(-1px, -1px); box-shadow: 5px 5px 0 var(--black); background: #fffbf0; }
        .active-drop-pill .drop-pill-label { font-weight: 900; font-size: 0.75rem; letter-spacing: 0.05em; opacity: 0.7; }
        .active-drop-pill strong { font-family: 'Klebekunst Headline', sans-serif; font-size: 1.3rem; color: var(--accent); }
        .active-drop-pill.is-none { opacity: 0.75; }

        .logout-btn { background: var(--black); color: #fff; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.4rem; padding: 6px 16px; border: 3px solid var(--black); text-decoration: none; cursor: pointer; box-shadow: 4px 4px 0 var(--accent); transition: all 0.1s; }
        .logout-btn:hover { background: var(--accent); color: #fff; transform: translate(-2px,-2px); box-shadow: 6px 6px 0 var(--black); }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .kpi-box { background: #fff; border: var(--border-thick); padding: 20px; box-shadow: 8px 8px 0 var(--black); border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; display: flex; flex-direction: column; }
        .kpi-title { font-weight: 900; text-transform: uppercase; font-size: 1.1rem; opacity: 0.8; margin-bottom: 5px; }
        .kpi-value { font-family: 'Klebekunst Headline', sans-serif; font-size: 4rem; line-height: 0.9; }

        /* Neo-Brutalism Filter Container */
        .neo-filter-container { background: #fff; border: var(--border-thick); padding: 22px; margin-bottom: 30px; box-shadow: 8px 8px 0 var(--black); }
        .filter-header-title { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
        .filter-section-label { font-family: 'Klebekunst Headline', sans-serif; font-size: 1.5rem; letter-spacing: 0.05em; color: var(--black); }
        .filter-hint { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #555; letter-spacing: 0.03em; }

        /* Shipping Portoklassen Filter Bar */
        .shipping-filter-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .shipping-btn { border: 3px solid var(--black); background: #fdfdfa; padding: 12px 14px; text-align: left; cursor: pointer; box-shadow: 4px 4px 0 var(--black); position: relative; display: flex; flex-direction: column; justify-content: space-between; min-height: 88px; transition: transform 0.1s, box-shadow 0.1s, background-color 0.1s; user-select: none; }
        .shipping-btn:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0 var(--black); background: #fff; }
        .shipping-btn:active { transform: translate(2px, 2px); box-shadow: 1px 1px 0 var(--black); }
        .shipping-btn .btn-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
        .shipping-btn .btn-icon { font-size: 1.35rem; line-height: 1; }
        .shipping-btn .count-pill { background: var(--black); color: #fff; font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.85rem; padding: 2px 9px; border: 2px solid var(--black); border-radius: 999px; min-width: 28px; text-align: center; }
        .shipping-btn .btn-title { font-family: 'Klebekunst Headline', sans-serif; font-size: 1.35rem; line-height: 1.1; color: var(--black); }
        .shipping-btn .btn-sub { font-size: 0.75rem; font-weight: 700; color: #444; margin-top: 5px; line-height: 1.25; }

        /* Active Shipping Filter Buttons */
        .shipping-btn.active { background: var(--black); color: #fff; transform: translate(-2px, -2px); box-shadow: 6px 6px 0 var(--accent); }
        .shipping-btn.active .btn-title { color: #fff; }
        .shipping-btn.active .btn-sub { color: #f0f0e8; }
        .shipping-btn.active .count-pill { background: #fff; color: var(--black); border-color: #fff; }

        .shipping-btn.tier-grossbrief.active { background: #047857; color: #fff; border-color: var(--black); box-shadow: 6px 6px 0 var(--black); }
        .shipping-btn.tier-maxibrief.active { background: #b45309; color: #fff; border-color: var(--black); box-shadow: 6px 6px 0 var(--black); }
        .shipping-btn.tier-paeckchen.active { background: #6b21a8; color: #fff; border-color: var(--black); box-shadow: 6px 6px 0 var(--black); }

        /* Region Filter Buttons (Inland / Ausland) */
        .country-btn-group { display: flex; gap: 6px; flex-wrap: wrap; }
        .country-btn { border: 2px solid var(--black); background: #fff; padding: 7px 12px; cursor: pointer; box-shadow: 3px 3px 0 var(--black); font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.82rem; text-transform: uppercase; transition: all 0.1s; user-select: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
        .country-btn:hover { transform: translate(-1px, -1px); box-shadow: 4px 4px 0 var(--black); background: #fcfcf7; }
        .country-btn:active { transform: translate(2px, 2px); box-shadow: none; }
        .country-btn.active { background: var(--black); color: #fff; box-shadow: 3px 3px 0 var(--accent); }
        .country-btn .tab-badge { background: rgba(0,0,0,0.1); padding: 1px 6px; border-radius: 8px; font-size: 0.75rem; font-weight: 900; }
        .country-btn.active .tab-badge { background: rgba(255,255,255,0.25); color: #fff; }
        .country-btn--inland.active { background: #059669; color: #fff; border-color: var(--black); box-shadow: 3px 3px 0 var(--black); }
        .country-btn--ausland.active { background: #2563eb; color: #fff; border-color: var(--black); box-shadow: 3px 3px 0 var(--black); }

        /* Active Filter Chips Bar (Kombinierte Filter-Anzeige) */
        .active-filter-chips-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding: 10px 14px; background: #fffbf0; border: 2px dashed var(--black); font-size: 0.82rem; font-weight: 800; }
        .active-filter-chips-bar .chips-label { font-family: 'Inter', sans-serif; font-weight: 900; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #555; }
        .filter-chip { display: inline-flex; align-items: center; gap: 6px; background: var(--black); color: #fff; padding: 4px 10px; border: 2px solid var(--black); font-size: 0.78rem; font-weight: 900; text-transform: uppercase; box-shadow: 2px 2px 0 var(--black); }
        .filter-chip-remove { background: none; border: none; color: #ff6b6b; font-size: 1.1rem; line-height: 1; font-weight: 900; cursor: pointer; padding: 0 0 0 4px; display: inline-flex; align-items: center; }
        .filter-chip-remove:hover { color: #fff; }
        .filter-chip.chip-shipping { background: #047857; }
        .filter-chip.chip-status { background: #b45309; }
        .filter-chip.chip-country { background: #2563eb; }
        .filter-chip.chip-drop { background: #6b21a8; }
        .filter-chip.chip-search { background: #374151; }

        /* Secondary Filter Bar (Status, Drop, Suche) */
        .filter-secondary-bar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px; padding-top: 16px; border-top: 2px dashed #000; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-label { font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8; }

        .status-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
        .status-tab { background: #fff; border: 2px solid var(--black); font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.82rem; padding: 7px 12px; cursor: pointer; text-transform: uppercase; box-shadow: 3px 3px 0 var(--black); transition: all 0.1s; display: inline-flex; align-items: center; gap: 5px; }
        .status-tab:hover { transform: translate(-1px, -1px); box-shadow: 4px 4px 0 var(--black); background: #fcfcf7; }
        .status-tab.active { background: var(--black); color: #fff; box-shadow: 3px 3px 0 var(--accent); }
        .status-tab .tab-badge { background: rgba(0,0,0,0.1); padding: 1px 6px; border-radius: 8px; font-size: 0.75rem; font-weight: 900; }
        .status-tab.active .tab-badge { background: rgba(255,255,255,0.25); color: #fff; }

        .neo-select { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 0.85rem; border: 3px solid var(--black); padding: 7px 12px; background: #fff; box-shadow: 3px 3px 0 var(--black); cursor: pointer; outline: none; min-width: 170px; height: 38px; }
        .neo-select:focus { background: #fffbf0; }

        .search-input-wrapper { position: relative; display: inline-flex; align-items: center; }
        .neo-search-input { font-family: 'Inter', sans-serif; font-weight: 700; font-size: 0.85rem; border: 3px solid var(--black); padding: 7px 32px 7px 12px; background: #fff; box-shadow: 3px 3px 0 var(--black); outline: none; width: 230px; height: 38px; }
        .neo-search-input:focus { background: #fffbf0; }
        .clear-search-btn { position: absolute; right: 8px; background: none; border: none; font-size: 1.3rem; line-height: 1; font-weight: 900; cursor: pointer; color: #888; padding: 0 4px; }
        .clear-search-btn:hover { color: var(--accent); }

        .reset-filter-btn { background: #fff; border: 2px solid var(--black); font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.8rem; padding: 8px 14px; cursor: pointer; box-shadow: 3px 3px 0 var(--black); margin-left: auto; transition: all 0.1s; display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; height: 38px; }
        .reset-filter-btn:hover { background: #ffebee; transform: translate(-1px, -1px); box-shadow: 4px 4px 0 var(--accent); }
        .reset-filter-btn:active { transform: translate(2px, 2px); box-shadow: none; }

        /* Export & Action Bar */
        .export-actions-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-top: 18px; padding-top: 16px; border-top: 3px solid var(--black); }
        .export-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 0.85rem; }
        .active-count-label { font-weight: 800; letter-spacing: 0.03em; }
        .active-count-label strong { font-family: 'Klebekunst Headline', sans-serif; font-size: 1.3rem; color: var(--black); }
        .selection-count-label { font-weight: 900; color: var(--accent); }
        .selection-helpers { display: inline-flex; align-items: center; gap: 8px; }
        .selection-helpers .link-btn { background: none; border: none; padding: 0; font-family: 'Inter', sans-serif; font-size: 0.8rem; font-weight: 800; color: #444; text-decoration: underline; cursor: pointer; }
        .selection-helpers .link-btn:hover { color: var(--black); }
        .selection-helpers .sep { color: #bbb; font-size: 0.8rem; }

        .export-btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-btn { font-family: 'Inter', sans-serif; font-weight: 900; text-transform: uppercase; font-size: 0.85rem; border: 3px solid var(--black); padding: 9px 16px; cursor: pointer; box-shadow: 4px 4px 0 var(--black); transition: all 0.1s; display: inline-flex; align-items: center; gap: 6px; }
        .action-btn:hover { transform: translate(-1px, -1px); box-shadow: 5px 5px 0 var(--black); }
        .action-btn:active { transform: translate(2px, 2px); box-shadow: none; }
        .action-bulk { background: #fff; color: var(--black); }
        .action-export-dp { background: #000; color: #fff; box-shadow: 4px 4px 0 #ffd700; }
        .action-export-dp:hover { box-shadow: 6px 6px 0 #ffd700; }
        .action-export-sc { background: var(--accent); color: #fff; }

        /* Portoklassen Badges in Tabelle */
        .badge-tier { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; font-size: 0.8rem; font-weight: 900; text-transform: uppercase; border: 2px solid var(--black); box-shadow: 2px 2px 0 var(--black); white-space: nowrap; }
        .badge-tier.tier-grossbrief { background: #ecfdf5; color: #065f46; }
        .badge-tier.tier-maxibrief { background: #fff3e0; color: #92400e; }
        .badge-tier.tier-paeckchen { background: #f3e8ff; color: #5b21b6; }

        .table-container { background: #fff; border: var(--border-thick); box-shadow: 12px 12px 0 var(--black); overflow-x: auto; margin-bottom: 50px; }
        table { width: 100%; border-collapse: collapse; min-width: 980px; }
        th, td { border: 2px solid var(--black); padding: 12px 14px; text-align: left; }
        th { background: var(--black); color: #fff; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.25rem; text-transform: uppercase; white-space: nowrap; letter-spacing: 0.03em; }
        td { font-size: 0.95rem; font-weight: 500; }
        tr:hover { background: rgba(0,0,0,0.02); }
        
        input[type="checkbox"] { width: 20px; height: 20px; border: 2px solid var(--black); accent-color: var(--black); cursor: pointer; }
        
        select.status-select { font-family: 'Inter', sans-serif; font-weight: 900; border: 2px solid var(--black); padding: 6px 8px; outline: none; background: var(--bg-color); cursor: pointer; }
        
        .badge { display: inline-block; padding: 3px 8px; font-size: 0.78rem; font-weight: 900; text-transform: uppercase; border: 1px solid var(--black); background: #eee; margin-top: 5px; }
        .badge.offen { background: #ffd700; }
        .badge.in-bearbeitung { background: #87ceeb; }
        .badge.abgeschlossen { background: #90ee90; }
        .badge.storniert { background: #ffb6c1; }
        .badge.refund-offen { background: #ff8c00; color: #fff; border-color: #cc6600; }
        .badge.refund-erstattet { background: #22c55e; color: #fff; border-color: #16a34a; }
        .cancel-reason-hint { font-size: 0.72rem; color: #555; font-style: italic; margin-top: 4px; max-width: 180px; word-break: break-word; }
        .quick-refund-btn { display: inline-block; margin-top: 6px; padding: 4px 10px; font-size: 0.75rem; font-weight: 900; font-family: 'Inter', sans-serif; text-transform: uppercase; background: #22c55e; color: #fff; border: 2px solid #000; cursor: pointer; box-shadow: 2px 2px 0 #000; transition: box-shadow .1s, transform .1s; }
        .quick-refund-btn:hover { box-shadow: 1px 1px 0 #000; transform: translate(1px,1px); }
        /* Cancel Modal */
        .neo-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9000; align-items: center; justify-content: center; }
        .neo-modal-backdrop.open { display: flex; }
        .neo-modal-card { background: #f0f0e8; border: 4px solid #000; box-shadow: 10px 10px 0 #000; padding: 32px 30px; max-width: 520px; width: 95vw; max-height: 90vh; overflow-y: auto; position: relative; }
        .neo-modal-card h2 { font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; text-transform: uppercase; margin-bottom: 6px; }
        .neo-modal-subtitle { font-size: 0.88rem; color: #555; margin-bottom: 22px; border-bottom: 2px solid #000; padding-bottom: 12px; }
        .neo-modal-label { display: block; font-size: 0.82rem; font-weight: 900; text-transform: uppercase; margin-bottom: 5px; margin-top: 16px; }
        .neo-modal-select, .neo-modal-textarea { width: 100%; font-family: 'Inter', sans-serif; font-size: 0.9rem; border: 2px solid #000; background: #fff; padding: 8px 10px; outline: none; }
        .neo-modal-textarea { resize: vertical; min-height: 70px; }
        .neo-modal-radio-group { display: flex; gap: 12px; margin-top: 6px; flex-wrap: wrap; }
        .neo-modal-radio-group label { display: flex; align-items: center; gap: 6px; font-size: 0.88rem; font-weight: 700; cursor: pointer; }
        .neo-modal-check-row { display: flex; align-items: center; gap: 8px; margin-top: 14px; font-size: 0.88rem; font-weight: 700; cursor: pointer; }
        .neo-modal-actions { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
        .neo-modal-btn-primary { flex: 1; padding: 12px 16px; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.1rem; text-transform: uppercase; background: #ff2a00; color: #fff; border: 3px solid #000; box-shadow: 4px 4px 0 #000; cursor: pointer; transition: box-shadow .1s, transform .1s; }
        .neo-modal-btn-primary:hover { box-shadow: 2px 2px 0 #000; transform: translate(2px,2px); }
        .neo-modal-btn-secondary { padding: 12px 16px; font-family: 'Inter', sans-serif; font-size: 0.9rem; font-weight: 900; text-transform: uppercase; background: #f0f0e8; border: 3px solid #000; box-shadow: 4px 4px 0 #000; cursor: pointer; transition: box-shadow .1s, transform .1s; }
        .neo-modal-btn-secondary:hover { box-shadow: 2px 2px 0 #000; transform: translate(2px,2px); }
        .neo-modal-close { position: absolute; top: 12px; right: 12px; background: none; border: 2px solid #000; width: 32px; height: 32px; font-size: 1.1rem; font-weight: 900; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .neo-modal-info-box { background: #fff; border: 2px solid #000; padding: 10px 14px; font-size: 0.82rem; color: #333; margin-bottom: 14px; }
        
        .artikel-list { font-size: 0.85rem; line-height: 1.35; white-space: pre-wrap; margin-bottom: 4px; }
        .empty-table-msg { text-align: center; padding: 45px 20px; font-size: 1.15rem; font-weight: 800; color: #555; background: #fafaf5; }

        @media (max-width: 900px) {
            .shipping-filter-bar { grid-template-columns: repeat(2, 1fr); }
            .reset-filter-btn { margin-left: 0; width: 100%; justify-content: center; }
            .export-actions-bar { flex-direction: column; align-items: stretch; }
            .export-btn-group { flex-direction: column; }
            .action-btn { width: 100%; justify-content: center; }
        }

        @media (max-width: 768px) {
            body { padding: 15px 4vw; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; margin-bottom: 20px; }
            .header h1 { font-size: 2.2rem; }
            .logout-btn { width: 100%; text-align: center; box-shadow: 3px 3px 0 var(--accent); }
            
            .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 25px; }
            .kpi-box { padding: 15px; border-radius: 2px 15px 3px 15px / 15px 5px 15px 3px; }
            .kpi-title { font-size: 0.85rem; }
            .kpi-value { font-size: 2.2rem; }
            
            .shipping-filter-bar { grid-template-columns: 1fr; }
            .filter-secondary-bar { flex-direction: column; align-items: stretch; }
            .status-tabs { justify-content: flex-start; }
            .status-tab { flex: 1 1 45%; justify-content: center; }
            .neo-search-input { width: 100%; }
            .neo-select { width: 100%; }

            .table-container { box-shadow: 6px 6px 0 var(--black); border-width: 3px; }
            th { font-size: 1.1rem; padding: 10px; }
            td { padding: 10px; font-size: 0.9rem; }
            
            select.status-select { font-size: 0.9rem; padding: 8px; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-left">
            <h1>ADMIN DASHBOARD</h1>
            <div class="admin-nav-tabs">
                <a href="admin.php" class="admin-nav-tab active">BESTELLUNGEN (<?= $countTotal ?>)</a>
                <a href="admin_analytics.php" class="admin-nav-tab">DROPS & ANALYTICS</a>
            </div>
            <?php if (!empty($_SESSION['admin_access'])): ?>
                <a href="?logout=1" class="logout-btn">LOGOUT</a>
            <?php else: ?>
                <a href="admin.php?login=1" class="logout-btn">ADMIN-LOGIN</a>
            <?php endif; ?>
        </div>
        <div class="header-right">
            <div class="shop-toggle-card">
                <span class="toggle-card-label">SHOP-STATUS:</span>
                <form method="POST" action="admin.php" id="shopStatusForm" style="margin:0; display:inline-flex;">
                    <input type="hidden" name="action" value="set_shop_status">
                    <input type="hidden" name="shop_open" id="shopStatusInput" value="<?= !empty($shopSettings['shop_open']) ? '0' : '1' ?>">
                    <button type="submit" id="shopStatusToggleBtn" class="status-toggle-btn <?= !empty($shopSettings['shop_open']) ? 'is-open' : 'is-closed' ?>">
                        <span class="status-indicator-dot"></span>
                        <span id="shopStatusText"><?= !empty($shopSettings['shop_open']) ? 'SHOP GEÖFFNET' : 'SHOP GESCHLOSSEN' ?></span>
                    </button>
                </form>
            </div>
            <?php if ($activeDrop): ?>
                <a href="admin_analytics.php?drop=<?= urlencode($activeDrop['id']) ?>" class="active-drop-pill" title="Aktiver Drop - Zur Analyse">
                    <span class="drop-pill-label">AKTIVER DROP:</span>
                    <strong><?= htmlspecialchars($activeDrop['name']) ?></strong>
                </a>
            <?php else: ?>
                <a href="admin_analytics.php" class="active-drop-pill is-none" title="Kein aktiver Drop - Zu den Drops">
                    <span class="drop-pill-label">DROP:</span>
                    <span>KEIN AKTIVER DROP</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($permErrors)): ?>
        <div style="background:#fff; border:3px solid var(--black); border-left:10px solid var(--accent); padding:12px 20px; margin-bottom:25px; box-shadow:6px 6px 0 var(--black); font-weight:800;">
            Achtung Dateiberechtigungen: <?= htmlspecialchars(implode(" | ", $permErrors)) ?>
        </div>
    <?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi-box"><span class="kpi-title">Gesamt</span><span class="kpi-value"><?= $countTotal ?></span></div>
        <div class="kpi-box" style="border-color: #d4af37;"><span class="kpi-title">Offen</span><span class="kpi-value"><?= $countOffen ?></span></div>
        <div class="kpi-box" style="border-color: #1e90ff;"><span class="kpi-title">In Bearbeitung</span><span class="kpi-value"><?= $countBearbeitung ?></span></div>
        <div class="kpi-box" style="border-color: #32cd32;"><span class="kpi-title">Abgeschlossen</span><span class="kpi-value"><?= $countAbgeschlossen ?></span></div>
    </div>

    <!-- NEO-BRUTALISMUS FILTER LEISTE & DYNAMISCHE EXPORT-STEUERUNG -->
    <div class="neo-filter-container">
        <!-- 1. Versandgrößen / Portoklassen Filter Buttons -->
        <div class="filter-header-title">
            <span class="filter-section-label">PORTOKLASSE &amp; VERSANDGRÖSSE</span>
            <span class="filter-hint">Filtere Bestellungen für Deutsche Post &amp; DHL Portoklassen</span>
        </div>
        <div class="shipping-filter-bar">
            <button type="button" class="shipping-btn active" data-shipping="all">
                <div class="btn-top">
                    <span class="count-pill" id="pill-shipping-all"><?= $countByShipping['all'] ?></span>
                </div>
                <div class="btn-title">ALLE PORTOKLASSEN</div>
                <div class="btn-sub">Gesamte Portoklassen anzeigen</div>
            </button>
            <button type="button" class="shipping-btn tier-grossbrief" data-shipping="grossbrief">
                <div class="btn-top">
                    <span class="count-pill" id="pill-shipping-grossbrief"><?= $countByShipping['grossbrief'] ?></span>
                </div>
                <div class="btn-title">GROSSBRIEF</div>
                <div class="btn-sub">1,80 € DE / 3,30 € INT &bull; 1–4 Packs</div>
            </button>
            <button type="button" class="shipping-btn tier-maxibrief" data-shipping="maxibrief">
                <div class="btn-top">
                    <span class="count-pill" id="pill-shipping-maxibrief"><?= $countByShipping['maxibrief'] ?></span>
                </div>
                <div class="btn-title">MAXIBRIEF</div>
                <div class="btn-sub">2,90 € DE / 6,50 € INT &bull; 5–9 Packs</div>
            </button>
            <button type="button" class="shipping-btn tier-paeckchen" data-shipping="paeckchen">
                <div class="btn-top">
                    <span class="count-pill" id="pill-shipping-paeckchen"><?= $countByShipping['paeckchen'] ?></span>
                </div>
                <div class="btn-title">2 KG PÄCKCHEN</div>
                <div class="btn-sub">4,19 € DE / 9,90 € INT &bull; ab 10 Packs</div>
            </button>
        </div>

        <!-- 2. Sekundäre Filter: Status, Drop, Volltextsuche & Reset -->
        <div class="filter-secondary-bar">
            <!-- Status Tabs -->
            <div class="filter-group">
                <span class="filter-label">Status-Filter:</span>
                <div class="status-tabs" id="statusTabs">
                    <button type="button" class="status-tab active" data-status="all">Alle <span class="tab-badge"><?= $countTotal ?></span></button>
                    <button type="button" class="status-tab" data-status="offen">Offen <span class="tab-badge"><?= $countOffen ?></span></button>
                    <button type="button" class="status-tab" data-status="in bearbeitung">In Bearbeitung <span class="tab-badge"><?= $countBearbeitung ?></span></button>
                    <button type="button" class="status-tab" data-status="abgeschlossen">Abgeschlossen <span class="tab-badge"><?= $countAbgeschlossen ?></span></button>
                    <button type="button" class="status-tab" data-status="storniert">Storniert <span class="tab-badge"><?= $countStorniert ?></span></button>
                    <button type="button" class="status-tab tab-refund-offen" data-status="rueckzahlung-offen" title="Storniert & Rückzahlung noch ausstehend">Rückzahlung offen <span class="tab-badge" style="background:#cc5500;color:#fff;"><?= $countRueckzahlungOffen ?></span></button>
                    <button type="button" class="status-tab tab-erstattet" data-status="erstattet" title="Storniert & Betrag bereits zurücküberwiesen">Erstattet <span class="tab-badge" style="background:#166534;color:#fff;"><?= $countErstattet ?></span></button>
                </div>
            </div>

            <!-- Drop Filter -->
            <?php if (!empty($drops)): ?>
            <div class="filter-group">
                <label for="dropSelect" class="filter-label">Drop:</label>
                <select id="dropSelect" class="neo-select">
                    <option value="all">Alle Drops</option>
                    <?php foreach ($drops as $d): ?>
                        <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="unassigned">Ohne Drop-Zuweisung</option>
                </select>
            </div>
            <?php endif; ?>

            <!-- Ausland / Inland Filter -->
            <div class="filter-group">
                <label class="filter-label">Region:</label>
                <div class="country-btn-group">
                    <button type="button" class="country-btn active" data-country="all">Alle <span class="tab-badge" id="badge-country-all"><?= $countTotal ?></span></button>
                    <button type="button" class="country-btn country-btn--inland" data-country="inland">Inland <span class="tab-badge" id="badge-country-inland"><?= $countInland ?></span></button>
                    <button type="button" class="country-btn country-btn--ausland" data-country="ausland">Ausland <span class="tab-badge" id="badge-country-ausland"><?= $countAusland ?></span></button>
                </div>
            </div>

            <!-- Schnellsuche -->
            <div class="filter-group">
                <label for="searchQuery" class="filter-label">Schnellsuche:</label>
                <div class="search-input-wrapper">
                    <input type="text" id="searchQuery" class="neo-search-input" placeholder="Name, E-Mail, Ref, PLZ, ID...">
                    <button type="button" id="clearSearchBtn" class="clear-search-btn" title="Suche leeren" style="display:none;">&times;</button>
                </div>
            </div>

            <!-- Reset Button -->
            <button type="button" id="resetFiltersBtn" class="reset-filter-btn" title="Alle Filter zurücksetzen">
                &#x21bb; FILTER ZURÜCKSETZEN
            </button>
        </div>

        <!-- Dynamische Leiste für aktive Filter-Kombination -->
        <div id="activeFilterChipsBar" class="active-filter-chips-bar" style="display:none;">
            <span class="chips-label">Kombinierte Filter:</span>
            <div id="chipsContainer" style="display:inline-flex;gap:6px;flex-wrap:wrap;"></div>
            <button type="button" class="link-btn" id="chipsResetBtn" style="margin-left:auto;font-weight:900;text-transform:uppercase;font-size:0.75rem;cursor:pointer;color:var(--accent);">Filter zurücksetzen &times;</button>
        </div>

        <!-- 3. Export & Aktionen Bar -->
        <div class="export-actions-bar">
            <div class="export-info">
                <span class="active-count-label">
                    ANGEZEIGT: <strong id="visible-count"><?= $countTotal ?></strong> VON <strong><?= $countTotal ?></strong>
                </span>
                <span class="selection-count-label" id="selection-count-wrapper" style="display:none;">
                    &bull; <strong id="selected-count">0</strong> MARKIERTE BESTELLUNGEN
                </span>
                <div class="selection-helpers">
                    <button type="button" class="link-btn" id="selectAllVisibleBtn">Alle sichtbaren auswählen</button>
                    <span class="sep">|</span>
                    <button type="button" class="link-btn" id="clearSelectionBtn">Auswahl aufheben</button>
                </div>
            </div>

            <div class="export-btn-group">
                <button type="button" class="action-btn action-bulk" onclick="bulkComplete()">
                    &#10004; Markierte Abgeschlossen
                </button>
                <button type="button" class="action-btn action-export-dp" onclick="exportCSV('dp')" title="Exportiert exakt die aktuell gefilterten oder markierten Adressen für die Deutsche Post / DHL">
                    POST / DHL CSV EXPORT (<span id="export-count-dp"><?= $countTotal ?></span>)
                </button>
                <button type="button" class="action-btn action-export-sc" onclick="exportCSV('sendcloud')" title="Sendcloud CSV Export der gefilterten Bestellungen">
                    SENDCLOUD CSV (<span id="export-count-sc"><?= $countTotal ?></span>)
                </button>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align:center;"><input type="checkbox" id="master-check"></th>
                    <th>ID / Paypal</th>
                    <th>Datum</th>
                    <th>Kunde</th>
                    <th>Adresse</th>
                    <th>Portoklasse</th>
                    <th>Artikel &amp; Ref</th>
                    <th>Gesamt</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="order-tbody">
                <?php if (empty($orders)): ?>
                    <tr id="empty-initial-row">
                        <td colspan="9" class="empty-table-msg">Noch keine Bestellungen im System vorhanden.</td>
                    </tr>
                <?php endif; ?>
                <tr id="no-orders-row" style="display:none;">
                    <td colspan="9" class="empty-table-msg">Keine Bestellungen für diese Filterkombination gefunden.</td>
                </tr>
                <?php foreach (array_reverse($orders) as $o): 
                    $status = strtolower($o['status'] ?? 'offen');
                    $shippingTier = get_order_shipping_tier($o);
                    $shippingMeta = get_shipping_tier_meta($shippingTier, $o['land'] ?? 'Deutschland');
                    $orderDropId = resolve_order_drop_id($o, $drops);
                    $orderDropName = 'Ohne Drop';
                    foreach ($drops as $d) {
                        if ($d['id'] === $orderDropId) {
                            $orderDropName = $d['name'];
                            break;
                        }
                    }
                    $searchBlob = strtolower(implode(' ', [
                        $o['id'] ?? '',
                        $o['paypal_id'] ?? '',
                        $o['name'] ?? '',
                        $o['email'] ?? '',
                        $o['strasse'] ?? '',
                        $o['hausnummer'] ?? '',
                        $o['plz'] ?? '',
                        $o['ort'] ?? '',
                        $o['land'] ?? '',
                        $o['referenz'] ?? '',
                        $o['artikel'] ?? '',
                        $shippingMeta['name'] ?? '',
                        $orderDropName ?? ''
                    ]));
                ?>
                <?php
                    $refundStatus  = $o['refund_status'] ?? 'offen';
                    $cancelReason  = $o['cancel_reason'] ?? '';
                    // Virtueller Filter-Status für Tabs
                    $filterStatus  = $status;
                    if ($status === 'storniert') {
                        $filterStatus = ($refundStatus === 'erstattet') ? 'erstattet' : 'rueckzahlung-offen';
                    }
                ?>
                <?php
                    $rowLand = strtolower(trim($o['land'] ?? 'deutschland'));
                    $rowCountry = ($rowLand === 'deutschland' || $rowLand === 'de' || $rowLand === 'deu') ? 'inland' : 'ausland';
                ?>
                <tr class="order-data-row"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-status="<?= htmlspecialchars($filterStatus) ?>"
                    data-real-status="<?= htmlspecialchars($status) ?>"
                    data-refund="<?= htmlspecialchars($refundStatus) ?>"
                    data-name="<?= htmlspecialchars($o['name'] ?? '') ?>"
                    data-email="<?= htmlspecialchars($o['email'] ?? '') ?>"
                    data-total="<?= htmlspecialchars($o['gesamt'] ?? '') ?>"
                    data-shipping="<?= htmlspecialchars($shippingTier) ?>"
                    data-drop="<?= htmlspecialchars($orderDropId) ?>"
                    data-country="<?= $rowCountry ?>"
                    data-search="<?= htmlspecialchars($searchBlob) ?>">
                    <td style="text-align:center;"><input type="checkbox" class="row-check" value="<?= htmlspecialchars($o['id']) ?>"></td>
                    <td><strong style="font-size:0.8rem;"><?= htmlspecialchars($o['id']) ?></strong><br><span style="font-size:0.7rem; opacity:0.6;"><?= htmlspecialchars($o['paypal_id'] ?? '') ?></span></td>
                    <td><?= htmlspecialchars($o['date']) ?></td>
                    <td><strong><?= htmlspecialchars($o['name']) ?></strong><br><a href="mailto:<?= htmlspecialchars($o['email']) ?>" style="color:var(--accent); font-size:0.8rem;"><?= htmlspecialchars($o['email']) ?></a></td>
                    <td><?= htmlspecialchars($o['strasse'] . ' ' . $o['hausnummer']) ?><br><?= htmlspecialchars($o['plz'] . ' ' . $o['ort']) ?><br><?= htmlspecialchars($o['land']) ?></td>
                    <td>
                        <span class="badge-tier tier-<?= htmlspecialchars($shippingTier) ?>" title="<?= htmlspecialchars($shippingMeta['range']) ?>">
                            <?= $shippingMeta['icon'] ?> <?= htmlspecialchars($shippingMeta['name']) ?>
                        </span>
                        <div style="font-size:0.75rem; font-weight:700; color:#444; margin-top:4px;">
                            <?= htmlspecialchars($shippingMeta['sub']) ?>
                        </div>
                        <?php if ($orderDropId !== 'unassigned'): ?>
                            <div style="font-size:0.7rem; font-weight:800; background:#f4f4f4; border:1px solid #000; padding:1px 5px; display:inline-block; margin-top:4px;">
                                <?= htmlspecialchars($orderDropName) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><div class="artikel-list"><?= htmlspecialchars($o['artikel']) ?></div><span style="font-family:monospace; font-size:0.7rem; background:#eee; padding:2px 4px; border:1px solid #000;"><?= htmlspecialchars($o['referenz'] ?? '') ?></span></td>
                    <td><strong><?= htmlspecialchars($o['gesamt']) ?></strong></td>
                    <td>
                        <select class="status-select"
                            data-id="<?= htmlspecialchars($o['id']) ?>"
                            data-name="<?= htmlspecialchars($o['name'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($o['email'] ?? '') ?>"
                            data-total="<?= htmlspecialchars($o['gesamt'] ?? '') ?>">
                            <option value="offen" <?= $status === 'offen' ? 'selected' : '' ?>>Offen</option>
                            <option value="in bearbeitung" <?= $status === 'in bearbeitung' ? 'selected' : '' ?>>In Bearbeitung</option>
                            <option value="abgeschlossen" <?= $status === 'abgeschlossen' ? 'selected' : '' ?>>Abgeschlossen</option>
                            <option value="storniert" <?= $status === 'storniert' ? 'selected' : '' ?>>Storniert</option>
                        </select>
                        <br>
                        <span class="badge <?= str_replace(' ', '-', $status) ?>" id="badge-<?= htmlspecialchars($o['id']) ?>"><?= htmlspecialchars(strtoupper($status)) ?></span>
                        <?php if ($status === 'storniert'): ?>
                            <?php if ($refundStatus === 'erstattet'): ?>
                                <span class="badge refund-erstattet">ERSTATTET</span>
                            <?php else: ?>
                                <span class="badge refund-offen" id="rbadge-<?= htmlspecialchars($o['id']) ?>">RÜCKZAHLUNG OFFEN</span>
                                <button type="button" class="quick-refund-btn"
                                    onclick="quickMarkRefunded('<?= htmlspecialchars($o['id']) ?>', this)">
                                    Als erstattet markieren
                                </button>
                            <?php endif; ?>
                            <?php if (!empty($cancelReason)): ?>
                                <div class="cancel-reason-hint">Grund: <?= htmlspecialchars($cancelReason) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Filter-Zustand (Multi-Kombination aus Portoklasse, Status, Drop & Textsuche)
        const filterState = {
            shipping: 'all',
            status: 'all',
            drop: 'all',
            country: 'all',
            search: ''
        };
        const countryBtns = document.querySelectorAll('.country-btn');

        const shippingBtns = document.querySelectorAll('.shipping-btn');
        const statusTabs = document.querySelectorAll('.status-tab');
        const dropSelect = document.getElementById('dropSelect');
        const searchInput = document.getElementById('searchQuery');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const resetBtn = document.getElementById('resetFiltersBtn');
        const masterCheck = document.getElementById('master-check');
        const selectAllVisibleBtn = document.getElementById('selectAllVisibleBtn');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const visibleCountElem = document.getElementById('visible-count');
        const exportCountDpElem = document.getElementById('export-count-dp');
        const exportCountScElem = document.getElementById('export-count-sc');
        const selectionCountWrapper = document.getElementById('selection-count-wrapper');
        const selectedCountElem = document.getElementById('selected-count');
        const noOrdersRow = document.getElementById('no-orders-row');
        const orderRows = document.querySelectorAll('#order-tbody tr.order-data-row');

        function updateSelectionDisplay() {
            const checked = document.querySelectorAll('.row-check:checked');
            const checkedVisible = Array.from(checked).filter(cb => {
                const tr = cb.closest('tr');
                return tr && tr.style.display !== 'none';
            });
            const count = checkedVisible.length;

            if (selectedCountElem) selectedCountElem.innerText = count;
            if (selectionCountWrapper) {
                selectionCountWrapper.style.display = count > 0 ? 'inline' : 'none';
            }

            if (masterCheck) {
                const visibleRows = Array.from(orderRows).filter(tr => tr.style.display !== 'none');
                if (visibleRows.length === 0) {
                    masterCheck.checked = false;
                    masterCheck.indeterminate = false;
                } else if (count === visibleRows.length) {
                    masterCheck.checked = true;
                    masterCheck.indeterminate = false;
                } else if (count > 0) {
                    masterCheck.checked = false;
                    masterCheck.indeterminate = true;
                } else {
                    masterCheck.checked = false;
                    masterCheck.indeterminate = false;
                }
            }
        }

        function syncActiveButtons() {
            shippingBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-shipping') === filterState.shipping));
            statusTabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-status') === filterState.status));
            countryBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-country') === filterState.country));
            if (dropSelect) dropSelect.value = filterState.drop;
        }

        function updateFilterCounts() {
            const counts = {
                shipping: { all: 0, grossbrief: 0, maxibrief: 0, paeckchen: 0 },
                status: { all: 0, offen: 0, 'in bearbeitung': 0, abgeschlossen: 0, storniert: 0, 'rueckzahlung-offen': 0, erstattet: 0 },
                country: { all: 0, inland: 0, ausland: 0 }
            };

            const q = filterState.search.toLowerCase().trim();

            orderRows.forEach(row => {
                const rShipping   = row.getAttribute('data-shipping') || 'grossbrief';
                const rStatus     = row.getAttribute('data-status') || 'offen';
                const rRealStatus = row.getAttribute('data-real-status') || rStatus;
                const rDrop       = row.getAttribute('data-drop') || 'unassigned';
                const rCountry    = row.getAttribute('data-country') || 'inland';
                const rSearch     = (row.getAttribute('data-search') || '').toLowerCase();

                const matchDrop   = (filterState.drop === 'all' || rDrop === filterState.drop);
                const matchSearch = (!q || rSearch.includes(q));

                if (!matchDrop || !matchSearch) return;

                let matchStatus;
                if (filterState.status === 'all') matchStatus = true;
                else if (filterState.status === 'storniert') matchStatus = (rRealStatus === 'storniert');
                else matchStatus = (rStatus === filterState.status);

                const matchShipping = (filterState.shipping === 'all' || rShipping === filterState.shipping);
                const matchCountry  = (filterState.country === 'all' || rCountry === filterState.country);

                // Zähler für Shipping-Buttons (kombiniert mit aktuellem Status & Region & Drop & Suche)
                if (matchStatus && matchCountry) {
                    counts.shipping.all++;
                    if (counts.shipping[rShipping] !== undefined) {
                        counts.shipping[rShipping]++;
                    }
                }

                // Zähler für Status-Tabs (kombiniert mit aktueller Portoklasse & Region & Drop & Suche)
                if (matchShipping && matchCountry) {
                    counts.status.all++;
                    if (rRealStatus === 'storniert') {
                        counts.status.storniert++;
                    }
                    if (counts.status[rStatus] !== undefined) {
                        counts.status[rStatus]++;
                    }
                }

                // Zähler für Region-Buttons (kombiniert mit Portoklasse & Status & Drop & Suche)
                if (matchShipping && matchStatus) {
                    counts.country.all++;
                    if (counts.country[rCountry] !== undefined) {
                        counts.country[rCountry]++;
                    }
                }
            });

            // Shipping Portoklassen Pills aktualisieren
            const pillAll = document.getElementById('pill-shipping-all');
            if (pillAll) pillAll.innerText = counts.shipping.all;
            const pillGross = document.getElementById('pill-shipping-grossbrief');
            if (pillGross) pillGross.innerText = counts.shipping.grossbrief;
            const pillMaxi = document.getElementById('pill-shipping-maxibrief');
            if (pillMaxi) pillMaxi.innerText = counts.shipping.maxibrief;
            const pillPaeck = document.getElementById('pill-shipping-paeckchen');
            if (pillPaeck) pillPaeck.innerText = counts.shipping.paeckchen;

            // Status Badges aktualisieren
            statusTabs.forEach(tab => {
                const st = tab.getAttribute('data-status');
                const badge = tab.querySelector('.tab-badge');
                if (badge && counts.status[st] !== undefined) {
                    badge.innerText = counts.status[st];
                }
            });

            // Region Badges aktualisieren
            countryBtns.forEach(btn => {
                const co = btn.getAttribute('data-country');
                const badge = btn.querySelector('.tab-badge');
                if (badge && counts.country[co] !== undefined) {
                    badge.innerText = counts.country[co];
                }
            });
        }

        function updateFilterChips() {
            const bar = document.getElementById('activeFilterChipsBar');
            const container = document.getElementById('chipsContainer');
            if (!bar || !container) return;

            const chips = [];

            if (filterState.shipping !== 'all') {
                const names = {
                    grossbrief: 'Großbrief',
                    maxibrief: 'Maxibrief',
                    paeckchen: '2 kg Päckchen'
                };
                const label = names[filterState.shipping] || filterState.shipping.toUpperCase();
                chips.push({
                    type: 'shipping',
                    label: `Porto: ${label}`,
                    clear: () => { filterState.shipping = 'all'; syncActiveButtons(); applyFilters(); }
                });
            }

            if (filterState.status !== 'all') {
                const names = {
                    offen: 'Offen',
                    'in bearbeitung': 'In Bearbeitung',
                    abgeschlossen: 'Abgeschlossen',
                    storniert: 'Storniert',
                    'rueckzahlung-offen': 'Rückzahlung offen',
                    erstattet: 'Erstattet'
                };
                const label = names[filterState.status] || filterState.status;
                chips.push({
                    type: 'status',
                    label: `Status: ${label}`,
                    clear: () => { filterState.status = 'all'; syncActiveButtons(); applyFilters(); }
                });
            }

            if (filterState.country !== 'all') {
                const label = filterState.country === 'inland' ? 'Inland' : 'Ausland';
                chips.push({
                    type: 'country',
                    label: `Region: ${label}`,
                    clear: () => { filterState.country = 'all'; syncActiveButtons(); applyFilters(); }
                });
            }

            if (filterState.drop !== 'all' && dropSelect) {
                const dropOpt = dropSelect.querySelector(`option[value="${filterState.drop}"]`);
                const dropLabel = dropOpt ? dropOpt.innerText : filterState.drop;
                chips.push({
                    type: 'drop',
                    label: `Drop: ${dropLabel}`,
                    clear: () => { filterState.drop = 'all'; syncActiveButtons(); applyFilters(); }
                });
            }

            if (filterState.search) {
                chips.push({
                    type: 'search',
                    label: `Suche: "${filterState.search}"`,
                    clear: () => {
                        filterState.search = '';
                        if (searchInput) searchInput.value = '';
                        if (clearSearchBtn) clearSearchBtn.style.display = 'none';
                        applyFilters();
                    }
                });
            }

            if (chips.length === 0) {
                bar.style.display = 'none';
                container.innerHTML = '';
            } else {
                bar.style.display = 'flex';
                container.innerHTML = '';
                chips.forEach(c => {
                    const span = document.createElement('span');
                    span.className = `filter-chip chip-${c.type}`;
                    span.innerHTML = `${c.label} <button type="button" class="filter-chip-remove" title="Filter entfernen">&times;</button>`;
                    span.querySelector('.filter-chip-remove').addEventListener('click', c.clear);
                    container.appendChild(span);
                });
            }
        }

        function resetAllFilters() {
            filterState.shipping = 'all';
            filterState.status = 'all';
            filterState.drop = 'all';
            filterState.country = 'all';
            filterState.search = '';

            syncActiveButtons();
            if (searchInput) searchInput.value = '';
            if (clearSearchBtn) clearSearchBtn.style.display = 'none';

            applyFilters();
        }

        function applyFilters() {
            let visibleCount = 0;
            const q = filterState.search.toLowerCase().trim();

            orderRows.forEach(row => {
                const rowShipping   = row.getAttribute('data-shipping') || 'grossbrief';
                const rowStatus     = row.getAttribute('data-status') || 'offen';
                const rowRealStatus = row.getAttribute('data-real-status') || rowStatus;
                const rowDrop       = row.getAttribute('data-drop') || 'unassigned';
                const rowCountry    = row.getAttribute('data-country') || 'inland';
                const rowSearch     = (row.getAttribute('data-search') || '').toLowerCase();

                const matchShipping = (filterState.shipping === 'all' || rowShipping === filterState.shipping);
                let matchStatus;
                if (filterState.status === 'all') {
                    matchStatus = true;
                } else if (filterState.status === 'storniert') {
                    matchStatus = (rowRealStatus === 'storniert');
                } else {
                    matchStatus = (rowStatus === filterState.status);
                }
                const matchDrop    = (filterState.drop === 'all' || rowDrop === filterState.drop);
                const matchCountry = (filterState.country === 'all' || rowCountry === filterState.country);
                const matchSearch  = (!q || rowSearch.includes(q));

                const isVisible = matchShipping && matchStatus && matchDrop && matchCountry && matchSearch;
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            if (noOrdersRow) {
                noOrdersRow.style.display = (visibleCount === 0 && orderRows.length > 0) ? '' : 'none';
            }

            if (visibleCountElem) visibleCountElem.innerText = visibleCount;
            if (exportCountDpElem) exportCountDpElem.innerText = visibleCount;
            if (exportCountScElem) exportCountScElem.innerText = visibleCount;

            // Dynamische Zähler auf allen Filter-Buttons aktualisieren
            updateFilterCounts();

            // Aktive Filter-Chips aktualisieren
            updateFilterChips();

            // URL Parameter ohne Seiten-Reload synchronisieren (history.replaceState)
            const url = new URL(window.location);
            if (filterState.shipping !== 'all') url.searchParams.set('shipping', filterState.shipping);
            else url.searchParams.delete('shipping');

            if (filterState.status !== 'all') url.searchParams.set('status', filterState.status);
            else url.searchParams.delete('status');

            if (filterState.drop !== 'all') url.searchParams.set('drop', filterState.drop);
            else url.searchParams.delete('drop');

            if (filterState.country !== 'all') url.searchParams.set('country', filterState.country);
            else url.searchParams.delete('country');

            if (q) url.searchParams.set('q', q);
            else url.searchParams.delete('q');

            const newUrl = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '');
            history.replaceState(null, '', newUrl);

            updateSelectionDisplay();
        }

        // Versandklassen Filter Klick (inkl. Toggle-Deselect)
        shippingBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const s = btn.getAttribute('data-shipping') || 'all';
                filterState.shipping = (filterState.shipping === s && s !== 'all') ? 'all' : s;
                syncActiveButtons();
                applyFilters();
            });
        });

        // Ausland / Inland Filter (inkl. Toggle-Deselect)
        countryBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const co = btn.getAttribute('data-country') || 'all';
                filterState.country = (filterState.country === co && co !== 'all') ? 'all' : co;
                syncActiveButtons();
                applyFilters();
            });
        });

        // Status Tabs Klick (inkl. Toggle-Deselect)
        statusTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const st = tab.getAttribute('data-status') || 'all';
                filterState.status = (filterState.status === st && st !== 'all') ? 'all' : st;
                syncActiveButtons();
                applyFilters();
            });
        });

        // Drop Auswahl
        if (dropSelect) {
            dropSelect.addEventListener('change', () => {
                filterState.drop = dropSelect.value;
                applyFilters();
            });
        }

        // Schnellsuche
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                filterState.search = searchInput.value;
                if (clearSearchBtn) {
                    clearSearchBtn.style.display = searchInput.value ? 'block' : 'none';
                }
                applyFilters();
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                searchInput.value = '';
                filterState.search = '';
                clearSearchBtn.style.display = 'none';
                applyFilters();
            });
        }

        // Reset Filter Buttons
        if (resetBtn) resetBtn.addEventListener('click', resetAllFilters);
        const chipsResetBtn = document.getElementById('chipsResetBtn');
        if (chipsResetBtn) chipsResetBtn.addEventListener('click', resetAllFilters);

        // Master Checkbox
        if (masterCheck) {
            masterCheck.addEventListener('change', () => {
                const isChecked = masterCheck.checked;
                orderRows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const cb = row.querySelector('.row-check');
                        if (cb) cb.checked = isChecked;
                    }
                });
                updateSelectionDisplay();
            });
        }

        // Alle sichtbaren auswählen
        if (selectAllVisibleBtn) {
            selectAllVisibleBtn.addEventListener('click', () => {
                orderRows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const cb = row.querySelector('.row-check');
                        if (cb) cb.checked = true;
                    }
                });
                updateSelectionDisplay();
            });
        }

        // Auswahl aufheben
        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', () => {
                document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
                updateSelectionDisplay();
            });
        }

        // Einzelne Checkboxen beobachten
        document.querySelectorAll('.row-check').forEach(cb => {
            cb.addEventListener('change', updateSelectionDisplay);
        });

        // Filter-synchroner CSV-Export
        function exportCSV(type) {
            const checkedVisible = Array.from(document.querySelectorAll('.row-check:checked'))
                .filter(cb => {
                    const tr = cb.closest('tr');
                    return tr && tr.style.display !== 'none';
                });

            let idsToExport = [];
            if (checkedVisible.length > 0) {
                // Wenn einzelne Checkboxen markiert sind, exportiere nur diese
                idsToExport = checkedVisible.map(cb => cb.value);
            } else {
                // Wenn keine Checkboxen markiert sind, exportiere alle aktuell gefilterten & sichtbaren Zeilen
                const visibleRows = Array.from(orderRows).filter(tr => tr.style.display !== 'none');
                idsToExport = visibleRows.map(tr => tr.getAttribute('data-id'));
            }

            if (idsToExport.length === 0) {
                alert('Keine Bestellungen für den Export vorhanden!');
                return;
            }

            // POST-Formular dynamisch erzeugen und absenden (verhindert HTTP 414 URL-Längenlimit)
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin.php';
            form.style.display = 'none';

            const fields = {
                export: type,
                ids: idsToExport.join(','),
                shipping: filterState.shipping,
                status: filterState.status,
                drop: filterState.drop,
                country: filterState.country
            };

            for (const [key, val] of Object.entries(fields)) {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = key;
                inp.value = val;
                form.appendChild(inp);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Bulk-Status Änderung
        function bulkComplete() {
            const checkedVisible = Array.from(document.querySelectorAll('.row-check:checked'))
                .filter(cb => {
                    const tr = cb.closest('tr');
                    return tr && tr.style.display !== 'none';
                });

            let ids = checkedVisible.map(cb => cb.value);
            if (ids.length === 0) {
                const visibleRows = Array.from(orderRows).filter(tr => tr.style.display !== 'none');
                if (visibleRows.length === 0) return alert('Keine sichtbaren Bestellungen vorhanden!');
                if (!confirm(`Keine Checkboxen markiert.\n\nMöchtest du ALLE ${visibleRows.length} aktuell sichtbaren Bestellungen auf "Abgeschlossen" setzen?`)) {
                    return;
                }
                ids = visibleRows.map(tr => tr.getAttribute('data-id'));
            } else {
                if (!confirm(`Möchtest du ${ids.length} markierte Bestellung(en) auf "Abgeschlossen" setzen?`)) return;
            }

            fetch('admin.php?action=bulk_status', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ids=${encodeURIComponent(ids.join(','))}&status=abgeschlossen`
            }).then(res => res.json()).then(data => {
                if (data && data.success) {
                    ids.forEach(id => {
                        const row = document.querySelector(`tr[data-id="${id}"]`);
                        if (row) {
                            row.setAttribute('data-status', 'abgeschlossen');
                            const select = row.querySelector('.status-select');
                            if (select) select.value = 'abgeschlossen';
                            const badge = row.querySelector('.badge');
                            if (badge) {
                                badge.className = 'badge abgeschlossen';
                                badge.innerText = 'ABGESCHLOSSEN';
                            }
                        }
                    });
                    applyFilters();
                } else {
                    alert('Fehler beim Aktualisieren des Status.');
                }
            }).catch(err => {
                console.error(err);
                window.location.reload();
            });
        }

        // Status Select Änderung pro Zeile
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const id     = this.getAttribute('data-id');
                const status = this.value;
                const row    = this.closest('tr');
                const name   = this.getAttribute('data-name') || row.getAttribute('data-name') || '';
                const email  = this.getAttribute('data-email') || row.getAttribute('data-email') || '';
                const total  = this.getAttribute('data-total') || row.getAttribute('data-total') || '';

                if (status === 'storniert') {
                    // Modal öffnen statt sofort speichern
                    openCancelModal(id, name, email, total, this);
                    return;
                }

                fetch('admin.php?action=update_status', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(status)}`
                }).then(res => res.json()).then(data => {
                    if (data && data.success) {
                        row.setAttribute('data-status', status);
                        row.setAttribute('data-real-status', status);
                        const badge = row.querySelector('.badge');
                        if (badge) {
                            badge.className = 'badge ' + status.replace(/ /g, '-');
                            badge.innerText = status.toUpperCase();
                        }
                        applyFilters();
                    }
                }).catch(() => window.location.reload());
            });
        });

        // -------------------------------------------------------
        // STORNIERUNG MODAL
        // -------------------------------------------------------
        let _cancelOrderId   = null;
        let _cancelSelectRef = null;
        let _cancelPrevValue = null;

        function openCancelModal(orderId, name, email, total, selectEl) {
            _cancelOrderId   = orderId;
            _cancelSelectRef = selectEl;
            _cancelPrevValue = 'offen'; // restore on cancel
            // Hole echten vorherigen Wert aus data-real-status
            const row = selectEl ? selectEl.closest('tr') : null;
            if (row) _cancelPrevValue = row.getAttribute('data-real-status') || 'offen';

            document.getElementById('cancelModalOrderId').value   = orderId;
            document.getElementById('cancelModalName').innerText  = name || orderId;
            document.getElementById('cancelModalEmail').innerText = email;
            document.getElementById('cancelModalTotal').innerText = total;
            document.getElementById('cancelReason').value      = 'andere';
            document.getElementById('cancelCustomReason').value = '';
            document.getElementById('cancelCustomNote').value   = '';
            document.getElementById('cancelRefundOffen').checked = true;
            document.getElementById('cancelRestoreStock').checked = true;
            document.getElementById('cancelSendEmail').checked   = true;
            toggleCustomReason();
            document.getElementById('cancelOrderModal').classList.add('open');
        }

        function closeCancelModal(revert) {
            document.getElementById('cancelOrderModal').classList.remove('open');
            // Dropdown zurücksetzen wenn abgebrochen
            if (revert && _cancelSelectRef) {
                _cancelSelectRef.value = _cancelPrevValue || 'offen';
            }
            _cancelOrderId   = null;
            _cancelSelectRef = null;
        }

        function toggleCustomReason() {
            const sel = document.getElementById('cancelReason').value;
            const wrap = document.getElementById('cancelCustomReasonWrap');
            wrap.style.display = (sel === 'andere') ? '' : 'none';
        }

        function submitCancelOrder() {
            const id          = document.getElementById('cancelModalOrderId').value;
            const reasonSel   = document.getElementById('cancelReason').value;
            const customReason = document.getElementById('cancelCustomReason').value.trim();
            const customNote  = document.getElementById('cancelCustomNote').value.trim();
            const refundStatus = document.getElementById('cancelRefundOffen').checked ? 'offen' : 'erstattet';
            const restoreStock = document.getElementById('cancelRestoreStock').checked ? '1' : '0';
            const sendEmail   = document.getElementById('cancelSendEmail').checked ? '1' : '0';

            const reasonMap = {
                'vergriffen':      'Artikel leider vergriffen / nicht mehr verfügbar',
                'fehlerhafte_bestellung': 'Fehlerhafte Bestellung (Kundenanfrage)',
                'zahlungsproblem': 'Zahlungsproblem / Zahlung nicht eingegangen',
                'doppelt':         'Doppelte Bestellung',
                'andere':          customReason || 'Stornierung durch Shopbetreiber'
            };
            const reason = reasonMap[reasonSel] || customReason || 'Stornierung durch Shopbetreiber';

            const btn = document.getElementById('cancelSubmitBtn');
            btn.disabled = true;
            btn.innerText = 'WIRD VERARBEITET...';

            const params = new URLSearchParams();
            params.append('id',            id);
            params.append('reason',        reason);
            params.append('custom_note',   customNote);
            params.append('refund_status', refundStatus);
            params.append('restore_stock', restoreStock);
            params.append('send_email',    sendEmail);

            fetch('admin.php?action=cancel_order', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = 'STORNIERUNG BESTÄTIGEN';
                if (data && data.success) {
                    // Row aktualisieren
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        const newFilterStatus = (refundStatus === 'erstattet') ? 'erstattet' : 'rueckzahlung-offen';
                        row.setAttribute('data-status', newFilterStatus);
                        row.setAttribute('data-real-status', 'storniert');
                        row.setAttribute('data-refund', refundStatus);
                        // Badge aktualisieren
                        const badge = document.getElementById('badge-' + id);
                        if (badge) { badge.className = 'badge storniert'; badge.innerText = 'STORNIERT'; }
                        // Refund-Badge + Button
                        const rbadge = document.getElementById('rbadge-' + id);
                        if (refundStatus === 'erstattet') {
                            if (rbadge) { rbadge.className = 'badge refund-erstattet'; rbadge.innerText = 'ERSTATTET'; }
                        }
                    }
                    closeCancelModal(false);
                    // Feedback
                    const mailInfo = sendEmail === '1' ? (data.mail_sent ? ' — Mail versendet' : ' — Mail-Fehler (Log prüfen)') : '';
                    showToast('Bestellung storniert' + mailInfo, data.mail_sent || sendEmail === '0' ? '#166534' : '#cc5500');
                    applyFilters();
                } else {
                    alert('Fehler beim Stornieren: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = 'STORNIERUNG BESTÄTIGEN';
                alert('Netzwerkfehler: ' + err);
            });
        }

        function quickMarkRefunded(orderId, btnEl) {
            if (!confirm('Rückzahlung als erstattet markieren?')) return;
            const params = new URLSearchParams();
            params.append('id', orderId);
            params.append('refund_status', 'erstattet');
            fetch('admin.php?action=update_refund_status', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    const row = btnEl.closest('tr');
                    if (row) {
                        row.setAttribute('data-status', 'erstattet');
                        row.setAttribute('data-refund', 'erstattet');
                        const rbadge = row.querySelector('.badge.refund-offen');
                        if (rbadge) { rbadge.className = 'badge refund-erstattet'; rbadge.innerText = 'ERSTATTET'; }
                        btnEl.remove();
                    }
                    applyFilters();
                    showToast('Als erstattet markiert', '#166534');
                }
            });
        }

        function showToast(msg, bg) {
            const t = document.createElement('div');
            t.innerText = msg;
            Object.assign(t.style, {
                position:'fixed', bottom:'24px', right:'24px', background: bg || '#333',
                color:'#fff', fontWeight:'900', fontSize:'0.9rem', padding:'12px 20px',
                border:'3px solid #000', boxShadow:'4px 4px 0 #000', zIndex:'99999',
                maxWidth:'320px', transition:'opacity .4s'
            });
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 500); }, 3500);
        }

        // Backdrop-Klick schließt Modal
        document.getElementById('cancelOrderModal').addEventListener('click', function(e) {
            if (e.target === this) closeCancelModal(true);
        });
        // ESC schließt Modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeCancelModal(true);
        });

        // Initiale Filter aus URL Parametern wiederherstellen
        (function initFiltersFromUrl() {
            const params = new URLSearchParams(window.location.search);
            if (params.has('shipping')) filterState.shipping = params.get('shipping');
            if (params.has('status')) filterState.status = params.get('status');
            if (params.has('drop')) filterState.drop = params.get('drop');
            if (params.has('country')) filterState.country = params.get('country');
            if (params.has('q') && searchInput) {
                searchInput.value = params.get('q');
                filterState.search = searchInput.value;
                if (clearSearchBtn) clearSearchBtn.style.display = 'block';
            }
            syncActiveButtons();
            applyFilters();
        })();

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

                fetch('admin.php', {
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

    <!-- ====================================================
         STORNIERUNG MODAL
         ==================================================== -->
    <div class="neo-modal-backdrop" id="cancelOrderModal" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
        <div class="neo-modal-card">
            <button class="neo-modal-close" onclick="closeCancelModal(true)" title="Schließen">&times;</button>
            <h2 id="cancelModalTitle"><span style="display:inline-block;width:6px;height:1.6rem;background:var(--accent);margin-right:10px;vertical-align:middle;"></span>Stornierung</h2>
            <p class="neo-modal-subtitle">
                Bestellung von <strong id="cancelModalName"></strong> &bull;
                <span id="cancelModalEmail" style="color:#555;"></span> &bull;
                <strong id="cancelModalTotal"></strong>
            </p>
            <input type="hidden" id="cancelModalOrderId">

            <div class="neo-modal-info-box">
                Nach der Stornierung wird automatisch eine E-Mail an den Kunden mit dem Stornierungsgrund und dem Hinweis auf die Rücküberweisung gesendet.
            </div>

            <label class="neo-modal-label" for="cancelReason">Stornierungsgrund *</label>
            <select class="neo-modal-select" id="cancelReason" onchange="toggleCustomReason()">
                <option value="vergriffen">Artikel vergriffen / nicht verfügbar</option>
                <option value="fehlerhafte_bestellung">Fehlerhafte Bestellung (Kundenwunsch)</option>
                <option value="zahlungsproblem">Zahlungsproblem / Zahlung nicht eingegangen</option>
                <option value="doppelt">Doppelte Bestellung</option>
                <option value="andere" selected>Anderer Grund (bitte angeben)</option>
            </select>

            <div id="cancelCustomReasonWrap">
                <label class="neo-modal-label" for="cancelCustomReason">Eigener Grund</label>
                <input type="text" class="neo-modal-select" id="cancelCustomReason" placeholder="Stornierungsgrund eingeben...">
            </div>

            <label class="neo-modal-label" for="cancelCustomNote">Optionale Anmerkung an den Kunden</label>
            <textarea class="neo-modal-textarea" id="cancelCustomNote" placeholder="Zusätzliche Infos für den Kunden (optional)..."></textarea>

            <label class="neo-modal-label">Rückzahlungsstatus</label>
            <div class="neo-modal-radio-group">
                <label style="border-left:3px solid #cc5500;padding-left:8px;">
                    <input type="radio" name="cancelRefund" id="cancelRefundOffen" value="offen" checked>
                    Rückzahlung noch offen (wird zeitnah überwiesen)
                </label>
                <label style="border-left:3px solid #166534;padding-left:8px;">
                    <input type="radio" name="cancelRefund" id="cancelRefundErstattet" value="erstattet">
                    Geld bereits zurücküberwiesen
                </label>
            </div>

            <label class="neo-modal-check-row">
                <input type="checkbox" id="cancelRestoreStock" checked>
                Lagerbestand automatisch wiederherstellen
            </label>
            <label class="neo-modal-check-row">
                <input type="checkbox" id="cancelSendEmail" checked>
                Stornierungsmail an Kunden senden
            </label>

            <div class="neo-modal-actions">
                <button type="button" class="neo-modal-btn-primary" id="cancelSubmitBtn" onclick="submitCancelOrder()">
                    STORNIERUNG BESTÄTIGEN
                </button>
                <button type="button" class="neo-modal-btn-secondary" onclick="closeCancelModal(true)">
                    Abbrechen
                </button>
            </div>
        </div>
    </div>
</body>
</html>
