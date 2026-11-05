<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logFolder = __DIR__ . '/logs/';
        if (!is_dir($logFolder)) @mkdir($logFolder, 0775, true);
        $msg = date('Y-m-d H:i:s') . " - FATAL SHOP ERROR: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
        @file_put_contents($logFolder . 'system_errors.log', $msg, FILE_APPEND);
    }
});

session_name('KLEBEKUNST_SESSION');
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

require_once __DIR__ . '/settings_helper.php';
$shopSettings = get_shop_settings();
if (!empty($shopSettings['shop_open'])) {
    $_SESSION['drop_access'] = true;
}

if (!isset($_SESSION['drop_access']) || $_SESSION['drop_access'] !== true) {
    header("Location: index.php");
    exit;
}

$_SESSION['last_activity'] = time();
$cartTotalItems = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

require_once __DIR__ . '/products.php';
require_once __DIR__ . '/stock_helper.php';
$allStocks = stock_get_all();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KLEBEKUNST | STOFF</title>
    
    <link rel="icon" type="image/png" href="images/favicon-black.png?v=2" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" href="images/favicon-white.png?v=2" media="(prefers-color-scheme: dark)">
    
    <style>
        @font-face { font-family: 'Klebekunst Headline'; src: url('fonts/Klebekunst-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Inter'; src: url('fonts/Inter-Variable.woff2') format('woff2-variations'), url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); font-weight: 100 900; font-style: normal; font-display: swap; }
        @font-face { font-family: 'KlebekunstStriche'; src: url('fonts/KlebekunstStriche-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        
        .euro { font-family: 'KlebekunstStriche', sans-serif !important; font-size: 1.3em !important; display: inline !important; }
        *, *:before, *:after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg-color: #f0f0e8; --black: #000000; --accent: #ff2a00; --border-thick: 4px solid var(--black); }

        html, body { width: 100%; max-width: 100vw; margin: 0; padding: 0; overflow-x: hidden; }
        body { color: var(--black); font-family: 'Inter', sans-serif; min-height: 100vh; position: relative; display: flex; flex-direction: column; }
        html { scrollbar-width: auto; scrollbar-color: var(--black) var(--bg-color); }
        ::-webkit-scrollbar { width: 18px; }
        ::-webkit-scrollbar-track { background: var(--bg-color); border-left: 4px solid var(--black); }
        ::-webkit-scrollbar-thumb { background: var(--black); border: 3px solid var(--bg-color); }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }

        #magnetic-grid { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 1; pointer-events: none; }

        .shop-nav { position: sticky; top: 0; left: 0; width: 100%; background: var(--bg-color); border-bottom: var(--border-thick); display: flex; justify-content: flex-end; align-items: center; padding: 15px 5vw; z-index: 100; box-shadow: 0px 8px 0px rgba(0,0,0,1), 0px -50vh 0px var(--bg-color); }
        .cart-btn { background: var(--accent); color: #fff; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; padding: 8px 20px; border: 3px solid var(--black); border-radius: 255px 15px 225px 5px / 15px 255px 5px 225px; text-decoration: none; box-shadow: 4px 4px 0px var(--black); transition: all 0.1s; cursor: pointer; }
        .cart-btn:hover { transform: translate(-2px, -2px) rotate(2deg); box-shadow: 6px 6px 0px var(--black); }

        .nav-logo-center { position: absolute; left: 50%; top: 10px; transform: translateX(-50%); display: block; text-decoration: none; z-index: 150; max-width: 50vw; }
        .logo-image { height: 180px; width: auto; max-width: 100%; object-fit: contain; }

        #cart-drawer { position: fixed; top: 0; right: -100%; width: 100%; max-width: 420px; height: 100vh; height: 100dvh; background: var(--bg-color); border-left: var(--border-thick); z-index: 1000; box-shadow: none; transition: right 0.3s ease-in-out, box-shadow 0.3s ease-in-out; display: flex; flex-direction: column; padding: 25px; padding-bottom: calc(25px + env(safe-area-inset-bottom)); overscroll-behavior: contain; }
        #cart-drawer.open { right: 0; box-shadow: -15px 0px 0px rgba(0,0,0,0.8); }
        .drawer-header { display: flex; justify-content: space-between; align-items: center; font-family: 'Klebekunst Headline', sans-serif; font-size: 2.2rem; border-bottom: 3px solid var(--black); padding-bottom: 10px; margin-bottom: 20px; }
        .drawer-close { background: none; border: none; font-size: 2rem; cursor: pointer; font-family: 'Klebekunst Headline', sans-serif; color: var(--black); }
        .drawer-close:hover { color: var(--accent); }
        
        .drawer-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; overscroll-behavior: contain; }
        .drawer-item { background: #fff; border: 3px solid var(--black); padding: 12px; display: flex; flex-direction: column; gap: 10px; box-shadow: 4px 4px 0px var(--black); }
        .drawer-item-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .drawer-item-info { font-size: 0.95rem; font-weight: 800; }
        .drawer-item-info span { display: block; font-size: 0.8rem; font-weight: normal; opacity: 0.7; }
        .remove-cart-item { background: var(--accent); color: #fff; border: 2px solid var(--black); padding: 4px 10px; font-weight: 900; cursor: pointer; font-family: 'Klebekunst Headline', sans-serif; transition: all 0.1s; }
        .remove-cart-item:active { transform: translate(2px, 2px); box-shadow: none; }
        
        .drawer-qty-select { width: 100%; height: 38px; border: 2px solid var(--black); font-family: 'Inter', sans-serif; font-size: 0.9rem; font-weight: 800; appearance: none; -webkit-appearance: none; background-color: var(--bg-color); background-image: url("data:image/svg+xml;utf8,<svg fill='%23ff2a00' height='20' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/></svg>"); background-repeat: no-repeat; background-position-x: 96%; background-position-y: 50%; cursor: pointer; outline: none; padding: 0 10px; }

        .drawer-footer { border-top: 3px solid var(--black); padding-top: 20px; margin-top: auto; display: flex; flex-direction: column; gap: 15px; }
        .drawer-total { font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; display: flex; justify-content: space-between; }
        .checkout-go-btn { background: var(--black); color: #fff; text-align: center; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; padding: 12px; border: 3px solid var(--black); text-decoration: none; box-shadow: 4px 4px 0px var(--accent); text-transform: uppercase; display: block; transition: all 0.1s; }
        .checkout-go-btn:hover { background: var(--accent); color: #fff; }

        .shop-header { padding: 60px 5vw 40px 5vw; position: relative; z-index: 10; }
        .headline-cutout { font-family: 'Klebekunst Headline', sans-serif; font-size: clamp(3rem, 10vw, 8rem); color: #ffffff; -webkit-text-stroke: 3px var(--black); text-shadow: 8px 8px 0 var(--accent); text-transform: uppercase; line-height: 0.9; display: inline-block; transform: rotate(-1.5deg); letter-spacing: 1px; }

        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 50px; padding: 0 5vw 100px 5vw; max-width: 1600px; margin: 0 auto; position: relative; z-index: 10; flex: 1; align-items: start; }
        .product-card { position: relative; background: #ffffff; border: var(--border-thick); padding: 22px; box-shadow: 12px 12px 0px rgba(0,0,0,1); display: flex; flex-direction: column; gap: 15px; transition: all 0.15s ease-out; border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; }
        .product-card:nth-child(odd) { transform: rotate(-1.2deg); }
        .product-card:nth-child(even) { transform: rotate(1.8deg); }
        .product-card::before { content: ""; position: absolute; top: -20px; left: 45%; transform: translateX(-50%) rotate(-4deg); width: 90px; height: 30px; background-color: #cccccc; background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px), repeating-linear-gradient(-45deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px); border-left: 3px dashed rgba(0,0,0,0.25); border-right: 3px dashed rgba(0,0,0,0.25); box-shadow: 2px 3px 4px rgba(0,0,0,0.2); z-index: 5; }
        .product-card::after { content: ""; position: absolute; top: -15px; left: 55%; transform: translateX(-50%) rotate(5deg); width: 80px; height: 25px; background-color: #b8b8b8; background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px), repeating-linear-gradient(-45deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px); border-left: 2px dashed rgba(0,0,0,0.3); border-right: 2px dashed rgba(0,0,0,0.3); box-shadow: 1px 2px 3px rgba(0,0,0,0.3); z-index: 6; }
        .product-image-wrapper { width: 100%; aspect-ratio: 1 / 1; border: 3px solid var(--black); overflow: hidden; background: #f4f4f4; position: relative; }
        .product-image { width: 100%; height: 100%; object-fit: cover; filter: contrast(110%); transition: transform 0.2s; }
        .product-card:hover .product-image { transform: scale(1.05); }

        .product-title { font-family: 'Klebekunst Headline', sans-serif; font-size: 2.2rem; text-transform: uppercase; margin-top: 8px; line-height: 0.95; }
        .product-meta { display: flex; align-items: center; gap: 8px; margin: 6px 0 2px 0; flex-wrap: wrap; }
        .product-type-badge { font-family: 'Inter', sans-serif; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; background: var(--black); color: #fff; padding: 2px 7px; border: 1.5px solid var(--black); }
        .product-size-info { font-family: 'Inter', sans-serif; font-size: 0.75rem; font-weight: 700; color: #555; border: 1.5px solid currentColor; padding: 2px 7px; }
        .product-price { font-family: monospace; font-size: 1.5rem; font-weight: bold; color: var(--accent); background: var(--black); display: inline-block; padding: 5px 12px; align-self: flex-start; transform: rotate(-2deg); }

        .product-options { display: flex; gap: 10px; width: 100%; }
        .brutal-select { flex: 1; height: 48px; border: 3px solid var(--black); background: transparent; font-family: 'Inter', sans-serif; font-weight: 800; font-size: 0.95rem; text-transform: uppercase; padding: 0 10px; cursor: pointer; outline: none; appearance: none; background-image: url("data:image/svg+xml;utf8,<svg fill='black' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/><path d='M0 0h24v24H0z' fill='none'/></svg>"); background-repeat: no-repeat; background-position-x: 94%; background-position-y: 50%; border-radius: 0; }

        .add-to-cart-btn { width: 100%; height: 55px; margin-top: auto; background: var(--black); color: #fff; border: 3px solid var(--black); font-family: 'Klebekunst Headline', sans-serif; font-size: 2rem; text-transform: uppercase; cursor: pointer; box-shadow: 4px 4px 0px rgba(0,0,0,0.3); transition: all 0.1s; }
        .add-to-cart-btn:hover:not(:disabled) { background: var(--accent); box-shadow: 6px 6px 0px var(--black); transform: translate(-2px, -2px); }
        .add-to-cart-btn:active:not(:disabled) { transform: translate(2px, 2px); box-shadow: 0px 0px 0px var(--black); }
        .add-to-cart-btn:disabled, .add-to-cart-btn.sold-out { background: var(--accent) !important; color: #ffffff !important; cursor: not-allowed !important; box-shadow: 3px 3px 0px var(--black) !important; transform: none !important; opacity: 0.95 !important; }
        .product-stock-badge { font-family: 'Inter', sans-serif; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 7px; border: 1.5px solid var(--black); }
        .product-stock-badge.sold-out { background: var(--accent); color: #fff; }
        .product-stock-badge.low-stock { background: #ffd700; color: #000; }

        .brutal-footer { background: var(--black); color: var(--bg-color); border-top: var(--border-thick); padding: 30px 5vw; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10; font-family: 'Klebekunst Headline', sans-serif; text-transform: uppercase; margin-top: auto; }
        .brutal-footer .footer-brand { font-size: 2.5rem; transform: rotate(-2deg); }
        .brutal-footer .footer-links { display: flex; gap: 30px; }
        .brutal-footer a { color: var(--bg-color); text-decoration: none; font-size: 1.5rem; transition: color 0.1s; }
        .brutal-footer a:hover { color: var(--accent); text-decoration: underline; text-decoration-thickness: 3px; text-underline-offset: 4px; }

        @media (max-width: 1200px) and (min-width: 768px) {
            html, body { background-color: var(--bg-color) !important; overflow-x: hidden; }
            body::after { display: none !important; }
            .shop-nav::before { display: none !important; }
            .shop-nav { justify-content: space-between; padding: 15px 4vw; }
            .nav-logo-center { position: static; transform: none; max-width: 40vw; }
            .logo-image { height: 70px; max-width: 100%; } 
            .cart-btn { font-size: 1.4rem; padding: 6px 15px; border-width: 3px; }
            .shop-header { padding: 35px 4vw 25px 4vw; }
            .headline-cutout { font-size: clamp(3rem, 7vw, 5rem); }
            .product-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; padding: 0 4vw 80px 4vw; align-items: start; }
            .product-card { padding: 18px; gap: 12px; margin: 0; }
            .product-title { font-size: 1.5rem; margin-top: 5px; line-height: 1; }
            .product-price { font-size: 1.2rem; padding: 4px 8px; }
            .brutal-select { height: 44px; font-size: 0.9rem; }
            .add-to-cart-btn { height: 50px; font-size: 1.5rem; }
        }

        @media (max-width: 767px) {
            html { background-color: var(--black) !important; overflow-x: hidden; }
            body { background-color: transparent !important; }
            body::after { content: ''; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: var(--bg-color); z-index: -1; }
            .shop-nav::before { content: ''; position: absolute; bottom: 99%; left: 0; width: 100%; height: 100vh; background-color: var(--bg-color); z-index: 100; }
            #cart-drawer { border-left: none !important; }
            #cart-drawer.open { box-shadow: none !important; }
            .shop-nav { justify-content: space-between; padding: 12px 4vw; }
            .nav-logo-center { position: static; transform: none; margin-right: auto; max-width: 45vw; }
            .logo-image { height: 55px; max-width: 100%; }
            .cart-btn { font-size: 1.2rem; padding: 6px 12px; border-width: 2px; box-shadow: 3px 3px 0px var(--black); margin-left: 10px; }
            .shop-header { padding: 30px 4vw 15px 4vw; }
            .headline-cutout { font-size: clamp(2.2rem, 11vw, 4rem); -webkit-text-stroke: 2px var(--black); text-shadow: 5px 5px 0 var(--accent); }
            .product-grid { display: grid; grid-template-columns: repeat(2, 1fr); column-gap: 12px; row-gap: 15px; padding: 10px 4vw 100px 4vw; align-items: start; }
            .product-card { width: 100%; padding: 12px; gap: 10px; border-width: 3px; box-shadow: 5px 5px 0px rgba(0,0,0,1); background: #ffffff; margin-top: 0 !important; }
            .product-card:nth-child(odd) { transform: rotate(-1.5deg); }
            .product-card:nth-child(even) { transform: translateY(40px) rotate(1.5deg); }
            .product-card:hover, .product-card:active { box-shadow: 5px 5px 0px rgba(0,0,0,1); }
            .product-card:nth-child(odd):hover, .product-card:nth-child(odd):active { transform: rotate(-1.5deg); }
            .product-card:nth-child(even):hover, .product-card:nth-child(even):active { transform: translateY(40px) rotate(1.5deg); }
            .product-card:hover .product-image, .product-card:active .product-image { transform: none; }
            .product-card::before { width: 40px; height: 14px; top: -7px; left: 45%; border-width: 2px; }
            .product-card::after { width: 35px; height: 12px; top: -6px; left: 55%; border-width: 1px; }
            .product-image-wrapper { border-width: 2px; }
            .product-title { font-size: 1.15rem; margin-top: 5px; line-height: 1; }
            .product-price { font-size: 1rem; padding: 4px 8px; }
            .product-options { flex-direction: column; gap: 6px; width: 100%; }
            .brutal-select { width: 100%; height: 42px !important; font-size: 0.85rem; border-width: 2px; padding: 0 8px !important; flex: none !important; -webkit-appearance: none !important; border-radius: 0 !important; color: var(--black) !important; }
            .add-to-cart-btn { width: 100%; height: 46px; font-size: 1.25rem; border-width: 2px; box-shadow: 3px 3px 0px rgba(0,0,0,0.3); margin-top: 2px; }
            .brutal-footer { flex-direction: column; gap: 20px; text-align: center; padding: 30px 4vw 50px 4vw; margin-bottom: 0; box-shadow: none; }
            .brutal-footer .footer-links { flex-direction: column; gap: 15px; }
        }

        /* =========================================================
           LIGHTBOX MODAL
           ========================================================= */
        .lightbox-modal { position: fixed; top: 0; left: 0; width: 100vw; height: 100dvh; background: rgba(0, 0, 0, 0.85); z-index: 9999; display: none; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(4px); overscroll-behavior: contain; touch-action: none; }
        .lightbox-modal.active { display: flex; }
        .lightbox-content-wrapper { position: relative; max-width: 90vw; max-height: 75dvh; display: flex; align-items: center; justify-content: center; }
        #lightbox-img { max-width: 90vw; max-height: 75dvh; object-fit: contain; border: 4px solid var(--black); box-shadow: 8px 8px 0px var(--black); background: var(--bg-color); user-select: none; -webkit-user-drag: none; border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; }
        .lightbox-close { position: absolute; top: 20px; right: 20px; background: var(--accent); color: #fff; border: 3px solid var(--black); box-shadow: 4px 4px 0px var(--black); font-family: 'Klebekunst Headline', sans-serif; font-size: 2.5rem; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.1s; z-index: 10000; padding-bottom: 5px; }
        .lightbox-close:hover { transform: translate(-2px, -2px) rotate(3deg); box-shadow: 6px 6px 0px var(--black); }
        .lightbox-close:active { transform: translate(2px, 2px); box-shadow: none; }
        .lightbox-nav { position: absolute; top: 50%; transform: translateY(-50%); background: #fff; color: var(--black); border: 3px solid var(--black); box-shadow: 4px 4px 0px var(--black); font-family: 'Inter', sans-serif; font-weight: 900; font-size: 2.5rem; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.1s; z-index: 10000; }
        .lightbox-nav:hover { background: var(--accent); color: #fff; transform: translateY(-50%) translate(-2px, -2px); box-shadow: 6px 6px 0px var(--black); }
        .lightbox-nav:active { transform: translateY(-50%) translate(2px, 2px); box-shadow: none; }
        .lightbox-prev { left: 20px; }
        .lightbox-next { right: 20px; }
        .lightbox-counter { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: #fff; color: var(--black); border: 3px solid var(--black); box-shadow: 4px 4px 0px var(--black); padding: 5px 15px; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.5rem; z-index: 10000; }
        
        body.lightbox-open { overflow: hidden !important; overscroll-behavior: contain; padding-right: var(--scrollbar-compensation, 0px); }
        .shop-nav.lightbox-open { padding-right: calc(var(--nav-pad-right, 5vw) + var(--scrollbar-compensation, 0px)); }

        @media (max-width: 767px) {
            .lightbox-prev { left: 5vw; top: auto; bottom: 20px; transform: none; }
            .lightbox-next { right: 5vw; top: auto; bottom: 20px; transform: none; }
            .lightbox-nav:hover { transform: translate(-2px, -2px); }
            .lightbox-nav:active { transform: translate(2px, 2px); }
            .lightbox-counter { bottom: 80px; }
            .shop-nav.lightbox-open { padding-right: calc(4vw + var(--scrollbar-compensation, 0px)); }
        }
    </style>
</head>
<body>

    <canvas id="magnetic-grid"></canvas>

    <nav class="shop-nav" id="shop-nav">
        <a href="index.php" class="nav-logo-center">
            <img src="images/LongLogo.png" class="logo-image" alt="KLEBEKUNST">
        </a>
        <button type="button" class="cart-btn" id="nav-cart-btn" onclick="toggleCartDrawer()">WARENKORB [<span id="cart-count"><?php echo $cartTotalItems; ?></span>]</button>
    </nav>

    <div id="cart-drawer">
        <div class="drawer-header">
            <span>WARENKORB</span>
            <button class="drawer-close" onclick="toggleCartDrawer()">[X]</button>
        </div>
        <div class="drawer-body" id="drawer-items-container">
            <div style="font-weight: 800; text-align: center; margin-top: 40px;">LÄDT...</div>
        </div>
        <div class="drawer-footer">
            <div class="drawer-total">
                <span>WARENWERT:</span>
                <span><span id="drawer-total-price">0,00</span><span class="euro">€</span></span>
            </div>
            <div id="drawer-shipping-box" style="margin-top: 5px; padding: 10px 12px; background: #fff; border: 2px solid var(--black); box-shadow: 3px 3px 0px var(--black); font-size: 0.85rem; text-transform: uppercase;">
                <div style="display: flex; justify-content: space-between; align-items: center; font-weight: 800;">
                    <span id="drawer-shipping-tier-name">Versand (DE):</span>
                    <span style="font-size: 1.1rem; color: var(--accent);"><span id="drawer-shipping-cost">1,80</span> €</span>
                </div>
                <div id="drawer-shipping-subtext" style="font-size: 0.72rem; opacity: 0.7; font-weight: 700; margin-top: 3px;">
                    Großbrief (1–4 Packungen) • Ausland an der Kasse
                </div>
            </div>
            <a href="checkout.php" class="checkout-go-btn" style="margin-top: 8px;">ZUR KASSE &rsaquo;</a>
        </div>
    </div>

    <header class="shop-header">
        <h1 class="headline-cutout">AUFKLEBER</h1>
    </header>

    <main class="product-grid">
        <?php if (!empty($products) && is_array($products)): ?>
            <?php 
                $activeDrop = get_active_drop();
                $activeDropId = $activeDrop['id'] ?? null;

                // 1. Produkte filtern: Entwürfe ausblenden & Drop-Zuweisung beachten
                $visibleProducts = [];
                foreach ($products as $key => $item) {
                    if (($item['status'] ?? 'active') === 'hidden') {
                        continue;
                    }
                    $prodDropId = !empty($item['drop_id']) ? $item['drop_id'] : null;
                    if ($prodDropId !== null && $activeDropId !== null && $prodDropId !== $activeDropId) {
                        continue;
                    }
                    $visibleProducts[$key] = $item;
                }

                // 2. Sicherheits-Fallback: Falls durch Drop-Filter kein Produkt übrig bleibt,
                // zeigen wir alle aktiven Produkte an, damit der Shop niemals versehentlich leer ist!
                if (empty($visibleProducts)) {
                    foreach ($products as $key => $item) {
                        if (($item['status'] ?? 'active') !== 'hidden') {
                            $visibleProducts[$key] = $item;
                        }
                    }
                }
            ?>
            <?php if (empty($visibleProducts)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; font-weight: 800; font-size: 1.3rem;">
                    Aktuell sind keine Produkte verfügbar. Bitte schau bald wieder vorbei!
                </div>
            <?php else: ?>
            <?php foreach ($visibleProducts as $key => $item): ?>
                <?php 
                    $hasSizes = isset($item['has_sizes']) ? $item['has_sizes'] : (!empty($item['sizes']));
                    $rawImg = !empty($item['image']) ? $item['image'] : 'images/placeholder.png';
                    $imgPath = $rawImg;
                    $prodId = isset($item['id']) ? (string)$item['id'] : (string)$key; 

                    $stockInfo = $allStocks[$prodId] ?? null;
                    $qtyStep = isset($item['qty_step']) ? max(1, (int)$item['qty_step']) : 50;
                    $currentPieces = isset($stockInfo['stock']) ? (int)$stockInfo['stock'] : stock_get($prodId);
                    $availablePacks = isset($stockInfo['available_packs']) ? (int)$stockInfo['available_packs'] : (int)floor($currentPieces / $qtyStep);
                    $isSoldOut = ($availablePacks <= 0);
                    $maxPacks = min(10, $availablePacks);
                ?>
                <div class="product-card" id="card-<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="product-image-wrapper" onclick="openLightbox('<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>')" style="cursor: zoom-in;">
                        <img src="<?php echo htmlspecialchars($imgPath); ?>?v=<?php echo file_exists(__DIR__ . '/' . $imgPath) ? filemtime(__DIR__ . '/' . $imgPath) : '1'; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="product-image" onerror="this.src='images/placeholder.png'; this.onerror=null;">
                        <?php if ($isSoldOut): ?>
                            <div style="position:absolute; top:12px; right:12px; background:var(--accent); color:#fff; font-family:'Klebekunst Headline', sans-serif; font-size:1.3rem; padding:4px 10px; border:2.5px solid var(--black); box-shadow:3px 3px 0 var(--black); transform:rotate(4deg); text-transform:uppercase; z-index:4;">
                                AUSVERKAUFT
                            </div>
                        <?php endif; ?>
                    </div>
                    <h2 class="product-title"><?php echo htmlspecialchars($item['title']); ?></h2>
                    <div class="product-meta">
                        <span class="product-type-badge"><?php echo !empty($hasSizes) ? 'Textil' : 'Aufkleber'; ?></span>
                        <?php if (!empty($item['size'])): ?>
                            <span class="product-size-info"><?php echo htmlspecialchars($item['size']); ?></span>
                        <?php endif; ?>
                        <?php if ($isSoldOut): ?>
                            <span class="product-stock-badge sold-out">AUSVERKAUFT</span>
                        <?php elseif ($availablePacks <= 5): ?>
                            <span class="product-stock-badge low-stock">Nur noch <?php echo $availablePacks; ?> Pack!</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['description'])): ?>
                        <p class="product-description" style="font-size:0.85rem; line-height:1.4; color:#333; margin:4px 0 6px 0; font-weight:500;">
                            <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                        </p>
                    <?php endif; ?>
                    <div class="product-price"><?php echo number_format($item['price'], 2, ',', '.'); ?> €</div>

                    <div class="product-options">
                        <?php if ($hasSizes && !empty($item['sizes']) && is_array($item['sizes'])): ?>
                            <select id="size_<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>" class="brutal-select" <?php echo $isSoldOut ? 'disabled' : ''; ?>>
                                <option value="" disabled selected>GRÖSSE</option>
                                <?php foreach ($item['sizes'] as $size): ?>
                                    <option value="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($isSoldOut): ?>
                            <select id="qty_<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>" class="brutal-select shop-qty-select" disabled style="opacity:0.65; cursor:not-allowed;">
                                <option value="0">0 Pack (Ausverkauft)</option>
                            </select>
                        <?php else: ?>
                            <select id="qty_<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>" class="brutal-select shop-qty-select" data-prod-id="<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php for ($q = 1; $q <= $maxPacks; $q++): ?>
                                    <option value="<?php echo $q; ?>">
                                        <?php echo ($qtyStep > 1) ? "{$q} Pack (" . ($q * $qtyStep) . " Stk.)" : "{$q} Stück"; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <?php if ($isSoldOut): ?>
                        <button class="add-to-cart-btn sold-out" id="btn-add-<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            AUSVERKAUFT
                        </button>
                    <?php else: ?>
                        <button class="add-to-cart-btn" id="btn-add-<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>" onclick="addToCart('<?php echo htmlspecialchars($prodId, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $hasSizes ? 'true' : 'false'; ?>, this)">
                            HINZUFÜGEN
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="brutal-footer">
        <div class="footer-brand">KLEBEKUNST</div>
        <div class="footer-links">
            <a href="impressum.php">Impressum</a>
            <a href="datenschutz.php">Datenschutz</a>
            <a href="https://instagram.com/1899klebekunst" target="_blank">Instagram</a>
        </div>
    </footer>

    <!-- LIGHTBOX MODAL -->
    <div id="lightbox-modal" class="lightbox-modal" onclick="if(event.target===this) closeLightbox();">
        <button class="lightbox-close" onclick="closeLightbox()">X</button>
        <button class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1, event)">&lsaquo;</button>
        <button class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1, event)">&rsaquo;</button>
        <div class="lightbox-counter"><span id="lightbox-curr">1</span> / <span id="lightbox-tot">3</span></div>
        <div class="lightbox-content-wrapper" id="lightbox-swipe-area">
            <img src="" id="lightbox-img" alt="Detailbild">
        </div>
    </div>

    <script>
        window.PRODUCTS = <?php echo json_encode($products); ?> || {};
        window.PRODUCT_STOCKS = <?php echo json_encode($allStocks); ?> || {};
    </script>
    <script src="script.js"></script>
    <script src="canvas.js"></script>
</body>
</html>