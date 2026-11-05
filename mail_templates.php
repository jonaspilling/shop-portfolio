<?php
/**
 * mail_templates.php - Hochwertige Neo-Brutalismus HTML- & Plaintext-Templates
 * für Kunden-Bestellbestätigung und Inhaber-Packzettel.
 */

/**
 * 1. KUNDEN-BESTELLBESTÄTIGUNG (HTML)
 * Fokus: Kundenerlebnis, Vertrauen, Übersichtlichkeit, Neo-Brutalismus
 */
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
                                alles bestens, deine Bestellung und die Zahlung sind bei mir angekommen! Ich mache deine Aufkleber jetzt schnellstmöglich fertig und schicke sie los.
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

/**
 * 1b. KUNDEN-BESTELLBESTÄTIGUNG (PLAIN TEXT FALLBACK)
 */
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

/**
 * 2. INHABER-BENACHRICHTIGUNG (HTML)
 * Fokus: Packzettel & Logistik, schnelles Abpacken, direkte Frankieranweisung
 */
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

/**
 * 2b. INHABER-BENACHRICHTIGUNG (PLAIN TEXT FALLBACK)
 */
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
