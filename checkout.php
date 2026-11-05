<?php
session_name('KLEBEKUNST_SESSION');
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

require_once __DIR__ . '/products.php';
require_once __DIR__ . '/stock_helper.php';
$PRODUCTS = $products;

if (isset($_GET['remove'])) {
    // Nur alphanumerische Zeichen, Bindestrich und Unterstrich erlauben
    $removeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_GET['remove']);
    if ($removeKey !== '' && isset($_SESSION['cart'][$removeKey])) {
        unset($_SESSION['cart'][$removeKey]);
        header("Location: checkout.php");
        exit;
    }
}

if (!isset($_SESSION['drop_access']) || $_SESSION['drop_access'] !== true) {
    header("Location: index.php");
    exit;
}

$timeout = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
} else {
    $_SESSION['last_activity'] = time();
}

$cartTotalItems = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
if ($cartTotalItems === 0) {
    header("Location: shop.php");
    exit;
}

$productTotals = [];
$cartDisplayList = [];

foreach ($_SESSION['cart'] as $cartKey => $qty) {
    $baseId = null;
    $size = '';
    
    $baseId = resolve_product_base_id($cartKey, $PRODUCTS);
    $size = '';
    if ($baseId !== null) {
        $realId = isset($PRODUCTS[$baseId]['id']) ? (string)$PRODUCTS[$baseId]['id'] : (string)$baseId;
        $size = ($cartKey === $realId) ? '' : substr($cartKey, strlen($realId) + 1);
    } else {
        continue;
    }

    if (!isset($productTotals[$baseId])) {
        $productTotals[$baseId] = 0;
    }
    $productTotals[$baseId] += $qty;

    if ($qty > 0 && isset($PRODUCTS[$baseId])) {
        $prod = $PRODUCTS[$baseId];
        $qtyStep = isset($prod['qty_step']) ? max(1, (int)$prod['qty_step']) : 50;
        $availablePacks = stock_get_available_packs($baseId, $qtyStep);

        // Überbuchungsschutz im Checkout: Ausverkaufte entfernen, Überschuss kappen
        if ($availablePacks <= 0) {
            unset($_SESSION['cart'][$cartKey]);
            continue;
        }
        if ($qty > $availablePacks) {
            $qty = $availablePacks;
            $_SESSION['cart'][$cartKey] = $qty;
        }

        $priceRaw = isset($prod['price']) ? (string)$prod['price'] : '0';
        $priceClean = get_clean_price($priceRaw);
        
        $linePriceVal = $qty * $priceClean;
        $linePrice = number_format($linePriceVal, 2, ',', '.') . ' €';

        $sizeDisplay = ($size === '-' || empty($size)) ? '' : " ($size)";
        $qtyUnit = isset($prod['qty_unit']) ? trim($prod['qty_unit']) : 'x';
        
        if ($qtyStep > 1) {
            $displayQty = ($qty * $qtyStep) . ' ' . $qtyUnit;
        } else {
            $displayQty = $qty . 'x';
        }

        $cartDisplayList[$cartKey] = [
            'baseId'         => $baseId,
            'title'          => $prod['title'],
            'sizeDisplay'    => $sizeDisplay,
            'qty'            => $qty,
            'qtyStep'        => $qtyStep,
            'qtyUnit'        => $qtyUnit,
            'availablePacks' => $availablePacks,
            'priceClean'     => $priceClean,
            'linePrice'      => $linePrice
        ];
    }
}

// Falls alle Artikel im Warenkorb ausverkauft waren
if (empty($_SESSION['cart']) || empty($cartDisplayList)) {
    header("Location: shop.php?notice=sold_out");
    exit;
}

$cartTotalItems = array_sum($_SESSION['cart']);
$initSubtotal = 0;
foreach ($_SESSION['cart'] as $cKey => $cQty) {
    $bId = resolve_product_base_id($cKey, $PRODUCTS);
    if ($bId !== null && isset($PRODUCTS[$bId])) {
        $pClean = get_clean_price(isset($PRODUCTS[$bId]['price']) ? (string)$PRODUCTS[$bId]['price'] : '0');
        $initSubtotal += ($cQty * $pClean);
    }
}
$initShipping = calculate_shipping($cartTotalItems, 'Deutschland');
$initTotal = $initSubtotal + $initShipping['price'];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KLEBEKUNST | KASSE</title>
    
    <link rel="icon" type="image/png" href="images/favicon-black.png?v=2" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" href="images/favicon-white.png?v=2" media="(prefers-color-scheme: dark)">
    
    <style>
        @font-face { font-family: 'Klebekunst Headline'; src: url('fonts/Klebekunst-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Inter'; src: url('fonts/Inter-Variable.woff2') format('woff2-variations'), url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); font-weight: 100 900; font-style: normal; font-display: swap; }
        @font-face { font-family: 'KlebekunstStriche'; src: url('fonts/KlebekunstStriche-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        
        .euro { font-family: 'KlebekunstStriche', sans-serif !important; font-size: 1.3em !important; display: inline !important; }
        *, *:before, *:after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg-color: #f0f0e8; --black: #000000; --accent: #ff2a00; --border-thick: 4px solid var(--black); }

        body { color: var(--black); font-family: 'Inter', sans-serif; min-height: 100vh; position: relative; overflow-x: hidden; display: flex; flex-direction: column; overscroll-behavior: none; }
        html { scrollbar-width: auto; scrollbar-color: var(--black) var(--bg-color); overscroll-behavior: none; }
        ::-webkit-scrollbar { width: 18px; }
        ::-webkit-scrollbar-track { background: var(--bg-color); border-left: 4px solid var(--black); }
        ::-webkit-scrollbar-thumb { background: var(--black); border: 3px solid var(--bg-color); }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }

        #magnetic-grid { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 1; pointer-events: none; }

        .shop-nav { position: sticky; top: 0; left: 0; width: 100%; background: var(--bg-color); border-bottom: var(--border-thick); display: flex; justify-content: flex-end; align-items: center; padding: 15px 5vw; z-index: 100; box-shadow: 0px 8px 0px rgba(0,0,0,1), 0px -50vh 0px var(--bg-color); }
        .cart-btn { background: var(--accent); color: #fff; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; padding: 8px 20px; border: 3px solid var(--black); border-radius: 255px 15px 225px 5px / 15px 255px 5px 225px; text-decoration: none; box-shadow: 4px 4px 0px var(--black); transition: all 0.1s; cursor: pointer; }
        .cart-btn:hover { transform: translate(-2px, -2px) rotate(2deg); box-shadow: 6px 6px 0px var(--black); }
        .back-btn { background: var(--bg-color); color: var(--black); font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; padding: 8px 20px; border: 3px solid var(--black); border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; text-decoration: none; text-transform: uppercase; box-shadow: 4px 4px 0px var(--black); transition: all 0.1s; cursor: pointer; }
        .back-btn:hover { background: var(--black); color: var(--bg-color); box-shadow: 6px 6px 0px var(--accent); transform: translate(-2px, -2px) rotate(-1deg); }

        .nav-logo-center { position: absolute; left: 50%; top: 10px; transform: translateX(-50%); display: block; text-decoration: none; z-index: 150; max-width: 50vw; }
        .logo-image { height: 180px; width: auto; max-width: 100%; object-fit: contain; }

        .shop-header { padding: 40px 5vw 20px 5vw; position: relative; z-index: 10; }
        .headline-cutout { font-family: 'Klebekunst Headline', sans-serif; font-size: clamp(3rem, 10vw, 8rem); color: #ffffff; -webkit-text-stroke: 3px var(--black); text-shadow: 8px 8px 0 var(--accent); text-transform: uppercase; line-height: 0.9; display: inline-block; transform: rotate(-1.5deg); letter-spacing: 1px; }

        .checkout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; padding: 0 5vw 100px 5vw; max-width: 1400px; margin: 0 auto; position: relative; z-index: 10; flex: 1; align-items: start; }
        
        .action-box { position: relative; background-color: #fcfcfc; border: var(--border-thick); padding: 35px; box-shadow: 12px 12px 0px rgba(0,0,0,1); border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; }
        .action-box::before { content: ""; position: absolute; top: -15px; left: 50%; transform: translateX(-50%) rotate(2deg); width: 120px; height: 35px; background-color: #cccccc; background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px), repeating-linear-gradient(-45deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px); border-left: 3px dashed rgba(0,0,0,0.25); border-right: 3px dashed rgba(0,0,0,0.25); box-shadow: 2px 3px 4px rgba(0,0,0,0.2); z-index: 5; }

        .box-title { font-family: 'Klebekunst Headline', sans-serif; font-size: 2.2rem; border-bottom: 3px solid var(--black); padding-bottom: 10px; margin-bottom: 25px; text-transform: uppercase; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 900; text-transform: uppercase; margin-bottom: 8px; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group select { width: 100%; height: 50px; background: transparent; border: 3px solid var(--black); font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 800; padding: 0 15px; border-radius: 0; outline: none; transition: background 0.1s; }
        .form-group input:focus, .form-group select:focus { background: rgba(0,0,0,0.05); border-color: var(--accent); }
        .form-group select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml;utf8,<svg fill='%23ff2a00' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/><path d='M0 0h24v24H0z' fill='none'/></svg>"); background-repeat: no-repeat; background-position-x: 96%; background-position-y: 50%; }

        .flex-row { display: flex; gap: 15px; }

        .cart-list { list-style: none; margin-bottom: 20px; }
        .cart-list li { display: flex; justify-content: space-between; align-items: flex-start; padding: 14px 0; border-bottom: 2px dashed var(--black); font-size: 1.1rem; }
        .cart-list li a { color: var(--accent); font-size: 0.85rem; font-weight: 900; text-transform: uppercase; text-decoration: none; display: inline-block; margin-top: 4px; }
        .cart-list li a:hover { text-decoration: underline; }

        .checkout-qty-select { width: 100%; max-width: 220px; height: 38px; border: 2.5px solid var(--black); background-color: #fff; font-family: 'Inter', sans-serif; font-size: 0.9rem; font-weight: 800; padding: 0 32px 0 10px; outline: none; cursor: pointer; border-radius: 0; appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml;utf8,<svg fill='%23ff2a00' height='22' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/></svg>"); background-repeat: no-repeat; background-position: right 8px center; box-shadow: 3px 3px 0px var(--black); margin-top: 6px; margin-bottom: 6px; display: block; transition: all 0.1s; }
        .checkout-qty-select:focus { border-color: var(--accent); }

        .coupon-section { display: flex; gap: 10px; margin-bottom: 10px; margin-top: 20px; }
        .coupon-btn { background: var(--black); color: #fff; border: 3px solid var(--black); font-family: 'Klebekunst Headline', sans-serif; font-size: 1.5rem; padding: 0 20px; cursor: pointer; text-transform: uppercase; transition: background 0.1s; }
        .coupon-btn:hover { background: var(--accent); }

        .summary-row { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800; margin-bottom: 10px; text-transform: uppercase; }
        .summary-total { display: flex; justify-content: space-between; font-size: 1.5rem; font-weight: 900; border-top: 4px solid var(--black); padding-top: 15px; margin-top: 10px; text-transform: uppercase; }

        .disclaimer { font-size: 0.8rem; font-weight: bold; color: #555; margin-top: 10px; }
        .warning-box { margin-top: 25px; margin-bottom: 25px; padding: 15px; border: 3px dashed var(--accent); text-align: center; font-weight: 800; text-transform: uppercase; font-size: 0.9rem; background: #fff; }
        .warning-box span { color: var(--accent); display: block; font-size: 1.2rem; margin-bottom: 5px; }

        .brutal-footer { background: var(--black); color: var(--bg-color); border-top: var(--border-thick); padding: 30px 5vw; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10; font-family: 'Klebekunst Headline', sans-serif; text-transform: uppercase; margin-top: auto; box-shadow: 0px 10px 0px var(--black); }
        .brutal-footer .footer-brand { font-size: 2.5rem; transform: rotate(-2deg); }
        .brutal-footer .footer-links { display: flex; gap: 30px; }
        .brutal-footer a { color: var(--bg-color); text-decoration: none; font-size: 1.5rem; transition: color 0.1s; }
        .brutal-footer a:hover { color: var(--accent); text-decoration: underline; text-decoration-thickness: 3px; text-underline-offset: 4px; }

        @media (max-width: 950px) {
            .checkout-grid { grid-template-columns: 1fr; gap: 30px; padding-bottom: 60px; }
            .action-box { padding: 25px 20px; }
            .flex-row { flex-direction: column; gap: 0; }
            .brutal-footer { flex-direction: column; gap: 20px; text-align: center; padding: 30px 4vw 50px 4vw; }
            .brutal-footer .footer-links { flex-direction: column; gap: 15px; }
            
            .shop-nav { justify-content: space-between; padding: 12px 4vw; }
            .cart-btn, .back-btn { font-size: 1.3rem; padding: 6px 12px; border-width: 2px; box-shadow: 3px 3px 0px var(--black); }
            
            .nav-logo-center { position: static; transform: none; margin-right: auto; max-width: 45vw; }
            .logo-image { height: 60px; max-width: 100%; }
            
            .shop-header { padding: 30px 4vw 15px 4vw; }
            .headline-cutout { font-size: clamp(2.2rem, 11vw, 4rem); -webkit-text-stroke: 2px var(--black); text-shadow: 5px 5px 0 var(--accent); }
        }
    </style>
</head>
<body>

    <canvas id="magnetic-grid"></canvas>

    <nav class="shop-nav">
        <a href="index.php" class="nav-logo-center">
            <img src="images/LongLogo.png" class="logo-image" alt="KLEBEKUNST">
        </a>
        <a href="shop.php" class="back-btn">&lsaquo; ZURÜCK</a>
    </nav>

    <header class="shop-header">
        <h1 class="headline-cutout">KASSE</h1>
    </header>

    <form action="process.php" method="POST" id="order-form" novalidate style="display: contents;">
        
        <?php foreach ($productTotals as $pId => $qty): ?>
            <?php if(isset($PRODUCTS[$pId])): ?>
            <!-- ULTIMATIVER FIX: Das data-price Attribut ist jetzt IMMER eine rechenbare Punkt-Zahl (z.B. "5.00") -->
            <input type="hidden" name="Menge_<?php echo htmlspecialchars($pId); ?>" id="qty-<?php echo htmlspecialchars($pId); ?>" data-price="<?php echo get_clean_price(isset($PRODUCTS[$pId]['price']) ? (string)$PRODUCTS[$pId]['price'] : '0'); ?>" value="<?php echo $qty; ?>">
            <?php endif; ?>
        <?php endforeach; ?>

        <main class="checkout-grid">
            <div class="action-box" style="transform: rotate(-1deg);">
                <h2 class="box-title">LIEFERUNG</h2>
                
                <div class="form-group">
                    <label>Vor- und Nachname*</label>
                    <input type="text" name="Name" required>
                </div>
                <div class="form-group">
                    <label>Straße und Hausnummer*</label>
                    <input type="text" name="Strasse" required>
                </div>
                <div class="flex-row">
                    <div class="form-group" style="flex: 1;">
                        <label>PLZ*</label>
                        <input type="text" name="PLZ" required>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>Ort*</label>
                        <input type="text" name="Ort" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Land*</label>
                    <select name="Land" id="country-select" required>
                        <option value="Deutschland">Deutschland</option>
                        <option value="Österreich">Österreich</option>
                        <option value="Schweiz">Schweiz</option>
                        <option value="Anderes EU-Land">Anderes EU-Land</option>
                    </select>
                </div>
                <div class="form-group" id="other-country-group" style="display: none;">
                    <label>Welches Land?</label>
                    <input type="text" name="Spezifisches_EU_Land" id="other-country-input">
                </div>
                <div class="form-group">
                    <label>E-Mail*</label>
                    <input type="email" name="Kunden_Email" required>
                </div>
            </div>

            <div class="action-box" style="transform: rotate(1deg);">
                <h2 class="box-title">ÜBERSICHT</h2>
                
                <ul class="cart-list">
                    <?php foreach($cartDisplayList as $key => $item): ?>
                        <li id="cart-item-<?php echo htmlspecialchars($key); ?>">
                            <div style="flex: 1; padding-right: 15px;">
                                <strong style="display: block; font-size: 1.15rem;"><?php echo htmlspecialchars($item['title'] . $item['sizeDisplay']); ?></strong>
                                <select class="checkout-qty-select" 
                                        data-key="<?php echo htmlspecialchars($key); ?>" 
                                        data-base-id="<?php echo htmlspecialchars($item['baseId']); ?>"
                                        data-price="<?php echo $item['priceClean']; ?>"
                                        onchange="updateCheckoutQty('<?php echo htmlspecialchars($key); ?>', '<?php echo htmlspecialchars($item['baseId']); ?>', this.value, <?php echo $item['priceClean']; ?>)">
                                    <?php 
                                        $maxP = min(10, max(1, (int)($item['availablePacks'] ?? 10)));
                                        for($i = 1; $i <= $maxP; $i++): 
                                    ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($i === (int)$item['qty']) ? 'selected' : ''; ?>>
                                            <?php echo ($item['qtyStep'] > 1) ? "{$i} Pack (" . ($i * $item['qtyStep']) . " Stk.)" : "{$i} Stück"; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <?php if (isset($item['availablePacks']) && $item['availablePacks'] <= 5): ?>
                                    <div style="font-size:0.75rem; font-weight:800; color:var(--accent); text-transform:uppercase; margin-top:2px;">
                                        Nur noch <?php echo $item['availablePacks']; ?> Packungen verfügbar!
                                    </div>
                                <?php endif; ?>
                                <a href="checkout.php?remove=<?php echo urlencode($key); ?>">ENTFERNEN</a>
                            </div>
                            <strong id="line-price-<?php echo htmlspecialchars($key); ?>" style="font-size: 1.25rem; white-space: nowrap;"><?php echo $item['linePrice']; ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="coupon-section">
                    <input type="text" id="coupon-input" class="form-group" style="margin: 0; width: 100%; height: 50px; background: transparent; border: 3px solid var(--black); font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 800; padding: 0 15px; outline: none; text-transform: uppercase;" placeholder="RABATTCODE">
                    <button type="button" onclick="applyCoupon()" class="coupon-btn">OK</button>
                </div>
                <div id="coupon-message" style="margin-bottom: 15px; font-weight: 900; font-size: 0.9rem; display: none;"></div>
                <input type="hidden" name="coupon_code" id="hidden-coupon-code" value="">

                <div class="summary-row"><span>Warenwert:</span> <span><span id="display-subtotal"><?php echo number_format($initSubtotal, 2, ',', '.'); ?></span> €</span></div>
                <div class="summary-row" style="align-items: flex-start;">
                    <div>
                        <span id="shipping-label">Versand:</span>
                        <div id="shipping-tier-badge" style="font-size: 0.8rem; font-weight: 700; color: #555; text-transform: uppercase; margin-top: 2px;"><?php echo htmlspecialchars($initShipping['label']); ?></div>
                    </div>
                    <span><span id="display-shipping"><?php echo number_format($initShipping['price'], 2, ',', '.'); ?></span> €</span>
                </div>
                <div class="summary-row" id="rabatt-row" style="display: none; color: var(--accent);"><span>Rabatt:</span> <span>-<span id="display-discount">0,00</span> €</span></div>

                <div class="summary-total"><span>GESAMT:</span> <span><span id="display-total"><?php echo number_format($initTotal, 2, ',', '.'); ?></span> <span class="euro">€</span></span></div>
                
                <div class="shipping-info-box" style="margin-top: 15px; margin-bottom: 25px; padding: 12px 14px; border: 2px dashed var(--black); background: #f7f7f0; font-size: 0.8rem; line-height: 1.5;">
                    <strong style="text-transform: uppercase; display: block; margin-bottom: 5px; font-size: 0.85rem;">Übersicht Versandstaffel:</strong>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                        <span>1–4 Packungen (Großbrief):</span> <strong>DE 1,80 € / Ausland 3,30 €</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                        <span>5–9 Packungen (Maxibrief):</span> <strong>DE 2,90 € / Ausland 6,50 €</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Ab 10 Packungen (Päckchen):</span> <strong>DE 4,19 € / Ausland 10,49 €</strong>
                    </div>
                </div>

                <p style="margin-top: 15px; margin-bottom: 15px; font-size: 0.8rem; line-height: 1.4; color: #444; text-align: center; font-weight: 600;">
                    Mit deiner Bestellung erklärst du dich mit unserer <a href="datenschutz.php" target="_blank" style="color: var(--accent); font-weight: 800; text-decoration: underline;">Datenschutzerklärung</a> einverstanden.
                </p>

                <div id="paypal-button-container"></div>
                
                <button type="submit" id="free-checkout-btn" class="coupon-btn" style="width: 100%; height: 50px; display: none;">
                    KOSTENLOS BESTELLEN
                </button>
            </div>
        </main>
    </form>

    <footer class="brutal-footer">
        <div class="footer-brand">KLEBEKUNST</div>
        <div class="footer-links">
            <a href="impressum.php">Impressum</a>
            <a href="datenschutz.php">Datenschutz</a>
            <a href="https://instagram.com/1899klebekunst" target="_blank">Instagram</a>
        </div>
    </footer>

    <script src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>

    <script src="https://www.paypal.com/sdk/js?client-id=AQluAuIyN7OK_SWFhGaOClW7GA-A8vpx-V5iHxJdM9h-1-1TNiKgyMDOFxZrer124SIMDlc34I7tc7xI&currency=EUR&locale=de_DE&disable-funding=sepa,giropay,sofort"></script>
    <script>
    paypal.Buttons({
        style: { layout: 'vertical', color: 'black', shape: 'rect', label: 'paypal' },
        onClick: function(data, actions) {
            let form = document.getElementById('order-form');
            if (!form.checkValidity()) { 
                form.reportValidity(); 
                return actions.reject(); 
            }

            // Prüfen ob 'Anderes EU-Land' gewählt, aber kein spezifisches Land angegeben wurde
            let countrySel = document.getElementById('country-select');
            let otherInput = document.getElementById('other-country-input');
            if (countrySel && countrySel.value === 'Anderes EU-Land') {
                if (!otherInput || !otherInput.value.trim()) {
                    alert('Bitte gib dein genaues Lieferland ein.');
                    if (otherInput) {
                        otherInput.focus();
                        otherInput.style.borderColor = 'var(--accent)';
                    }
                    return actions.reject();
                }
            }

            let totalEl = document.getElementById('display-total');
            let rawText = totalEl ? totalEl.innerText : '0';
            let cleanNum = rawText.replace(/\./g, '').replace(',', '.').replace(/[^0-9.]/g, '').trim();
            let parsedVal = parseFloat(cleanNum);
            if (isNaN(parsedVal) || parsedVal <= 0) {
                alert('Der Betrag ist 0,00 € oder ungültig. Bitte nutze den Button "Kostenlos bestellen".');
                return actions.reject();
            }

            return actions.resolve();
        },
        createOrder: function(data, actions) {
            let totalEl = document.getElementById('display-total');
            let rawText = totalEl ? totalEl.innerText : '0';
            // Bereinigen von Tausenderpunkten und Formatierung mit Dezimalpunkt (z.B. '12.50')
            let cleanNum = rawText.replace(/\./g, '').replace(',', '.').replace(/[^0-9.]/g, '').trim();
            let parsedVal = parseFloat(cleanNum);
            let totalAmount = (!isNaN(parsedVal) && parsedVal > 0) ? parsedVal.toFixed(2) : '0.00';
            
            if (parseFloat(totalAmount) <= 0) {
                console.error('Ungültiger Zahlbetrag für PayPal:', totalAmount);
                throw new Error('Der Zahlbetrag muss größer als 0 sein.');
            }

            console.log('PayPal createOrder -> Betrag:', totalAmount, 'EUR');

            return actions.order.create({
                intent: 'CAPTURE',
                purchase_units: [{ 
                    amount: { 
                        currency_code: 'EUR',
                        value: totalAmount 
                    } 
                }],
                application_context: { shipping_preference: "NO_SHIPPING" }
            });
        },
        onApprove: async function(data, actions) {
            let overlay = null;
            try {
                console.log('PayPal onApprove empfangen, orderID:', data.orderID);
                // Transaktion erfassen (capture)
                let details = await actions.order.capture();
                console.log('PayPal Capture erfolgreich:', details);

                let captureObj = details?.purchase_units?.[0]?.payments?.captures?.[0];
                let captureStatus = captureObj?.status || details?.status || '';

                if (captureStatus === 'DECLINED' || captureStatus === 'FAILED') {
                    throw new Error('Die Zahlung wurde von PayPal oder der Bank abgelehnt (Status: ' + captureStatus + '). Bitte wähle ein anderes Zahlungsmittel.');
                }

                // UI einfrieren – Nutzer am Schließen hindern
                overlay = document.createElement('div');
                overlay.id = 'payment-freeze-overlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(240,240,232,0.97);z-index:99999;display:flex;flex-direction:column;justify-content:center;align-items:center;';
                overlay.innerHTML = '<h2 style="font-family:\'Klebekunst Headline\',sans-serif;font-size:clamp(2rem,6vw,3.5rem);text-align:center;margin:0 20px;">ZAHLUNG ERFOLGREICH!</h2>'
                    + '<p style="font-size:1.1rem;font-weight:800;text-align:center;max-width:80vw;margin-top:15px;">Bestellung wird gesichert &ndash; bitte warten.<br><span style="color:#ff2a00;">Fenster NICHT schlie&szlig;en!</span></p>';
                document.body.appendChild(overlay);

                // PayPal-Order-ID, Capture-ID und erfassten Zahlbetrag an process.php übergeben
                let form = document.getElementById('order-form');
                
                let inputOrderId = document.createElement('input');
                inputOrderId.type = 'hidden';
                inputOrderId.name = 'paypal_order_id';
                inputOrderId.value = data.orderID;
                form.appendChild(inputOrderId);

                let captureId = captureObj?.id || '';
                if (captureId) {
                    let inputCapId = document.createElement('input');
                    inputCapId.type = 'hidden';
                    inputCapId.name = 'paypal_capture_id';
                    inputCapId.value = captureId;
                    form.appendChild(inputCapId);
                }

                let capturedVal = captureObj?.amount?.value || details?.purchase_units?.[0]?.amount?.value || '';
                let inputAmount = document.createElement('input');
                inputAmount.type = 'hidden';
                inputAmount.name = 'paypal_amount';
                inputAmount.value = capturedVal;
                form.appendChild(inputAmount);

                let inputStatus = document.createElement('input');
                inputStatus.type = 'hidden';
                inputStatus.name = 'paypal_status';
                inputStatus.value = captureStatus || 'COMPLETED';
                form.appendChild(inputStatus);

                form.submit();

            } catch(err) {
                console.error('PayPal onApprove / Capture Fehler:', err);
                if (overlay && overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
                let errString = String(err?.message || (typeof err === 'object' ? JSON.stringify(err) : err));

                // INSTRUMENT_DECLINED: Nutzer kann ein anderes Zahlungsmittel im Popup wählen
                if (errString.includes('INSTRUMENT_DECLINED')) {
                    alert('Deine ausgewählte Zahlungsmethode wurde von PayPal abgelehnt. Bitte wähle ein anderes Zahlungsmittel.');
                    return actions.restart();
                }

                if (errString.includes('permission_denied') || errString.includes('Insufficient privileges')) {
                    alert('PayPal-Zahlung abgelehnt: Fehlende Berechtigung (permission_denied / Insufficient privileges).\n\n'
                        + 'MÖGLICHE URSACHEN:\n'
                        + '1. EIGENKAUF BLOCKIERT: Du hast versucht, den Testkauf mit demselben PayPal-Konto zu bezahlen, das als Shop-Empfänger hinterlegt ist. PayPal verbietet Zahlungen an sich selbst!\n'
                        + '2. KONTO-EINSCHRÄNKUNG: Dein PayPal-Geschäftskonto verlangt eine Identitäts- oder Datenbestätigung (bitte auf paypal.com unter Benachrichtigungen/Glocke prüfen).\n\n'
                        + 'Technisches Detail: ' + errString);
                } else {
                    alert('Zahlung fehlgeschlagen oder Verbindung unterbrochen.\n\nFehler: ' + errString + '\n\nFalls bereits Geld abgebucht wurde, kontaktiere uns bitte sofort per Instagram (@1899klebekunst) oder E-Mail: eintrachtfrankfurt.klebekunst@gmail.com');
                }
            }
        },
        onCancel: function(data) {
            console.log('PayPal Zahlungsvorgang durch Nutzer abgebrochen:', data);
        },
        onError: function(err) {
            console.error('PayPal Buttons onError:', err);
            alert('Technisches Problem bei PayPal: ' + (err.message || err) + '\n\nBitte versuche es erneut oder lade die Seite neu.');
        }
    }).render('#paypal-button-container');

    // Validierung und Doppel-Klick-Schutz bei Formular-Absendung (z.B. Kostenlose Bestellung)
    const orderForm = document.getElementById('order-form');
    if (orderForm) {
        orderForm.addEventListener('submit', function(e) {
            let countrySel = document.getElementById('country-select');
            let otherInput = document.getElementById('other-country-input');
            if (countrySel && countrySel.value === 'Anderes EU-Land') {
                if (!otherInput || !otherInput.value.trim()) {
                    e.preventDefault();
                    alert('Bitte gib dein genaues Lieferland ein.');
                    if (otherInput) {
                        otherInput.focus();
                        otherInput.style.borderColor = 'var(--accent)';
                    }
                    return false;
                }
            }

            if (!orderForm.checkValidity()) {
                e.preventDefault();
                orderForm.reportValidity();
                return false;
            }

            let freeBtn = document.getElementById('free-checkout-btn');
            if (freeBtn && freeBtn.style.display !== 'none') {
                freeBtn.disabled = true;
                freeBtn.innerText = 'WIRD VERARBEITET...';
            }
        });
    }
    </script>
    <script src="canvas.js"></script>
</body>
</html>