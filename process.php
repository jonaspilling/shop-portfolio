<?php
// Fehler-Logging & Notfall-Schutz gegen HTTP 500
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logFolder = __DIR__ . '/logs/';
        if (!is_dir($logFolder)) @mkdir($logFolder, 0775, true);
        $msg = date('Y-m-d H:i:s') . " - FATAL SHUTDOWN ERROR: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
        @file_put_contents($logFolder . 'checkout_errors.log', $msg, FILE_APPEND);
        @file_put_contents($logFolder . 'paypal_debug.log', $msg, FILE_APPEND);
        if (!headers_sent()) {
            http_response_code(500);
            echo "<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Technischer Fehler</h2><p>Es gab ein Problem bei der Verarbeitung deiner Bestellung.<br>Bitte kontaktiere uns direkt.</p></body></html>";
        }
    }
});

// KRITISCH: Session-Name muss identisch mit index.php, checkout.php, shop.php sein!
session_name('KLEBEKUNST_SESSION');
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// PHPMailer: Composer autoload (falls vorhanden), sonst manueller Fallback
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Manueller Fallback: PHPMailer-Klassen direkt einbinden
    $phpmailerDir = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
    if (file_exists($phpmailerDir . 'PHPMailer.php')) {
        require_once $phpmailerDir . 'PHPMailer.php';
        require_once $phpmailerDir . 'SMTP.php';
        require_once $phpmailerDir . 'Exception.php';
    }
    // Falls PHPMailer komplett fehlt, loggen wir das weiter unten
}
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/settings_helper.php';
if (file_exists(__DIR__ . '/mail_templates.php')) {
    require_once __DIR__ . '/mail_templates.php';
}
$PRODUCTS = $products;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Rendert eine einheitliche Neo-Brutalist Erfolgsseite
 */
function render_brutal_success($title, $lead, $details = []) {
    $detailRowsHtml = '';
    if (!empty($details)) {
        $detailRowsHtml = '<div style="background:#fafaf5; border:3px solid #111; padding:16px 18px; margin-bottom:22px; text-align:left; font-size:0.95rem;">';
        foreach ($details as $label => $val) {
            $detailRowsHtml .= '<div style="display:flex; justify-content:space-between; margin-bottom:8px; border-bottom:1px dashed #d0d0d0; padding-bottom:6px;">'
                . '<span style="font-weight:700; color:#555;">' . htmlspecialchars($label) . ':</span>'
                . '<strong style="text-align:right; margin-left:10px;">' . htmlspecialchars($val) . '</strong>'
                . '</div>';
        }
        $detailRowsHtml .= '</div>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - KLEBEKUNST</title>
    <style>
        @font-face { font-family: 'Bebas Neue'; src: url('fonts/BebasNeue-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Inter'; src: url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); font-weight: 100 900; font-style: normal; font-display: swap; }
        :root { --black: #111111; --white: #ffffff; --accent: #ff2a00; --bg: #f0f0e8; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--black);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .success-card {
            background: var(--white);
            border: 4px solid var(--black);
            box-shadow: 8px 8px 0px var(--black);
            max-width: 580px;
            width: 100%;
            padding: 35px 28px;
            text-align: center;
        }
        .success-badge {
            display: inline-block;
            background: #22c55e;
            color: var(--white);
            border: 3px solid var(--black);
            font-weight: 900;
            padding: 6px 14px;
            font-size: 0.9rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            box-shadow: 3px 3px 0px var(--black);
            margin-bottom: 18px;
        }
        h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2rem, 5vw, 2.8rem);
            letter-spacing: 0.04em;
            line-height: 1.05;
            margin-bottom: 12px;
        }
        .lead {
            font-size: 1rem;
            line-height: 1.5;
            font-weight: 600;
            color: #333;
            margin-bottom: 22px;
        }
        .notice-box {
            background: #fff8e6;
            border-left: 4px solid #f59e0b;
            padding: 12px 14px;
            font-size: 0.85rem;
            line-height: 1.4;
            text-align: left;
            margin-bottom: 22px;
            font-weight: 600;
        }
        .btn-home {
            display: block;
            width: 100%;
            background: var(--accent);
            color: var(--white);
            border: 3px solid var(--black);
            box-shadow: 4px 4px 0px var(--black);
            padding: 14px 20px;
            font-size: 1.1rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .btn-home:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px var(--black);
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-badge">Zahlung bestätigt</div>
        <h1>{$title}</h1>
        <p class="lead">{$lead}</p>
        
        {$detailRowsHtml}
        
        <div class="notice-box">
            <strong>Hinweis:</strong> Eine Bestätigungs-E-Mail wurde an deine Adresse verschickt. Bitte schaue auch in deinem <u>Spam-Ordner</u> nach.
        </div>
        
        <a href="shop.php" class="btn-home">ZURÜCK ZUM SHOP</a>
        <p style="font-size: 0.8rem; color: #777; margin-top: 15px;">Automatische Weiterleitung in <span id="redirect-timer">15</span> Sekunden...</p>
    </div>
    <script>
        var count = 15;
        var timerDisplay = document.getElementById('redirect-timer');
        var counter = setInterval(function() {
            count = count - 1;
            if (timerDisplay) {
                timerDisplay.textContent = count;
            }
            if (count <= 0) {
                clearInterval(counter);
                window.location.href = 'shop.php';
            }
        }, 1000);
    </script>
</body>
</html>
HTML;
}

/**
 * Rendert eine einheitliche Neo-Brutalist Fehlerseite mit klarer Handlungsempfehlung
 */
function render_brutal_error($title, $lead, $details = [], $actionUrl = 'checkout.php', $actionText = 'ZURÜCK ZUR KASSE', $isPaymentCaptured = false) {
    $detailRowsHtml = '';
    if (!empty($details)) {
        $detailRowsHtml = '<div style="background:#fafaf5; border:3px solid #111; padding:16px 18px; margin-bottom:22px; text-align:left; font-size:0.95rem;">';
        foreach ($details as $label => $val) {
            $detailRowsHtml .= '<div style="display:flex; justify-content:space-between; margin-bottom:8px; border-bottom:1px dashed #d0d0d0; padding-bottom:6px;">'
                . '<span style="font-weight:700; color:#555;">' . htmlspecialchars($label) . ':</span>'
                . '<strong style="text-align:right; margin-left:10px; color:#111;">' . htmlspecialchars($val) . '</strong>'
                . '</div>';
        }
        $detailRowsHtml .= '</div>';
    }

    $badgeText = $isPaymentCaptured ? 'ZAHLUNG ERHALTEN &bull; PRÜFUNG NÖTIG' : 'FEHLER BEI DER VERARBEITUNG';
    $badgeBg = $isPaymentCaptured ? '#f59e0b' : '#ff2a00';
    $noticeBox = '';
    if ($isPaymentCaptured) {
        $noticeBox = '<div class="notice-box" style="background:#fff3cd; border-left:4px solid #f59e0b; color:#856404;">'
            . '<strong>WICHTIG:</strong> Deine Zahlung wurde verbucht. Dein Geld ist NICHT verloren. Unser Support prüft diesen Fall umgehend manuell. Du erreichst uns per Mail an <u>eintrachtfrankfurt.klebekunst@gmail.com</u> oder auf Instagram <u>@1899klebekunst</u>.'
            . '</div>';
    } else {
        $noticeBox = '<div class="notice-box" style="background:#fff1f2; border-left:4px solid #ff2a00; color:#991b1b;">'
            . '<strong>Hinweis:</strong> Es wurde kein Geld abgebucht. Bitte überprüfe deine Eingaben und versuche es erneut.'
            . '</div>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - KLEBEKUNST</title>
    <style>
        @font-face { font-family: 'Bebas Neue'; src: url('fonts/BebasNeue-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Inter'; src: url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); font-weight: 100 900; font-style: normal; font-display: swap; }
        :root { --black: #111111; --white: #ffffff; --accent: #ff2a00; --bg: #f0f0e8; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--black);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .error-card {
            background: var(--white);
            border: 4px solid var(--black);
            box-shadow: 8px 8px 0px var(--black);
            max-width: 580px;
            width: 100%;
            padding: 35px 28px;
            text-align: center;
        }
        .error-badge {
            display: inline-block;
            background: {$badgeBg};
            color: var(--white);
            border: 3px solid var(--black);
            font-weight: 900;
            padding: 6px 14px;
            font-size: 0.9rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            box-shadow: 3px 3px 0px var(--black);
            margin-bottom: 18px;
        }
        h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2rem, 5vw, 2.8rem);
            letter-spacing: 0.04em;
            line-height: 1.05;
            margin-bottom: 12px;
            color: var(--black);
        }
        .lead {
            font-size: 1rem;
            line-height: 1.5;
            font-weight: 600;
            color: #333;
            margin-bottom: 22px;
        }
        .notice-box {
            padding: 14px 16px;
            font-size: 0.88rem;
            line-height: 1.45;
            text-align: left;
            margin-bottom: 22px;
            font-weight: 600;
            border: 2px solid var(--black);
        }
        .btn-action {
            display: block;
            width: 100%;
            background: var(--black);
            color: var(--white);
            border: 3px solid var(--black);
            box-shadow: 4px 4px 0px var(--black);
            padding: 14px 20px;
            font-size: 1.1rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.1s;
        }
        .btn-action:hover {
            transform: translate(-2px, -2px);
            background: var(--accent);
            box-shadow: 6px 6px 0px var(--black);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-badge">{$badgeText}</div>
        <h1>{$title}</h1>
        <p class="lead">{$lead}</p>
        
        {$detailRowsHtml}
        {$noticeBox}
        
        <a href="{$actionUrl}" class="btn-action">{$actionText}</a>
    </div>
</body>
</html>
HTML;
}




/**
 * mail_templates.php - Hochwertige Neo-Brutalismus HTML- & Plaintext-Templates
 * für Kunden-Bestellbestätigung und Inhaber-Packzettel.
 */

/**
 * 1. KUNDEN-BESTELLBESTÄTIGUNG (HTML)
 * Fokus: Kundenerlebnis, Vertrauen, Übersichtlichkeit, Neo-Brutalismus
 */
if (!function_exists("generate_customer_email_html")) {
function generate_customer_email_html(array $data): string {
    $orderId       = htmlspecialchars($data['order_id'] ?? '');
    $date          = htmlspecialchars($data['date'] ?? date('d.m.Y H:i'));
    $paypalId      = htmlspecialchars($data['paypal_id'] ?? '');
    $name          = htmlspecialchars($data['name'] ?? '');
    $vorname       = htmlspecialchars($data['vorname'] ?? 'Kunde');
    $strasse       = htmlspecialchars($data['strasse'] ?? '');
    $hausnummer    = htmlspecialchars($data['hausnummer'] ?? '');
    $plz           = htmlspecialchars($data['plz'] ?? '');
    $ort           = htmlspecialchars($data['ort'] ?? '');
    $land          = htmlspecialchars($data['land'] ?? 'Deutschland');
    $email         = htmlspecialchars($data['email'] ?? '');
    $subtotal      = htmlspecialchars($data['subtotal'] ?? '0,00 €');
    $discount      = $data['discount'] ? htmlspecialchars($data['discount']) : null;
    $couponCode    = htmlspecialchars($data['coupon_code'] ?? '');
    $shippingLabel = htmlspecialchars($data['shipping_label'] ?? 'Versand');
    $shippingPrice = htmlspecialchars($data['shipping_price'] ?? '0,00 €');
    $total         = htmlspecialchars($data['total'] ?? '0,00 €');
    $items         = $data['items'] ?? [];
    $hasFreePoster = !empty($data['has_free_poster']);

    // Tabellenzeilen für Artikel
    $itemRowsHtml = '';
    foreach ($items as $idx => $it) {
        $title       = htmlspecialchars($it['title'] ?? '');
        $size        = !empty($it['size']) && $it['size'] !== '-' ? ' (' . htmlspecialchars($it['size']) . ')' : '';
        $packDisplay = htmlspecialchars($it['pack_display'] ?? ($it['qty'] . 'x'));
        $priceUnit   = htmlspecialchars($it['price_unit'] ?? '');
        $priceTotal  = htmlspecialchars($it['price_total'] ?? '');
        $bgColor     = ($idx % 2 === 0) ? '#ffffff' : '#fafaf7';

        $itemRowsHtml .= <<<TR
        <tr style="background-color: {$bgColor}; border-bottom: 2px solid #111111;">
            <td style="padding: 12px 14px; font-size: 14px; font-weight: 800; color: #111111; vertical-align: middle;">
                {$title}{$size}
                <div style="font-size: 12px; font-weight: 600; color: #666666; margin-top: 3px;">
                    Einzelpreis: {$priceUnit}
                </div>
            </td>
            <td align="center" style="padding: 12px 10px; font-size: 13px; font-weight: 900; color: #111111; vertical-align: middle; white-space: nowrap;">
                <span style="display: inline-block; background-color: #f0f0e8; border: 2px solid #111111; padding: 3px 8px;">
                    {$packDisplay}
                </span>
            </td>
            <td align="right" style="padding: 12px 14px; font-size: 15px; font-weight: 900; color: #111111; vertical-align: middle; white-space: nowrap;">
                {$priceTotal}
            </td>
        </tr>
TR;
    }

    // Gratis-Poster Zeile
    if ($hasFreePoster) {
        $itemRowsHtml .= <<<TR
        <tr style="background-color: #fff8e6; border-bottom: 2px solid #111111;">
            <td style="padding: 12px 14px; font-size: 14px; font-weight: 900; color: #ff2a00; vertical-align: middle;">
                1x GRATIS-POSTER (Aktion: Ab 2 Packungen)
                <div style="font-size: 12px; font-weight: 700; color: #555555; margin-top: 2px;">
                    Packe ich dir gratis mit ins Paket
                </div>
            </td>
            <td align="center" style="padding: 12px 10px; font-size: 13px; font-weight: 900; color: #111111; vertical-align: middle;">
                <span style="display: inline-block; background-color: #22c55e; color: #ffffff; border: 2px solid #111111; padding: 3px 8px;">
                    1x GRATIS
                </span>
            </td>
            <td align="right" style="padding: 12px 14px; font-size: 15px; font-weight: 900; color: #22c55e; vertical-align: middle;">
                0,00 €
            </td>
        </tr>
TR;
    }

    // Rabattzeile
    $discountRowHtml = '';
    if ($discount !== null) {
        $codeBadge = $couponCode ? ' <span style="font-size: 11px; background-color: #111111; color: #ffffff; padding: 2px 6px; font-weight: 800;">GUTSCHEIN: ' . $couponCode . '</span>' : '';
        $discountRowHtml = <<<ROW
        <tr>
            <td style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #ff2a00;">
                Gutschein-Rabatt{$codeBadge}:
            </td>
            <td align="right" style="padding: 6px 0; font-size: 15px; font-weight: 900; color: #ff2a00;">
                -{$discount}
            </td>
        </tr>
ROW;
    }

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bestellbestätigung - KLEBEKUNST</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f0e8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111111;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f0f0e8" style="background-color: #f0f0e8; padding: 25px 15px;">
        <tr>
            <td align="center">
                <!-- MAIN CONTAINER (600px) -->
                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border: 4px solid #111111;">
                    
                    <!-- BRAND HEADER -->
                    <tr>
                        <td style="background-color: #ff2a00; padding: 20px 24px; border-bottom: 4px solid #111111;">
                            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div style="font-size: 28px; font-weight: 900; letter-spacing: 2px; color: #ffffff; text-transform: uppercase; line-height: 1;">
                                            KLEBEKUNST
                                        </div>
                                    </td>
                                    <td align="right">
                                        <span style="background-color: #111111; color: #ffffff; font-size: 11px; font-weight: 900; padding: 6px 10px; border: 2px solid #ffffff; text-transform: uppercase; letter-spacing: 1px; display: inline-block;">
                                            BESTELLUNG
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CONTENT BODY -->
                    <tr>
                        <td style="padding: 28px 24px;">
                            
                            <!-- STATUS BADGE -->
                            <div style="display: inline-block; background-color: #22c55e; color: #ffffff; border: 2px solid #111111; padding: 6px 12px; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px;">
                                ZAHLUNG ERHALTEN (PAYPAL)
                            </div>

                            <!-- HEADING & GREETING -->
                            <h1 style="margin: 0 0 14px 0; font-size: 26px; font-weight: 900; line-height: 1.15; text-transform: uppercase; color: #111111;">
                                DANKE DIR FÜR DEINE BESTELLUNG!
                            </h1>
                            <p style="margin: 0 0 22px 0; font-size: 15px; line-height: 1.55; color: #222222; font-weight: 500;">
                                Gude <strong>{$vorname}</strong>,<br />
                                deine Bestellung und die Zahlung sind bei mir angekommen! Ich mach deine Aufkleber jetzt schnellstmöglich fertig und schicke sie los.
                            </p>

                            <!-- META-BOX: BESTELLDATEN -->
                            <table width="100%" border="0" cellpadding="8" cellspacing="0" style="border: 3px solid #111111; background-color: #f7f7f0; margin-bottom: 26px; font-size: 13px;">
                                <tr>
                                    <td style="border-bottom: 1px dashed #cccccc; color: #555555; font-weight: 800; text-transform: uppercase;">Bestell-Nr.:</td>
                                    <td align="right" style="border-bottom: 1px dashed #cccccc; font-weight: 900; color: #111111; font-family: monospace; font-size: 14px;">#{$orderId}</td>
                                </tr>
                                <tr>
                                    <td style="border-bottom: 1px dashed #cccccc; color: #555555; font-weight: 800; text-transform: uppercase;">Bestelldatum:</td>
                                    <td align="right" style="border-bottom: 1px dashed #cccccc; font-weight: 800; color: #111111;">{$date}</td>
                                </tr>
                                <tr>
                                    <td style="color: #555555; font-weight: 800; text-transform: uppercase;">PayPal Transaktion:</td>
                                    <td align="right" style="font-weight: 800; color: #111111; font-family: monospace; font-size: 12px;">{$paypalId}</td>
                                </tr>
                            </table>

                            <!-- ARTIKELÜBERSICHT -->
                            <div style="font-size: 15px; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; color: #111111; margin-bottom: 10px;">
                                DAS HAST DU BESTELLT:
                            </div>

                            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="border: 3px solid #111111; margin-bottom: 22px; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #111111; color: #ffffff;">
                                        <th align="left" style="padding: 10px 14px; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Artikel</th>
                                        <th align="center" style="padding: 10px 10px; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Menge</th>
                                        <th align="right" style="padding: 10px 14px; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">Summe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$itemRowsHtml}
                                </tbody>
                            </table>

                            <!-- SUMMENAUFSTELLUNG -->
                            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #444444;">
                                        Warenwert (Zwischensumme):
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 15px; font-weight: 800; color: #111111;">
                                        {$subtotal}
                                    </td>
                                </tr>
                                {$discountRowHtml}
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #444444;">
                                        Versandkosten <span style="font-size: 12px; font-weight: 800; color: #777777;">({$shippingLabel})</span>:
                                    </td>
                                    <td align="right" style="padding: 6px 0; font-size: 15px; font-weight: 800; color: #111111;">
                                        {$shippingPrice}
                                    </td>
                                </tr>
                            </table>

                            <!-- PROMINENTER ENDBETRAG -->
                            <div style="background-color: #ff2a00; color: #ffffff; border: 3px solid #111111; padding: 16px 18px; margin-bottom: 28px;">
                                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td>
                                            <div style="font-size: 12px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #ffffff;">
                                                GESAMTBETRAG
                                            </div>
                                            <div style="font-size: 12px; font-weight: 700; color: #ffe5e0; margin-top: 3px;">
                                                Bezahlt mit PayPal
                                            </div>
                                        </td>
                                        <td align="right">
                                            <div style="font-size: 30px; font-weight: 900; color: #ffffff; line-height: 1;">
                                                {$total}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- LIEFERANSCHRIFT -->
                            <div style="border: 3px solid #111111; background-color: #f7f7f0; padding: 18px; margin-bottom: 26px;">
                                <div style="font-size: 12px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #555555; margin-bottom: 8px;">
                                    HIER GEHT ES HIN (LIEFERADRESSE):
                                </div>
                                <div style="font-size: 15px; font-weight: 800; line-height: 1.45; color: #111111;">
                                    {$name}<br />
                                    {$strasse} {$hausnummer}<br />
                                    {$plz} {$ort}<br />
                                    <strong>{$land}</strong>
                                </div>
                                <div style="margin-top: 10px; font-size: 12px; color: #666666; border-top: 1px dashed #cccccc; padding-top: 8px;">
                                    Stimmt was an der Adresse nicht? Antworte mir einfach direkt auf diese Mail!
                                </div>
                            </div>

                            <!-- RECHTLICHER HINWEIS -->
                            <div style="font-size: 11px; color: #777777; line-height: 1.4; border-top: 1px solid #e0e0e0; padding-top: 14px; margin-bottom: 20px;">
                                Gemäß § 19 UStG wird keine Umsatzsteuer berechnet und ausgewiesen (Kleinunternehmerregelung).
                            </div>

                            <!-- GRUSSFORMEL -->
                            <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #111111;">
                                Viele Grüße und danke dir,<br />
                                <strong style="font-size: 16px; text-transform: uppercase;">Jonas von KLEBEKUNST</strong>
                            </p>

                        </td>
                    </tr>

                    <!-- BOTTOM ACCENT -->
                    <tr>
                        <td style="background-color: #111111; height: 12px; line-height: 12px; font-size: 1px;">
                            &nbsp;
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
}

/**
 * 1b. KUNDEN-BESTELLBESTÄTIGUNG (PLAIN TEXT FALLBACK)
 */
if (!function_exists("generate_customer_email_text")) {
function generate_customer_email_text(array $data): string {
    $orderId       = $data['order_id'] ?? '';
    $date          = $data['date'] ?? date('d.m.Y H:i');
    $paypalId      = $data['paypal_id'] ?? '';
    $name          = $data['name'] ?? '';
    $vorname       = $data['vorname'] ?? 'Kunde';
    $strasse       = $data['strasse'] ?? '';
    $hausnummer    = $data['hausnummer'] ?? '';
    $plz           = $data['plz'] ?? '';
    $ort           = $data['ort'] ?? '';
    $land          = $data['land'] ?? 'Deutschland';
    $subtotal      = $data['subtotal'] ?? '0,00 €';
    $discount      = $data['discount'] ?? null;
    $couponCode    = $data['coupon_code'] ?? '';
    $shippingLabel = $data['shipping_label'] ?? 'Versand';
    $shippingPrice = $data['shipping_price'] ?? '0,00 €';
    $total         = $data['total'] ?? '0,00 €';
    $items         = $data['items'] ?? [];
    $hasFreePoster = !empty($data['has_free_poster']);

    $itemsList = "";
    foreach ($items as $it) {
        $title = $it['title'] ?? '';
        $size = (!empty($it['size']) && $it['size'] !== '-') ? ' (' . $it['size'] . ')' : '';
        $pack = $it['pack_display'] ?? ($it['qty'] . 'x');
        $pt = $it['price_total'] ?? '';
        $itemsList .= "  * {$pack} {$title}{$size} -- {$pt}\n";
    }
    if ($hasFreePoster) {
        $itemsList .= "  * 1x GRATIS-POSTER (Aktion: Ab 2 Packungen) -- 0,00 €\n";
    }

    $discountText = "";
    if ($discount !== null) {
        $discountText = "Rabatt [Gutschein: {$couponCode}]: -{$discount}\n";
    }

    return <<<TXT
==================================================
KLEBEKUNST - BESTELLUNG
==================================================

Gude {$vorname},

alles bestens, deine Bestellung und die Zahlung sind bei mir angekommen!
Ich mache deine Aufkleber jetzt schnellstmöglich fertig und schicke sie los.

BESTELLDATEN:
- Bestell-Nr.: #{$orderId}
- Datum:       {$date}
- PayPal:      {$paypalId} (Zahlung bestätigt)

DAS HAST DU BESTELLT:
{$itemsList}
KOSTENÜBERSICHT:
- Zwischensumme: {$subtotal}
{$discountText}- Versandkosten ({$shippingLabel}): {$shippingPrice}
--------------------------------------------------
GESAMTBETRAG: {$total} (Bezahlt mit PayPal)
==================================================

HIER GEHT ES HIN (LIEFERADRESSE):
{$name}
{$strasse} {$hausnummer}
{$plz} {$ort}
{$land}

Stimmt was an der Adresse nicht? Antworte mir einfach direkt auf diese Mail!

Gemäß § 19 UStG wird keine Umsatzsteuer berechnet und ausgewiesen.

Viele Grüße und danke dir,
Jonas von KLEBEKUNST
TXT;
}
}

/**
 * 2. INHABER-BENACHRICHTIGUNG (HTML)
 * Fokus: Packzettel & Logistik, schnelles Abpacken, direkte Frankieranweisung
 */
if (!function_exists("generate_owner_email_html")) {
function generate_owner_email_html(array $data): string {
    $orderId       = htmlspecialchars($data['order_id'] ?? '');
    $date          = htmlspecialchars($data['date'] ?? date('d.m.Y H:i'));
    $paypalId      = htmlspecialchars($data['paypal_id'] ?? '');
    $name          = htmlspecialchars($data['name'] ?? '');
    $strasse       = htmlspecialchars($data['strasse'] ?? '');
    $hausnummer    = htmlspecialchars($data['hausnummer'] ?? '');
    $plz           = htmlspecialchars($data['plz'] ?? '');
    $ort           = htmlspecialchars($data['ort'] ?? '');
    $land          = htmlspecialchars($data['land'] ?? 'Deutschland');
    $postLand      = htmlspecialchars($data['post_land'] ?? 'DEU');
    $email         = htmlspecialchars($data['email'] ?? '');
    $totalPacks    = (int)($data['total_packs'] ?? 0);
    $refString     = htmlspecialchars($data['ref_string'] ?? '');
    $subtotal      = htmlspecialchars($data['subtotal'] ?? '0,00 €');
    $discount      = $data['discount'] ? htmlspecialchars($data['discount']) : null;
    $couponCode    = htmlspecialchars($data['coupon_code'] ?? '');
    $shippingLabel = htmlspecialchars($data['shipping_label'] ?? 'Versand');
    $shippingPrice = htmlspecialchars($data['shipping_price'] ?? '0,00 €');
    $total         = htmlspecialchars($data['total'] ?? '0,00 €');
    $targetFile    = htmlspecialchars($data['target_file'] ?? 'bestellungen_de.csv');
    $weight        = htmlspecialchars($data['weight'] ?? '200g');
    $items         = $data['items'] ?? [];
    $hasFreePoster = !empty($data['has_free_poster']);

    // Packlisten-Zeilen
    $packRowsHtml = '';
    foreach ($items as $it) {
        $title       = htmlspecialchars($it['title'] ?? '');
        $qty         = (int)($it['qty'] ?? 1);
        $totalUnits  = htmlspecialchars((string)($it['total_units'] ?? ''));
        $ref         = htmlspecialchars($it['ref'] ?? '');
        $packDisplay = htmlspecialchars($it['pack_display'] ?? ($qty . 'x'));

        $packRowsHtml .= <<<ROW
        <tr style="border-bottom: 2px solid #111111;">
            <td style="padding: 10px 12px; font-size: 17px; font-weight: 900; color: #ff2a00; vertical-align: middle; white-space: nowrap;">
                {$qty}x Pack
            </td>
            <td style="padding: 10px 12px; font-size: 15px; font-weight: 800; color: #111111; vertical-align: middle;">
                {$title}
                <div style="font-size: 12px; font-weight: 600; color: #666666; margin-top: 2px;">
                    Gesamt: {$totalUnits} Stück
                </div>
            </td>
            <td align="center" style="padding: 10px 12px; font-size: 14px; font-weight: 900; background-color: #f7f7f0; color: #111111; vertical-align: middle; font-family: monospace;">
                {$ref}
            </td>
        </tr>
ROW;
    }

    // Falls Poster beilegen
    $posterNoticeHtml = '';
    if ($hasFreePoster) {
        $posterNoticeHtml = <<<POSTER
        <div style="background-color: #ff2a00; color: #ffffff; border: 3px solid #111111; padding: 12px 14px; font-weight: 900; font-size: 15px; text-transform: uppercase; margin-top: 14px; letter-spacing: 0.5px;">
            ACHTUNG: 1x GRATIS-POSTER BEILEGEN (ROLLE / KUVERT)!
        </div>
POSTER;
    }

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Packzettel #{$orderId} - KLEBEKUNST</title>
</head>
<body style="margin: 0; padding: 0; background-color: #e5e5dc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111111;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#e5e5dc" style="background-color: #e5e5dc; padding: 20px 12px;">
        <tr>
            <td align="center">
                <!-- MAIN PACKING CONTAINER (620px) -->
                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="max-width: 620px; background-color: #ffffff; border: 4px solid #111111;">
                    
                    <!-- HEADER -->
                    <tr>
                        <td style="background-color: #111111; color: #ffffff; padding: 18px 22px; border-bottom: 4px solid #ff2a00;">
                            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div style="font-size: 12px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; color: #ff2a00;">
                                            NEUE BESTELLUNG &bull; PACKZETTEL
                                        </div>
                                        <div style="font-size: 26px; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; margin-top: 3px; color: #ffffff;">
                                            AUFTRAG #{$orderId}
                                        </div>
                                    </td>
                                    <td align="right">
                                        <div style="font-size: 26px; font-weight: 900; color: #22c55e;">
                                            {$total}
                                        </div>
                                        <div style="font-size: 11px; font-weight: 800; color: #aaaaaa; text-transform: uppercase;">
                                            BEZAHLT MIT PAYPAL
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CONTENT BODY -->
                    <tr>
                        <td style="padding: 22px;">

                            <!-- 1. PACKLISTE HIGHLIGHT BOX (WAS MUSS INS KUVERT?) -->
                            <div style="background-color: #fff8e6; border: 3px solid #111111; padding: 18px; margin-bottom: 22px;">
                                <div style="font-size: 14px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #111111; margin-bottom: 12px; border-bottom: 2px solid #111111; padding-bottom: 6px;">
                                    PACKLISTE (WAS MUSS INS KUVERT?):
                                </div>

                                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border: 2px solid #111111; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background-color: #111111; color: #ffffff;">
                                            <th align="left" style="padding: 8px 12px; font-size: 12px; font-weight: 900; text-transform: uppercase;">Menge</th>
                                            <th align="left" style="padding: 8px 12px; font-size: 12px; font-weight: 900; text-transform: uppercase;">Artikel / Motiv</th>
                                            <th align="center" style="padding: 8px 12px; font-size: 12px; font-weight: 900; text-transform: uppercase;">Kürzel</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$packRowsHtml}
                                    </tbody>
                                </table>

                                {$posterNoticeHtml}

                                <!-- REFERENZ-STRING & GESAMTMENGE -->
                                <table width="100%" border="0" cellpadding="8" cellspacing="0" style="background-color: #ffffff; border: 2px dashed #111111; margin-top: 14px; font-size: 13px;">
                                    <tr>
                                        <td>
                                            <strong style="color: #111111;">ETIKETTEN-KÜRZEL:</strong>
                                            <code style="font-size: 16px; font-weight: 900; color: #ff2a00; margin-left: 6px; letter-spacing: 0.5px;">{$refString}</code>
                                        </td>
                                        <td align="right" style="font-weight: 900; color: #111111; white-space: nowrap;">
                                            GESAMT: {$totalPacks} Packungen
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- 2. VERSAND- & FRANKIER-INFO BOX -->
                            <div style="background-color: #eff6ff; border: 3px solid #111111; padding: 18px; margin-bottom: 22px;">
                                <div style="font-size: 14px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #1e40af; margin-bottom: 12px; border-bottom: 2px solid #111111; padding-bottom: 6px;">
                                    VERSAND- &amp; FRANKIER-INFO:
                                </div>

                                <table width="100%" border="0" cellpadding="6" cellspacing="0" style="font-size: 14px;">
                                    <tr>
                                        <td style="color: #444444; font-weight: 800; width: 160px;">Portoklasse:</td>
                                        <td style="font-size: 16px; font-weight: 900; color: #ff2a00;">
                                            {$shippingLabel} ({$shippingPrice})
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #444444; font-weight: 800;">Zielland / Versandweg:</td>
                                        <td style="font-weight: 800; color: #111111;">
                                            <strong>{$postLand}</strong> &bull; {$land}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #444444; font-weight: 800;">Geschätztes Gewicht:</td>
                                        <td style="font-weight: 800; color: #111111;">
                                            ca. {$weight}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #444444; font-weight: 800;">CSV-Zuweisung:</td>
                                        <td style="font-family: monospace; font-weight: 800; color: #111111;">
                                            bestellungen/{$targetFile}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- 3. EMPFÄNGERADRESSE (FORMATIERT & KOPIERBAR) -->
                            <div style="background-color: #ffffff; border: 3px solid #111111; padding: 18px; margin-bottom: 22px;">
                                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
                                    <tr>
                                        <td style="font-size: 13px; font-weight: 900; text-transform: uppercase; color: #111111;">
                                            LIEFERANSCHRIFT (KOPIERBAR):
                                        </td>
                                        <td align="right">
                                            <span style="background-color: #111111; color: #ffffff; padding: 3px 8px; font-size: 12px; font-weight: 900;">
                                                {$postLand}
                                            </span>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Kopierbarer Textblock -->
                                <div style="background-color: #f7f7f0; border: 2px solid #111111; padding: 14px; font-family: Consolas, 'Courier New', monospace; font-size: 15px; line-height: 1.5; font-weight: 700; color: #111111;">
                                    {$name}<br />
                                    {$strasse} {$hausnummer}<br />
                                    {$plz} {$ort}<br />
                                    {$land}
                                </div>

                                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 12px; font-size: 13px;">
                                    <tr>
                                        <td style="color: #555555; font-weight: 700;">Kunden-E-Mail:</td>
                                        <td align="right">
                                            <a href="mailto:{$email}" style="color: #ff2a00; font-weight: 900; text-decoration: underline;">{$email}</a>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- 4. FINANZ- & TRANSAKTIONSDATEN -->
                            <table width="100%" border="0" cellpadding="6" cellspacing="0" style="border: 2px solid #111111; background-color: #f7f7f0; font-size: 13px;">
                                <tr>
                                    <td style="color: #666666; font-weight: 700;">PayPal-Bestellnummer:</td>
                                    <td align="right" style="font-family: monospace; font-weight: 800; color: #111111;">{$paypalId}</td>
                                </tr>
                                <tr>
                                    <td style="color: #666666; font-weight: 700;">Warenwert (Netto/Zwischensumme):</td>
                                    <td align="right" style="font-weight: 800; color: #111111;">{$subtotal}</td>
                                </tr>
                                <tr>
                                    <td style="color: #666666; font-weight: 700;">Berechnetes Porto:</td>
                                    <td align="right" style="font-weight: 800; color: #111111;">{$shippingPrice}</td>
                                </tr>
                                <tr style="background-color: #111111; color: #ffffff;">
                                    <td style="font-weight: 900; padding: 10px 8px; color: #ffffff;">GESAMTBETRAG:</td>
                                    <td align="right" style="font-size: 17px; font-weight: 900; color: #22c55e; padding: 10px 8px;">{$total}</td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color: #111111; color: #888888; padding: 12px 20px; font-size: 11px; text-align: center; border-top: 2px solid #333333;">
                            KLEBEKUNST SHOP-SYSTEM &bull; Bestelleingang: {$date}
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
}

/**
 * 2b. INHABER-BENACHRICHTIGUNG (PLAIN TEXT FALLBACK)
 */
if (!function_exists("generate_owner_email_text")) {
function generate_owner_email_text(array $data): string {
    $orderId       = $data['order_id'] ?? '';
    $date          = $data['date'] ?? date('d.m.Y H:i');
    $paypalId      = $data['paypal_id'] ?? '';
    $name          = $data['name'] ?? '';
    $strasse       = $data['strasse'] ?? '';
    $hausnummer    = $data['hausnummer'] ?? '';
    $plz           = $data['plz'] ?? '';
    $ort           = $data['ort'] ?? '';
    $land          = $data['land'] ?? 'Deutschland';
    $postLand      = $data['post_land'] ?? 'DEU';
    $email         = $data['email'] ?? '';
    $totalPacks    = (int)($data['total_packs'] ?? 0);
    $refString     = $data['ref_string'] ?? '';
    $subtotal      = $data['subtotal'] ?? '0,00 €';
    $shippingLabel = $data['shipping_label'] ?? 'Versand';
    $shippingPrice = $data['shipping_price'] ?? '0,00 €';
    $total         = $data['total'] ?? '0,00 €';
    $targetFile    = $data['target_file'] ?? 'bestellungen_de.csv';
    $weight        = $data['weight'] ?? '200g';
    $items         = $data['items'] ?? [];
    $hasFreePoster = !empty($data['has_free_poster']);

    $packList = "";
    foreach ($items as $it) {
        $title = $it['title'] ?? '';
        $qty = $it['qty'] ?? 1;
        $totalUnits = $it['total_units'] ?? '';
        $ref = $it['ref'] ?? '';
        $packList .= "  * {$qty}x Pack {$title} ({$totalUnits} Stk.) [Kürzel: {$ref}]\n";
    }
    if ($hasFreePoster) {
        $packList .= "  * 1x GRATIS-POSTER BEILEGEN!\n";
    }

    return <<<TXT
==================================================
PACKZETTEL #{$orderId} - NEUE BESTELLUNG
==================================================
Eingegangen: {$date}
Gesamtumsatz: {$total} (PayPal: {$paypalId})

--------------------------------------------------
1. PACKLISTE (WAS MUSS INS KUVERT?):
--------------------------------------------------
{$packList}
Gesamtzahl Packs: {$totalPacks} Packungen
Etikett-Kürzel:   {$refString}

--------------------------------------------------
2. FRANKIERUNG & LOGISTIK:
--------------------------------------------------
Portoklasse:  {$shippingLabel} ({$shippingPrice})
Zielland:     {$postLand} ({$land})
Gewicht:      ca. {$weight}
CSV-Datei:    bestellungen/{$targetFile}

--------------------------------------------------
3. LIEFERADRESSE (KOPIERBAR):
--------------------------------------------------
{$name}
{$strasse} {$hausnummer}
{$plz} {$ort}
{$land}

Kunden-Email: {$email}
==================================================
TXT;
}
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // E. System-Logging: jeden eingehenden POST & Session mit Zeitstempel detailliert festhalten
    $logFolder = __DIR__ . '/logs/';
    if (!is_dir($logFolder)) mkdir($logFolder, 0775, true);

    // DSGVO: Automatische Löschung von Logdateien älter als 14 Tage (Speicherbegrenzung gem. Art. 5 Abs. 1 lit. e DSGVO)
    $oldLogs = glob($logFolder . '*.log');
    if ($oldLogs) {
        $retentionLimit = time() - (14 * 86400);
        foreach ($oldLogs as $lf) {
            if (filemtime($lf) < $retentionLimit) {
                @unlink($lf);
            }
        }
    }
    
    $debugLogFile = $logFolder . 'paypal_debug.log';
    $debugEntry = "==================================================\n"
        . "[" . date('Y-m-d H:i:s') . "] INCOMING CHECKOUT POST\n"
        . "Session-ID:   " . session_id() . "\n"
        . "Remote-IP:    " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n"
        . "User-Agent:   " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n"
        . "POST-Daten:\n"  . print_r($_POST, true) . "\n"
        . "SESSION-Cart:\n" . print_r($_SESSION['cart'] ?? 'KEIN WARENKORB', true) . "\n";
    file_put_contents($debugLogFile, $debugEntry, FILE_APPEND);

    // E. orders.log (Kompaktversion)
    $logPayload = $_POST;
    unset($logPayload['paypal_order_id']);
    file_put_contents($logFolder . 'orders.log',
        date('Y-m-d H:i:s') . ' - Incoming POST: ' . json_encode($logPayload, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND
    );

    // C. Eingaben bereinigen & Sanitisieren
    $rawName     = trim($_POST['Name'] ?? '');
    $rawStrasse  = trim($_POST['Strasse'] ?? '');
    $rawPLZ      = trim($_POST['PLZ'] ?? '');
    $rawOrt      = trim($_POST['Ort'] ?? '');
    $rawLand     = trim($_POST['Land'] ?? '');
    $rawEuLand   = trim($_POST['Spezifisches_EU_Land'] ?? '');
    $rawEmail    = trim($_POST['Kunden_Email'] ?? '');
    $rawPaypalId = trim($_POST['paypal_order_id'] ?? '');

    $name          = htmlspecialchars($rawName,    ENT_QUOTES, 'UTF-8');
    $strasse       = htmlspecialchars($rawStrasse, ENT_QUOTES, 'UTF-8');
    $plz           = htmlspecialchars($rawPLZ,     ENT_QUOTES, 'UTF-8');
    $ort           = htmlspecialchars($rawOrt,     ENT_QUOTES, 'UTF-8');
    $land          = htmlspecialchars($rawLand,    ENT_QUOTES, 'UTF-8');
    $eu_land       = htmlspecialchars($rawEuLand,  ENT_QUOTES, 'UTF-8');
    $email         = filter_var($rawEmail, FILTER_SANITIZE_EMAIL);
    $paypalOrderId = htmlspecialchars($rawPaypalId, ENT_QUOTES, 'UTF-8');
    $paypalCaptureId = htmlspecialchars(trim($_POST['paypal_capture_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $paypalStatus  = htmlspecialchars(trim($_POST['paypal_status'] ?? ''), ENT_QUOTES, 'UTF-8');
    $paypalCapturedAmount = (isset($_POST['paypal_amount']) && is_numeric($_POST['paypal_amount'])) ? (float)$_POST['paypal_amount'] : null;

    $hasPaidViaPaypal = (!empty($rawPaypalId) && $rawPaypalId !== 'KOSTENLOSE BESTELLUNG');

    // 1. Doppelbuchungs-Schutz: Dieselbe PayPal-ID darf nicht zweimal verarbeitet werden
    if ($hasPaidViaPaypal) {
        $allOrdersCheck = get_all_orders();
        foreach ($allOrdersCheck as $existingOrder) {
            if (isset($existingOrder['paypal_id']) && $existingOrder['paypal_id'] === $paypalOrderId) {
                file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - DUPLIKAT abgeblockt! PayPal-ID bereits erfasst: $paypalOrderId\n", FILE_APPEND);
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - DUPLIKAT abgeblockt! PayPal-ID bereits erfasst: $paypalOrderId\n", FILE_APPEND);
                unset($_SESSION['cart']);
                render_brutal_success(
                    'BESTELLUNG BEREITS ERFASST',
                    'Deine Zahlung wurde bereits verarbeitet und deine Bestellung ist im System registriert.',
                    [
                        'PayPal-ID' => $paypalOrderId,
                        'Status'    => 'Bereits erfasst'
                    ]
                );
                exit;
            }
        }
    }

    // 2. Datenvollständigkeit & Validierung
    $validationErrors = [];
    $requiredFields = [
        'Name'         => 'Vor- und Nachname',
        'Strasse'      => 'Straße und Hausnummer',
        'PLZ'          => 'Postleitzahl',
        'Ort'          => 'Ort',
        'Land'         => 'Land',
        'Kunden_Email' => 'E-Mail-Adresse'
    ];
    foreach ($requiredFields as $fieldKey => $fieldLabel) {
        if (empty(trim($_POST[$fieldKey] ?? ''))) {
            $validationErrors[] = "Das Pflichtfeld '{$fieldLabel}' fehlt.";
        }
    }

    if ($land === "Anderes EU-Land" && empty(trim($rawEuLand))) {
        $validationErrors[] = "Bitte gib dein spezifisches EU-Lieferland an.";
    }

    if (!empty($rawEmail) && !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
        $validationErrors[] = "Die eingegebene E-Mail-Adresse ({$rawEmail}) ist ungültig.";
    }

    if ($land === 'Deutschland' && !empty($rawPLZ) && !preg_match('/^[0-9]{5}$/', $rawPLZ)) {
        $validationErrors[] = "Für Lieferungen innerhalb Deutschlands muss die PLZ genau 5 Ziffern haben.";
    }

    if (!empty($validationErrors)) {
        $errSummary = implode(" | ", $validationErrors);
        file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - Validierungsfehler: {$errSummary}\n", FILE_APPEND);
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Validierungsfehler: {$errSummary}\n", FILE_APPEND);

        if ($hasPaidViaPaypal) {
            // Zahlung ist erfolgt – Notfall-Bestellung sichern, damit kein Geld verloren geht!
            $emergencyOrder = [
                'id' => uniqid('ord_err_'),
                'paypal_id' => $paypalOrderId,
                'paypal_capture_id' => $paypalCaptureId,
                'date' => date('d.m.Y H:i'),
                'name' => !empty($name) ? $name : 'Unvollständig',
                'strasse' => $strasse,
                'hausnummer' => '',
                'plz' => $plz,
                'ort' => $ort,
                'land' => $land,
                'email' => $email,
                'artikel' => 'Zahlung erhalten, aber Adressdaten waren fehlerhaft: ' . $errSummary,
                'gesamt' => ($paypalCapturedAmount !== null ? number_format($paypalCapturedAmount, 2, ',', '.') . ' €' : 'Siehe PayPal'),
                'status' => 'daten_unvollstaendig_pruefen'
            ];
            add_order($emergencyOrder);

            render_brutal_error(
                'ZAHLUNG ERHALTEN &ndash; ADRESSE PRÜFEN',
                'Deine Zahlung wurde über PayPal erfasst, aber bei deinen Lieferdaten gab es einen Fehler: ' . htmlspecialchars($errSummary) . '. Keine Sorge, dein Geld ist sicher. Unser Support prüft deinen Auftrag manuell.',
                [
                    'PayPal-Transaktion' => $paypalOrderId,
                    'Status'             => 'In manueller Klärung',
                    'Fehlerhinweis'      => $validationErrors[0]
                ],
                'shop.php',
                'ZURÜCK ZUM SHOP',
                true
            );
            exit;
        } else {
            render_brutal_error(
                'ANGABEN UNVOLLSTÄNDIG',
                'Bitte korrigiere deine Eingaben, um fortzufahren: ' . htmlspecialchars(implode(', ', $validationErrors)),
                ['Hinweis' => $validationErrors[0]],
                'checkout.php',
                'ZURÜCK ZUR KASSE',
                false
            );
            exit;
        }
    }

    $coupon_code = isset($_POST['coupon_code']) ? strtoupper(trim($_POST['coupon_code'])) : '';
    $couponsFile = 'coupons.json';
    $coupons = file_exists($couponsFile) ? json_decode(file_get_contents($couponsFile), true) : [];

    // 3. Warenkorb prüfen & ggf. aus POST rekonstruieren
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $reconstructedCart = [];
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'Menge_') === 0 && is_numeric($v) && (int)$v > 0) {
                $pId = substr($k, 6);
                if (isset($PRODUCTS[$pId])) {
                    $reconstructedCart[$pId] = (int)$v;
                }
            }
        }
        if (!empty($reconstructedCart)) {
            $_SESSION['cart'] = $reconstructedCart;
            file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - WARENKORB REKONSTRUIERT aus POST-Parametern: " . json_encode($reconstructedCart) . "\n", FILE_APPEND);
        }
    }

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ABBRUCH: Warenkorb leer oder Session abgelaufen. Session-ID: " . session_id() . "\n", FILE_APPEND);
        file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - ABBRUCH: Warenkorb leer / Session abgelaufen. PayPal-ID: {$paypalOrderId}\n", FILE_APPEND);

        if ($hasPaidViaPaypal) {
            $emergencyOrder = [
                'id' => uniqid('ord_session_'),
                'paypal_id' => $paypalOrderId,
                'paypal_capture_id' => $paypalCaptureId,
                'date' => date('d.m.Y H:i'),
                'name' => $name,
                'strasse' => $strasse,
                'hausnummer' => '',
                'plz' => $plz,
                'ort' => $ort,
                'land' => $land,
                'email' => $email,
                'artikel' => 'Warenkorb-Session war abgelaufen. Gekaufte Artikel anhand PayPal-Transaktion zuordnen!',
                'gesamt' => ($paypalCapturedAmount !== null ? number_format($paypalCapturedAmount, 2, ',', '.') . ' €' : 'Siehe PayPal'),
                'status' => 'session_abgelaufen_manuell_pruefen'
            ];
            add_order($emergencyOrder);

            render_brutal_error(
                'ZAHLUNG ERHALTEN &ndash; BESTELLUNG IN PRÜFUNG',
                'Deine Zahlung wurde verbucht! Aufgrund einer abgelaufenen Browser-Sitzung ordnen wir deine Artikel umgehend manuell zu. Du musst nichts weiter unternehmen.',
                [
                    'PayPal-Transaktion' => $paypalOrderId,
                    'Status'             => 'In Prüfung (Zahlung bestätigt)',
                    'E-Mail'             => $email
                ],
                'shop.php',
                'ZURÜCK ZUM SHOP',
                true
            );
            exit;
        } else {
            render_brutal_error(
                'WARENKORB LEER',
                'Dein Warenkorb ist leer oder deine Sitzung ist abgelaufen. Bitte lege deine gewünschten Artikel erneut in den Warenkorb.',
                [],
                'shop.php',
                'ZUM SHOP',
                false
            );
            exit;
        }
    }

    $finalesLand = ($land === "Anderes EU-Land") ? $eu_land : $land;

    $nameTeile = explode(' ', trim($name));
    $vorname = $nameTeile[0];

    $subtotal = 0;
    $totalPacks = 0;
    $emailItemsList = "";
    $itemsByProduct = [];
    $orderStructuredItems = [];
    $productionByProduct = [];
    $uniqueBoughtCount = 0;

    $defaultSizes = ['XS' => 0, 'S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0, 'XXL' => 0, '3XL' => 0, '4XL' => 0];

    // CLEAN CODE: Zerschneidet keine IDs mit Unterstrichen mehr!
    foreach ($_SESSION['cart'] as $cartKey => $qty) {
        $baseId = resolve_product_base_id($cartKey, $PRODUCTS);
        $size = '';
        if ($baseId !== null) {
            $prod = $PRODUCTS[$baseId];
            $realId = isset($prod['id']) ? (string)$prod['id'] : (string)$baseId;
            if (strpos((string)$cartKey, $realId . '_') === 0) {
                $size = substr((string)$cartKey, strlen($realId) + 1);
            }
        } else {
            $lastUnderscore = strrpos($cartKey, '_');
            $extractedId = ($lastUnderscore !== false) ? substr($cartKey, 0, $lastUnderscore) : $cartKey;
            $size = ($lastUnderscore !== false) ? substr($cartKey, $lastUnderscore + 1) : '';
            foreach ($PRODUCTS as $k => $p) {
                $realId = isset($p['id']) ? (string)$p['id'] : (string)$k;
                if ($realId === $extractedId) {
                    $baseId = $k;
                    break;
                }
            }
        }
        
        if ($qty > 0 && $baseId !== null && isset($PRODUCTS[$baseId])) {
            $prod = $PRODUCTS[$baseId];
            $prefix = $prod['ref_prefix'] ?? '';
            if (empty($prefix)) {
                if ($baseId === 'sticker1') $prefix = 'Waldstadion';
                elseif ($baseId === 'sticker2') $prefix = 'RebellingKids';
                elseif ($baseId === 'sticker3') $prefix = 'Schwenker';
                else $prefix = strtoupper(substr((string)$baseId, 0, 1));
            }
            
            $sizeOrder = ['XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5, 'XXL' => 6, '3XL' => 7, '4XL' => 8];
            $order = $sizeOrder[$size] ?? 99;

            if (!isset($itemsByProduct[$baseId])) {
                $itemsByProduct[$baseId] = [];
                $productionByProduct[$baseId] = $defaultSizes;
                $uniqueBoughtCount++;
            }

            $hasSizes = !empty($prod['has_sizes']) && !empty($size);
            if ($hasSizes) {
                $refItem = (strlen($prefix) === 1) ? "{$qty}{$prefix}-{$size}" : "{$qty}-{$prefix}-{$size}";
            } else {
                $refItem = "{$qty}-{$prefix}";
            }

            $itemsByProduct[$baseId][] = [
                'qty'   => $qty,
                'ref'   => $refItem,
                'title' => $prod['title'],
                'size'  => $size,
                'order' => $order
            ];

            if (isset($productionByProduct[$baseId][$size])) {
                $productionByProduct[$baseId][$size] += $qty;
            } else {
                $productionByProduct[$baseId][$size] = $qty;
            }

            $qtyStep = isset($prod['qty_step']) ? (int)$prod['qty_step'] : 1;
            $qtyUnit = isset($prod['qty_unit']) ? trim($prod['qty_unit']) : 'x';
            $displayQty = ($qtyStep > 1) ? ($qty * $qtyStep) . ' ' . $qtyUnit : $qty . 'x';

            $emailItemsList .= "- {$displayQty} {$prod['title']} ({$size})\n";
            $priceRaw = (string)$prod['price'];
            $priceClean = get_clean_price($priceRaw);
            $subtotal += ($qty * $priceClean);
            $totalPacks += (int)$qty;

            $orderStructuredItems[] = [
                'product_id' => $baseId,
                'title'      => $prod['title'],
                'size'       => $size,
                'qty'        => $qty,
                'price'      => $priceClean
            ];
        }
    }

    $refParts = [];
    $sortBySize = function($a, $b) { return $a['order'] <=> $b['order']; };

    foreach ($itemsByProduct as $pId => $pItems) {
        usort($pItems, $sortBySize);
        foreach ($pItems as $item) {
            $refParts[] = $item['ref'];
        }
    }

    $plakatFile = 'plakat_stock.json';
    $plakateAvailable = file_exists($plakatFile) ? (json_decode(file_get_contents($plakatFile), true)['available'] ?? 50) : 50;

    if (isset($_POST['free_poster']) && $_POST['free_poster'] === 'yes' && $uniqueBoughtCount >= 2 && $plakateAvailable > 0) {
        if (!in_array("X-PLAKAT", $refParts)) {
            array_unshift($refParts, "X-PLAKAT");
            $emailItemsList .= "- 1x GRATIS PLAKAT (Special-Aktion)\n";
            
            $newStock = max(0, $plakateAvailable - 1);
            @file_put_contents($plakatFile, json_encode(['available' => $newStock]), LOCK_EX);
        }
    }

    // Prüfung auf leeren Warenkorb nach Filterung
    if (empty($orderStructuredItems) || $totalPacks <= 0) {
        file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - FEHLER: Keine gültigen Artikel im Warenkorb gefunden. POST: " . json_encode($_POST) . "\n", FILE_APPEND);
        if ($hasPaidViaPaypal) {
            $emergencyOrder = [
                'id' => uniqid('ord_empty_'),
                'paypal_id' => $paypalOrderId,
                'paypal_capture_id' => $paypalCaptureId,
                'date' => date('d.m.Y H:i'),
                'name' => $name,
                'strasse' => $strasse,
                'hausnummer' => '',
                'plz' => $plz,
                'ort' => $ort,
                'land' => $land,
                'email' => $email,
                'artikel' => 'Warenkorb enthielt keine gültigen Artikel. Manuell anhand PayPal prüfen!',
                'gesamt' => ($paypalCapturedAmount !== null ? number_format($paypalCapturedAmount, 2, ',', '.') . ' €' : 'Siehe PayPal'),
                'status' => 'artikel_ungueltig_pruefen'
            ];
            add_order($emergencyOrder);

            render_brutal_error(
                'ZAHLUNG ERHALTEN &ndash; ARTIKEL PRÜFEN',
                'Deine Zahlung wurde erfasst, aber die Artikel konnten nicht automatisch zugeordnet werden. Unser Support prüft deine Bestellung umgehend manuell.',
                ['PayPal-ID' => $paypalOrderId, 'Status' => 'In manueller Prüfung', 'E-Mail' => $email],
                'shop.php',
                'ZURÜCK ZUM SHOP',
                true
            );
            exit;
        } else {
            render_brutal_error('KEINE GÜLTIGEN ARTIKEL', 'Es konnten keine gültigen Artikel im Warenkorb gefunden werden.', [], 'shop.php', 'ZUM SHOP', false);
            exit;
        }
    }

    $etikettReferenz = implode(" + ", $refParts); 

    // Serverseitige 3-stufige Versandkosten-Validierung (Staffel nach Packungsanzahl & Land)
    $shippingInfo = calculate_shipping($totalPacks, $land);
    $versand = ($subtotal > 0) ? $shippingInfo['price'] : 0;

    // Vollständige Gutschein-Unterstützung (alle Typen aus coupons.json)
    $discount = 0;
    if (!empty($coupon_code) && isset($coupons[$coupon_code])) {
        $coupon = $coupons[$coupon_code];
        $cType = $coupon['type'] ?? '';
        $cVal  = (float)($coupon['value'] ?? 0);

        if ($cType === 'percent') {
            $discount = $subtotal * ($cVal / 100);
        } elseif ($cType === 'fixed') {
            $discount = min($cVal, $subtotal);
        } elseif ($cType === 'free') {
            $discount = $subtotal + $versand;
        } elseif ($cType === 'items_free') {
            $discount = $subtotal;
        } elseif ($cType === 'free_shipping') {
            $discount = $versand;
        } elseif ($cType === 'percent_free_shipping') {
            $discount = ($subtotal * ($cVal / 100)) + $versand;
        }

        if ($discount > ($subtotal + $versand)) {
            $discount = $subtotal + $versand;
        }
        
        $emailItemsList .= "\n> Gutscheincode genutzt: $coupon_code (-" . number_format($discount, 2, ',', '.') . " €)\n";
        $etikettReferenz .= " [CODE: $coupon_code]";
    }
    
    $total = $subtotal + $versand - $discount;
    if ($total < 0) $total = 0;

    $fmtSubtotal = number_format($subtotal, 2, ',', '.') . " €";
    $fmtDiscount = number_format($discount, 2, ',', '.') . " €";
    $fmtVersand  = number_format($versand, 2, ',', '.') . " €";
    $fmtTotal    = number_format($total, 2, ',', '.') . " €";

    // Prüfung: Ist Bestellung legitim kostenlos oder ist Zahlung zwingend erforderlich?
    $isLegitimatelyFree = ($total <= 0.001);
    if (!$isLegitimatelyFree && !$hasPaidViaPaypal) {
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ABBRUCH: Kostenpflichtige Bestellung ohne PayPal-Zahlung ($total EUR).\n", FILE_APPEND);
        file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - ABBRUCH: Zahlung erforderlich, aber keine paypal_order_id erhalten.\n", FILE_APPEND);
        render_brutal_error(
            'ZAHLUNG ERFORDERLICH',
            'Für diese Bestellung ist eine Zahlung per PayPal erforderlich. Es wurde keine Transaktions-ID übermittelt.',
            ['Betrag' => $fmtTotal],
            'checkout.php',
            'ZUR KASSE',
            false
        );
        exit;
    }
    if ($isLegitimatelyFree && empty($paypalOrderId)) {
        $paypalOrderId = 'KOSTENLOSE BESTELLUNG';
    }

    // Passgenauigkeit PayPal-Summe vs. Backend-Berechnung prüfen & manipulierte Zahlungen blockieren/markieren
    $orderStatus = 'offen';
    $adminSubjectPrefix = '';
    $calcLog = "Kalkulation: Zwischensumme=" . number_format($subtotal, 2) . " EUR, Versand=" . number_format($versand, 2) . " EUR ({$shippingInfo['label']}), Rabatt=" . number_format($discount, 2) . " EUR => Berechnete Gesamtsumme=" . number_format($total, 2) . " EUR\n";
    if ($paypalCapturedAmount !== null && !$isLegitimatelyFree) {
        $diff = abs($paypalCapturedAmount - $total);
        $calcLog .= "PayPal-Zahlbetrag: " . number_format($paypalCapturedAmount, 2) . " EUR (Differenz: " . number_format($diff, 2) . " EUR)\n";
        if ($diff > 0.10) {
            $orderStatus = 'pruefen_betrag_abweichend';
            $adminSubjectPrefix = '[BETRAG-ABWEICHUNG!] ';
            $calcLog .= "KRITISCHE WARNUNG: Signifikante Abweichung zwischen PayPal ($paypalCapturedAmount) und Backend ($total)!\n";
            file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - " . $calcLog, FILE_APPEND);
        } else {
            $calcLog .= "BETRÄGE STIMMEN ÜBEREIN (Cent-genau).\n";
        }
    }
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - " . $calcLog, FILE_APPEND);

    if (!empty($orderStructuredItems) && ($subtotal > 0 || $totalPacks > 0)) { 
        $totalShirts = array_sum($_SESSION['cart']);
        $gewicht = ($totalShirts * 200) . 'g';

        $csvFolder = __DIR__ . '/bestellungen/';
        if (!is_dir($csvFolder)) {
            @mkdir($csvFolder, 0777, true);
        }
        @chmod($csvFolder, 0777);

        $targetFile = ($land === "Deutschland") ? 'bestellungen_de.csv' : 'bestellungen_ausland.csv';
        $fullPath = $csvFolder . $targetFile;

        $kundenStrasse = trim($strasse);
        $kundenHausnummer = '';
        if (preg_match('/^(.*?)\s+([\d]+[\w\/\-\s]*)$/u', $kundenStrasse, $matches)) {
            $kundenStrasse = rtrim(trim($matches[1]), ','); 
            $kundenHausnummer = trim($matches[2]);
        } elseif (preg_match('/^(.*?)\s*([\d]+[a-zA-Z\-\s]*)$/', $kundenStrasse, $matches)) {
            $kundenStrasse = rtrim(trim($matches[1]), ','); 
            $kundenHausnummer = trim($matches[2]);
        } else {
            file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - HINWEIS: Keine separate Hausnummer erkannt in '$kundenStrasse'.\n", FILE_APPEND);
        }

        $countryMap = ['Deutschland' => 'DEU', 'Österreich' => 'AUT', 'Schweiz' => 'CHE'];
        $postLand = $countryMap[$land] ?? strtoupper(substr($finalesLand, 0, 3));
        if (empty($postLand)) $postLand = 'DEU';
        
        $csvLine = [
            $name, $kundenStrasse, $kundenHausnummer, $plz, $ort, $postLand, trim($etikettReferenz), $email, $gewicht
        ];
        
        $csvLine = array_map(function($val) {
            return trim(str_replace([';', '"', "\n", "\r"], ' ', $val));
        }, $csvLine);

        $isNewFile = !file_exists($fullPath) || filesize($fullPath) == 0;
        $csvSuccess = false;
        $handle = @fopen($fullPath, 'a');
        if ($handle) {
            if (flock($handle, LOCK_EX)) {
                if ($isNewFile) {
                    $header = "NAME;STRASSE;NUMMER;PLZ;STADT;LAND;REFERENZ;EMAIL;GEWICHT\r\n";
                    $headerConverted = function_exists('mb_convert_encoding') ? mb_convert_encoding($header, 'Windows-1252', 'UTF-8') : (function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $header) : $header);
                    fwrite($handle, $headerConverted);
                }
                $lineString = implode(';', $csvLine) . "\r\n"; 
                $lineConverted = function_exists('mb_convert_encoding') ? mb_convert_encoding($lineString, 'Windows-1252', 'UTF-8') : (function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $lineString) : $lineString);
                fwrite($handle, $lineConverted);
                fflush($handle);
                flock($handle, LOCK_UN);
                $csvSuccess = true;
            }
            fclose($handle);
        }

        if (!$csvSuccess) {
            // Notfall-CSV-Schreibversuch
            $fbHandle = @fopen($csvFolder . 'bestellungen_fallback.csv', 'a');
            if ($fbHandle) {
                $lineString = implode(';', $csvLine) . "\r\n";
                fwrite($fbHandle, $lineString);
                fclose($fbHandle);
            }
            file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - FEHLER: Konnte nicht in $fullPath schreiben! In bestellungen_fallback.csv gesichert.\n", FILE_APPEND);
        }

        // In orders.json speichern inkl. Status & Drop-Zuweisung
        $activeDrop = get_active_drop();

        $newOrder = [
            'id' => uniqid('ord_'),
            'paypal_id' => $paypalOrderId,
            'paypal_capture_id' => $paypalCaptureId,
            'date' => date('d.m.Y H:i'),
            'drop_id' => $activeDrop ? $activeDrop['id'] : null,
            'drop_name' => $activeDrop ? $activeDrop['name'] : null,
            'name' => $name,
            'strasse' => $kundenStrasse,
            'hausnummer' => $kundenHausnummer,
            'plz' => $plz,
            'ort' => $ort,
            'land' => $postLand,
            'email' => $email,
            'artikel' => trim($emailItemsList),
            'referenz' => trim($etikettReferenz),
            'gesamt' => $fmtTotal,
            'total_numeric' => $total,
            'subtotal_numeric' => $subtotal,
            'shipping_numeric' => $versand,
            'shipping_tier' => $shippingInfo['label'],
            'items' => $orderStructuredItems,
            'status' => $orderStatus
        ];

        // Zentrale Speicherung mit atomarem Lock und CHMOD
        $orderSaved = add_order($newOrder);
        if ($orderSaved) {
            file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - orders.json: Bestellung {$newOrder['id']} erfolgreich im Dashboard hinterlegt.\n", FILE_APPEND);
        } else {
            file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - WARNUNG: add_order fehlgeschlagen! Versuche Fallback...\n", FILE_APPEND);
            $ordersFile = $csvFolder . 'orders.json';
            $allOrders = file_exists($ordersFile) ? json_decode(file_get_contents($ordersFile), true) : [];
            if (!is_array($allOrders)) $allOrders = [];
            $allOrders[] = $newOrder;
            $orderSaved = (@file_put_contents($ordersFile, json_encode($allOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false);
            @chmod($ordersFile, 0666);
        }

        if (!$orderSaved) {
            file_put_contents($logFolder . 'emergency_orders.log', date('Y-m-d H:i:s') . " - NOTFALL: " . json_encode($newOrder, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - KRITISCH: Bestellung {$newOrder['id']} konnte nicht in orders.json geschrieben werden! In emergency_orders.log gesichert.\n", FILE_APPEND);
        }

        // Produktionsübersicht je Produkt aktualisieren (mit File-Locking)
        foreach ($productionByProduct as $pId => $orderedSizes) {
            $prod = $PRODUCTS[$pId];
            $csvSuffix = $prod['csv_suffix'] ?? $pId;
            $prodFile = $csvFolder . 'produktion_' . $csvSuffix . '.csv';

            $existingSizes = $defaultSizes;

            if (file_exists($prodFile)) {
                if (($h = fopen($prodFile, "r")) !== FALSE) {
                    fgetcsv($h, 1000, ";"); 
                    while (($data = fgetcsv($h, 1000, ";")) !== FALSE) {
                        if (count($data) == 2 && isset($existingSizes[$data[0]])) {
                            $existingSizes[$data[0]] = (int)$data[1];
                        }
                    }
                    fclose($h);
                }
            }

            $h = @fopen($prodFile, 'w');
            if ($h) {
                if (flock($h, LOCK_EX)) {
                    fwrite($h, "Groesse;Gesamtmenge\r\n");
                    foreach ($orderedSizes as $s => $q) {
                        $total_q = ($existingSizes[$s] ?? 0) + $q;
                        fwrite($h, "{$s};{$total_q}\r\n");
                    }
                    fflush($h);
                    flock($h, LOCK_UN);
                }
                fclose($h);
            }
        }

        // Dynamisches Bestandsverwaltungssystem: Gekaufte Menge direkt vom Restbestand in stock.json abziehen & Überbuchung prüfen
        require_once __DIR__ . '/stock_helper.php';
        $hasOverbooking = false;
        foreach ($orderStructuredItems as $oItem) {
            $pId = $oItem['product_id'];
            $boughtPacks = (int)$oItem['qty'];
            $prod = $PRODUCTS[$pId] ?? [];
            $qtyStep = isset($prod['qty_step']) ? max(1, (int)$prod['qty_step']) : 50;
            $piecesToDeduct = $boughtPacks * $qtyStep;
            
            $currentStockBefore = stock_get($pId);
            if ($currentStockBefore < $piecesToDeduct) {
                $hasOverbooking = true;
                $overMsg = "WARNUNG: Artikel {$pId} wurde überbucht! Verfügbar vor Kauf: {$currentStockBefore} Stk., Gekauft: {$piecesToDeduct} Stk.\n";
                file_put_contents($logFolder . 'checkout_errors.log', date('Y-m-d H:i:s') . " - " . $overMsg, FILE_APPEND);
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - " . $overMsg, FILE_APPEND);
            }

            $deducted = stock_deduct($pId, $piecesToDeduct);
            if ($deducted) {
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - stock.json: {$piecesToDeduct} Stück ({$boughtPacks} Packs) für {$pId} erfolgreich dekrementiert.\n", FILE_APPEND);
            } else {
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - stock.json HINWEIS: Abzug für {$pId} ({$piecesToDeduct} Stück).\n", FILE_APPEND);
            }
        }
        if ($hasOverbooking && $orderStatus === 'offen') {
            $newOrder['overbooked'] = true;
            $newOrder['status'] = 'offen_ueberbucht';
            add_order($newOrder); // Aktualisieren
        }

        // D. HTML E-Mails + getrennte try-catch Blöcke (B. Fail-Safe)
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {

            // Daten für professionelle Neo-Brutalismus Mail-Templates strukturieren
            $preparedEmailItems = [];
            foreach ($orderStructuredItems as $oItem) {
                $pId = $oItem['product_id'];
                $prod = $PRODUCTS[$pId] ?? [];
                $qtyStep = isset($prod['qty_step']) ? (int)$prod['qty_step'] : 1;
                $qtyUnit = isset($prod['qty_unit']) ? trim($prod['qty_unit']) : ' Stk.';
                $prefix = $prod['ref_prefix'] ?? '';
                if (empty($prefix)) {
                    if ($pId === 'sticker1') $prefix = 'Waldstadion';
                    elseif ($pId === 'sticker2') $prefix = 'RebellingKids';
                    elseif ($pId === 'sticker3') $prefix = 'Schwenker';
                    else $prefix = strtoupper(substr((string)$pId, 0, 1));
                }
                
                $qty = (int)$oItem['qty'];
                $stkTotal = $qty * $qtyStep;
                $packDisplay = ($qtyStep > 1) 
                    ? "{$qty} Pack ({$stkTotal}{$qtyUnit})" 
                    : "{$qty} Stück";
                
                $itemSize = !empty($oItem['size']) ? $oItem['size'] : '';
                if (!empty($itemSize) && $itemSize !== 'STD') {
                    $refItem = (strlen($prefix) === 1) ? "{$qty}{$prefix}-{$itemSize}" : "{$qty}-{$prefix}-{$itemSize}";
                } else {
                    $refItem = "{$qty}-{$prefix}";
                }

                $preparedEmailItems[] = [
                    'product_id'   => $pId,
                    'title'        => $oItem['title'],
                    'size'         => $oItem['size'],
                    'qty'          => $qty,
                    'pack_display' => $packDisplay,
                    'total_units'  => $stkTotal,
                    'price_unit'   => number_format($oItem['price'], 2, ',', '.') . ' €',
                    'price_total'  => number_format($qty * $oItem['price'], 2, ',', '.') . ' €',
                    'ref'          => $refItem
                ];
            }

            $mailData = [
                'order_id'       => $newOrder['id'] ?? 'ord_' . uniqid(),
                'date'           => $newOrder['date'] ?? date('d.m.Y H:i'),
                'paypal_id'      => $paypalOrderId,
                'name'           => $name,
                'vorname'        => $vorname,
                'strasse'        => $kundenStrasse,
                'hausnummer'     => $kundenHausnummer,
                'plz'            => $plz,
                'ort'            => $ort,
                'land'           => $finalesLand,
                'post_land'      => $postLand,
                'email'          => $email,
                'items'          => $preparedEmailItems,
                'has_free_poster'=> in_array("X-PLAKAT", $refParts),
                'total_packs'    => $totalPacks,
                'ref_string'     => $etikettReferenz,
                'subtotal'       => $fmtSubtotal,
                'discount'       => ($discount > 0) ? $fmtDiscount : null,
                'coupon_code'    => $coupon_code,
                'shipping_label' => $shippingInfo['label'],
                'shipping_price' => $fmtVersand,
                'total'          => $fmtTotal,
                'target_file'    => $targetFile,
                'weight'         => $gewicht
            ];

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'eintrachtfrankfurt.klebekunst@gmail.com';
            $mail->Password   = 'xldv cfrg lhkv rkay';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;
            $mail->Timelimit  = 10;
            $mail->isHTML(true);
            $mail->setFrom('eintrachtfrankfurt.klebekunst@gmail.com', 'Klebekunst Shop');

            // B. Inhaber-Mail (Packzettel & Logistik) — eigener try-catch
            try {
                $mail->clearAllRecipients();
                $mail->clearAddresses();
                $mail->clearReplyTos();
                $mail->clearAttachments();
                $mail->clearCustomHeaders();
                $mail->addAddress('eintrachtfrankfurt.klebekunst@gmail.com');
                $mail->addReplyTo($email, $name);
                $mail->Subject = $adminSubjectPrefix . "Packzettel: {$fmtTotal} von {$name} (#{$mailData['order_id']})";

                $bodyAdmin    = generate_owner_email_html($mailData);
                $altBodyAdmin = generate_owner_email_text($mailData);

                $mail->Body    = $bodyAdmin;
                $mail->AltBody = $altBodyAdmin;
                $mail->send();
                file_put_contents($logFolder . 'orders.log',
                    date('Y-m-d H:i:s') . " - Admin-Packzettel OK an eintrachtfrankfurt.klebekunst@gmail.com\n",
                    FILE_APPEND
                );
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Admin-Packzettel erfolgreich versendet.\n", FILE_APPEND);
            } catch (\Throwable $e) {
                // Bestellung ist gespeichert – Mail-Fehler nur loggen, nicht abbrechen
                file_put_contents($logFolder . 'mail_errors.log',
                    date('Y-m-d H:i:s') . " - Admin-Mail FEHLER: " . $mail->ErrorInfo . " / " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Admin-Mail FEHLER: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            }

            // B. Kunden-Mail (Bestellbestätigung) — eigener try-catch
            try {
                $mail->clearAllRecipients();
                $mail->clearAddresses();
                $mail->clearReplyTos();
                $mail->clearAttachments();
                $mail->clearCustomHeaders();
                $mail->addAddress($email, $name);
                $mail->addReplyTo('eintrachtfrankfurt.klebekunst@gmail.com', 'Klebekunst Shop');
                $mail->Subject = "Bestellbestätigung - KLEBEKUNST (#{$mailData['order_id']})";

                $bodyCustomer    = generate_customer_email_html($mailData);
                $altBodyCustomer = generate_customer_email_text($mailData);

                $mail->Body    = $bodyCustomer;
                $mail->AltBody = $altBodyCustomer;
                $mail->send();
                file_put_contents($logFolder . 'orders.log',
                    date('Y-m-d H:i:s') . " - Kunden-Bestellbestätigung OK an $email\n",
                    FILE_APPEND
                );
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Kunden-Bestellbestätigung erfolgreich versendet.\n", FILE_APPEND);
            } catch (\Throwable $e) {
                file_put_contents($logFolder . 'mail_errors.log',
                    date('Y-m-d H:i:s') . " - Kunden-Mail FEHLER: " . $mail->ErrorInfo . " / " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Kunden-Mail FEHLER: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            }
        } else {
            file_put_contents($logFolder . 'mail_errors.log',
                date('Y-m-d H:i:s') . " - WARNUNG: PHPMailer nicht installiert (vendor/ fehlt). Keine E-Mails versendet. Bestellung wurde trotzdem gespeichert.\n",
                FILE_APPEND
            );
            file_put_contents($debugLogFile,
                date('Y-m-d H:i:s') . " - WARNUNG: PHPMailer nicht verfügbar. E-Mails übersprungen, Bestellung dennoch gesichert.\n",
                FILE_APPEND
            );
        }
    } // Ende if (!empty($orderStructuredItems) && ($subtotal > 0 || $totalPacks > 0))

    // Gutschein entwertet (mit atomarem Lock)
    if (!empty($coupon_code) && isset($coupons[$coupon_code])) {
        if (file_exists($couponsFile)) {
            $latestCoupons = json_decode(@file_get_contents($couponsFile), true);
            if (is_array($latestCoupons) && isset($latestCoupons[$coupon_code])) {
                unset($latestCoupons[$coupon_code]);
                @file_put_contents($couponsFile, json_encode($latestCoupons, JSON_PRETTY_PRINT), LOCK_EX);
                file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Gutschein {$coupon_code} entwertet.\n", FILE_APPEND);
            }
        }
    }
    
    unset($_SESSION['cart']);
    // Sicherstellen, dass Session-Aktivität und Shop-Zugang nach der Bestellung aktiv bleiben
    $_SESSION['drop_access'] = true;
    $_SESSION['last_activity'] = time();
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Warenkorb geleert. Zeige Bestätigungsseite an.\n==================================================\n\n", FILE_APPEND);

    // Brutalistische Erfolgsseite anzeigen
    render_brutal_success(
        'BESTELLUNG ERFOLGREICH!',
        'Vielen Dank für deine Bestellung, ' . htmlspecialchars($vorname) . '! Deine Bestellung ist fest gebucht.',
        [
            'Bestell-ID'    => $newOrder['id'] ?? 'ord_' . uniqid(),
            'PayPal-ID'     => $paypalOrderId,
            'Gesamtbetrag'  => $fmtTotal,
            'Versand'       => $shippingInfo['label'] . ' (' . $fmtVersand . ')',
            'Lieferadresse' => $strasse . ', ' . $plz . ' ' . $ort . ' (' . $finalesLand . ')',
            'E-Mail'        => $email
        ]
    );
} else {
    header("Location: shop.php");
    exit;
}
?>