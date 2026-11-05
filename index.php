<?php
// NEU: Eigener Name für das Cookie, damit alte Browser-Kekse ignoriert werden!
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
$settings = get_shop_settings();

$drop_is_live = (bool)($settings['shop_open'] ?? true); 
$geheim_passwort = $settings['vip_password'] ?? "Waldstadion2026"; 

$isLoggedIn = false;
if (isset($_SESSION['drop_access']) && $_SESSION['drop_access'] === true) {
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
    } else {
        $_SESSION['last_activity'] = time();
        $isLoggedIn = true;
    }
}

$error_msg = "";

// Brute-Force-Schutz VIP-Login: max 5 Versuche, 15 Min Sperre
$_vipMaxAttempts = 5;
$_vipLockout = 900;
$_vipLocked = false;
if (isset($_SESSION['vip_fail_count']) && $_SESSION['vip_fail_count'] >= $_vipMaxAttempts) {
    $elapsed = time() - ($_SESSION['vip_fail_time'] ?? 0);
    if ($elapsed < $_vipLockout) {
        $_vipLocked = true;
        $error_msg = 'X ZU VIELE VERSUCHE – BITTE ' . ceil(($_vipLockout - $elapsed) / 60) . ' MIN WARTEN X';
    } else {
        unset($_SESSION['vip_fail_count'], $_SESSION['vip_fail_time']);
    }
}

if (!$_vipLocked && $_SERVER["REQUEST_METHOD"] == "POST") {
    if ($drop_is_live || (isset($_POST['enter_shop']) && $isLoggedIn)) {
        $_SESSION['drop_access'] = true;
        $_SESSION['last_activity'] = time();
        header("Location: shop.php");
        exit;
    } else {
        if (isset($_POST['pwd']) && $_POST['pwd'] === $geheim_passwort) {
            $_SESSION['drop_access'] = true;
            $_SESSION['last_activity'] = time();
            unset($_SESSION['vip_fail_count'], $_SESSION['vip_fail_time']);
            header("Location: shop.php");
            exit;
        } elseif (isset($_POST['pwd'])) {
            $_SESSION['vip_fail_count'] = ($_SESSION['vip_fail_count'] ?? 0) + 1;
            $_SESSION['vip_fail_time'] = time();
            $error_msg = "X FALSCHER CODE X";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KLEBEKUNST | GUDE</title>
    
    <link rel="icon" type="image/png" href="images/favicon-black.png?v=2" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" href="images/favicon-white.png?v=2" media="(prefers-color-scheme: dark)">
    
    <style>
        /* =========================================================
           NEO-BRUTALISM RESET & VARIABLES
           ========================================================= */
        @font-face {
            font-family: 'Klebekunst Headline';
            src: url('fonts/Klebekunst-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'Inter';
            src: url('fonts/Inter-Variable.woff2') format('woff2-variations'),
                 url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype');
            font-weight: 100 900;
            font-style: normal;
            font-display: swap;
        }

        *, *:before, *:after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-color: #f0f0e8; 
            --black: #000000;
            --accent: #ff2a00;   
        }

        html, body {
            height: 100dvh; 
            width: 100vw;
            overflow: hidden;
            position: relative;
            background-color: var(--bg-color);
            color: var(--black);
            font-family: 'Inter', sans-serif;
            touch-action: none; 
        }

        /* =========================================================
           HINTERGRUNDBILD
           ========================================================= */
        .bg-texture {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100dvh;
            background-image: url('images/Klebekunst.jpg'); 
            background-size: cover;
            background-position: center;
            filter: grayscale(100%) contrast(200%) brightness(0.8);
            opacity: 0.15;
            mix-blend-mode: multiply;
            z-index: 1;
            pointer-events: none;
        }

        /* =========================================================
           INTERAKTIVES CANVAS RASTER
           ========================================================= */
        #magnetic-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100dvh;
            z-index: 2; 
            pointer-events: none; 
        }

        /* =========================================================
           LOGO STEMPEL (NEU)
           ========================================================= */
        .brand-stamp {
            position: absolute;
            top: 6vh;
            right: 6vw;
            width: 100px;
            height: auto;
            z-index: 15;
            transform: rotate(12deg);
            filter: drop-shadow(8px 8px 0px var(--accent)); /* Roter harter Schatten hinterm schwarzen Logo */
            opacity: 0.95;
            pointer-events: none;
        }

        /* =========================================================
           TYPOGRAFIE 
           ========================================================= */
        .hero-typography {
            position: absolute;
            top: 6dvh;
            left: 5vw;
            z-index: 5;
            pointer-events: none;
            display: flex;
            flex-direction: column;
        }

        .hero-line {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: clamp(5rem, 16vw, 16rem);
            line-height: 0.8;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .hero-line.cutout {
            color: var(--bg-color);
            -webkit-text-stroke: 0.03em var(--black);
            text-shadow: 
                 0.08em 0.08em 0 var(--black),
                 0.16em 0.16em 0 var(--accent);
            z-index: 3;
        }

        .hero-line.offset {
            position: relative;
            color: var(--bg-color);
            text-shadow: 
                -0.02em -0.02em 0 var(--black),  
                 0.02em -0.02em 0 var(--black),
                -0.02em  0.02em 0 var(--black),
                 0.02em  0.02em 0 var(--black);
            margin-left: 15vw;
            margin-top: 2dvh;
            z-index: 2;
        }

        .hero-line.offset::before {
            content: attr(data-text);
            position: absolute;
            top: 0.22em; 
            left: 0.15em;
            color: var(--accent);
            text-shadow: none; 
            z-index: -1;
            filter: url(#grainy-shadow);
            opacity: 0.9;
        }

        /* =========================================================
           STREET ART UI CONTAINER
           ========================================================= */
        .ui-container {
            position: absolute;
            bottom: 8dvh;
            right: 10vw;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            transform: rotate(-3deg);
        }

        .action-box {
            position: relative;
            background-color: #fcfcfc;
            border: 5px solid var(--black);
            border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px;
            box-shadow: 12px 15px 0px rgba(0,0,0,0.9);
            padding: 45px 35px 35px 35px;
            display: flex;
            flex-direction: column;
        }

        /* 
           NEUES DUCT TAPE (Panzertape 1 - Links) 
        */
        .action-box::before {
            content: "";
            position: absolute;
            top: -22px;
            left: 40%;
            transform: translateX(-50%) rotate(-4deg);
            width: 150px;
            height: 45px;
            background-color: #cccccc;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px),
                repeating-linear-gradient(-45deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px);
            border-left: 4px dashed rgba(0,0,0,0.25);
            border-right: 4px dashed rgba(0,0,0,0.25);
            box-shadow: 2px 4px 6px rgba(0,0,0,0.25);
            z-index: 5;
            border-radius: 2px 3px 1px 4px;
        }

        /* 
           NEUES DUCT TAPE (Panzertape 2 - Rechts überlappend) 
        */
        .action-box::after {
            content: "";
            position: absolute;
            top: -15px;
            left: 58%;
            transform: translateX(-50%) rotate(5deg);
            width: 120px;
            height: 38px;
            background-color: #b8b8b8;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px),
                repeating-linear-gradient(-45deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px);
            border-left: 3px dashed rgba(0,0,0,0.3);
            border-right: 3px dashed rgba(0,0,0,0.3);
            box-shadow: 2px 3px 5px rgba(0,0,0,0.3);
            z-index: 6;
            border-radius: 4px 1px 2px 3px;
        }

        .status-bar {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 2.5rem;
            line-height: 1;
            color: var(--black);
            text-transform: uppercase;
            border-bottom: 4px solid var(--black);
            border-radius: 255px 15px 225px 5px / 15px 255px 5px 225px;
            padding-bottom: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-bar span {
            background-color: var(--black);
            color: #fff;
            padding: 4px 10px;
            font-size: 2rem;
            transform: rotate(4deg);
        }

        .status-bar.live span {
            background-color: var(--accent);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        input[type="password"]:not(.vip-input) {
            width: 100%;
            height: 60px;
            background: transparent;
            border: none;
            border-bottom: 5px solid var(--black);
            border-radius: 5px 255px 5px 255px / 255px 5px 255px 5px;
            color: var(--black);
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 3rem;
            text-align: center;
            outline: none;
        }

        input[type="password"]:focus {
            background: rgba(0,0,0,0.03);
        }

        input[type="password"]::placeholder {
            color: rgba(0,0,0,0.2);
        }

        .neo-btn {
            width: 100%;
            height: 70px;
            background-color: var(--accent);
            color: #fff;
            border: 4px solid var(--black);
            border-radius: 255px 5px 225px 3px / 5px 255px 3px 225px;
            box-shadow: 6px 8px 0px var(--black);
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 2.8rem;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.1s;
        }

        .neo-btn:hover {
            transform: scale(1.03) rotate(-2deg);
            background-color: var(--black);
            color: var(--accent);
        }

        .neo-btn:active {
            transform: scale(0.98) translate(3px, 3px);
            box-shadow: 2px 2px 0px var(--black);
        }

        .error-badge {
            color: var(--accent);
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 2rem;
            text-align: center;
            margin-top: 10px;
            transform: rotate(-2deg);
        }

        /* =========================================================
           STATUS GESCHLOSSEN & VIP ZUGANG
           ========================================================= */
        .closed-notice-wrap {
            text-align: center;
            border-bottom: 4px solid var(--black);
            border-radius: 255px 15px 225px 5px / 15px 255px 5px 225px;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .closed-headline {
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 2.5rem;
            line-height: 1;
            color: var(--black);
            text-transform: uppercase;
        }

        .closed-subline {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--black);
            opacity: 0.7;
            margin-top: 8px;
            line-height: 1.3;
        }

        .vip-access-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .vip-toggle-btn {
            background: transparent;
            border: none;
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--black);
            opacity: 0.45;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            transition: all 0.15s ease;
        }

        .vip-toggle-btn:hover {
            opacity: 1;
            color: var(--accent);
            transform: translateY(-1px);
        }

        .vip-lock-svg {
            width: 14px;
            height: 14px;
            vertical-align: middle;
        }

        .vip-form {
            width: 100%;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .vip-input-row {
            display: flex;
            width: 100%;
            height: 48px;
            border: 3px solid var(--black);
            box-shadow: 4px 4px 0 var(--black);
            background: #fff;
            align-items: stretch;
            overflow: hidden;
            box-sizing: border-box;
            border-radius: 0;
        }

        .vip-input-row input.vip-input,
        .vip-input-row input[type="password"] {
            flex: 1;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
            max-height: none !important;
            border: none !important;
            border-bottom: none !important;
            border-radius: 0 !important;
            padding: 0 12px !important;
            font-family: 'Klebekunst Headline', sans-serif !important;
            font-size: 1.8rem !important;
            color: var(--black) !important;
            text-align: center !important;
            outline: none !important;
            box-shadow: none !important;
            margin: 0 !important;
            background: transparent !important;
            line-height: normal !important;
            box-sizing: border-box !important;
        }

        .vip-input-row input.vip-input::placeholder {
            color: rgba(0,0,0,0.3) !important;
            font-size: 1.4rem !important;
        }

        .vip-submit-btn {
            width: 52px;
            height: 100% !important;
            min-height: 0 !important;
            align-self: stretch;
            background: var(--accent);
            color: #fff;
            border: none !important;
            border-left: 3px solid var(--black) !important;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 !important;
            margin: 0 !important;
            flex-shrink: 0;
            box-sizing: border-box;
            border-radius: 0;
        }

        .vip-submit-btn svg {
            width: 22px;
            height: 22px;
            display: block;
            overflow: visible;
            transition: transform 0.15s ease;
        }

        .vip-submit-btn:hover {
            background: var(--black);
            color: var(--accent);
        }

        .vip-submit-btn:hover svg {
            transform: translateX(3px);
        }

        .vip-submit-btn:active svg {
            transform: scale(0.9);
        }

        /* =========================================================
           MOBILE ANPASSUNG 
           ========================================================= */
        @media (max-width: 850px) {
            .brand-stamp {
                top: 4dvh;
                right: 6vw;
                width: 75px; /* Skaliert das Logo auf Mobile */
                transform: rotate(8deg);
                filter: drop-shadow(5px 5px 0px var(--accent));
            }

            .hero-typography {
                top: 4dvh; 
                left: 5vw;
            }

            .hero-line {
                font-size: clamp(4.5rem, 20vw, 8rem); 
            }

            .hero-line.offset {
                margin-left: 8vw; 
                margin-top: 0dvh; /* FIX: Rutscht weiter runter, damit es KLEBE nicht überlappt */
            }

            .ui-container {
                /* Genug Abstand nach oben, damit der fixe Footer (ca. 90px) nichts verdeckt */
                bottom: calc(90px + 3dvh);
                right: 0;
                left: 0;
                margin: 0 auto; 
                width: 90vw;
                max-width: 380px; 
                transform: rotate(-1.5deg); 
            }

            .action-box {
                padding: 30px 20px 20px 20px; 
                box-shadow: 8px 10px 0px rgba(0,0,0,0.9);
            }

            /* Duct Tape Mobile Skalierung */
            .action-box::before {
                width: 120px;
                height: 35px;
                top: -18px;
            }
            .action-box::after {
                width: 90px;
                height: 30px;
                top: -12px;
            }

            .status-bar {
                font-size: 2rem;
                margin-bottom: 20px;
            }

            .status-bar span {
                font-size: 1.6rem;
            }

            input[type="password"] {
                height: 50px;
                font-size: 2.2rem; 
                border-bottom: 4px solid var(--black);
            }

            .neo-btn {
                height: 55px;
                font-size: 2.2rem; 
                box-shadow: 4px 6px 0px var(--black);
            }
        }

        /* =========================================================
           ADMIN BUTTON (Underneath Login Box)
           ========================================================= */
        .admin-link-bottom {
            display: block;
            text-align: center;
            margin-top: 15px;
            font-family: 'Klebekunst Headline', sans-serif;
            font-size: 1.2rem;
            color: var(--black);
            text-decoration: none;
            opacity: 0.2;
            transition: opacity 0.2s, transform 0.2s;
        }
        .admin-link-bottom:hover {
            opacity: 1;
            transform: scale(1.05);
            color: var(--accent);
        }
        /* =========================================================
           FOOTER
           ========================================================= */
        .brutal-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--black);
            color: var(--bg-color);
            border-top: 4px solid var(--black);
            padding: 18px 5vw;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
            font-family: 'Klebekunst Headline', sans-serif;
            text-transform: uppercase;
        }
        .brutal-footer .footer-brand {
            font-size: 2rem;
            transform: rotate(-2deg);
        }
        .brutal-footer .footer-links {
            display: flex;
            gap: 30px;
        }
        .brutal-footer a {
            color: var(--bg-color);
            text-decoration: none;
            font-size: 1.3rem;
            transition: color 0.1s;
        }
        .brutal-footer a:hover {
            color: var(--accent);
            text-decoration: underline;
            text-decoration-thickness: 3px;
            text-underline-offset: 4px;
        }

        @media (max-width: 600px) {
            .brutal-footer {
                flex-direction: column;
                gap: 8px;
                text-align: center;
                padding: 12px 4vw 12px 4vw;
                /* Safe-area für Geräte mit Home-Indikator (iPhone etc.) */
                padding-bottom: max(12px, env(safe-area-inset-bottom));
            }
            .brutal-footer .footer-links {
                gap: 20px;
            }
            .brutal-footer .footer-brand { font-size: 1.3rem; }
            .brutal-footer a { font-size: 1rem; }
        }
    </style>
</head>
<body>

    <!-- SVG FILTER -->
    <svg style="width:0; height:0; position:absolute; z-index:-1; pointer-events:none;" aria-hidden="true">
        <filter id="grainy-shadow" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur in="SourceGraphic" stdDeviation="12" result="blur" />
            <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" result="noise" />
            <feColorMatrix type="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 4 -1.5" in="noise" result="sharpNoise" />
            <feComposite in="blur" in2="sharpNoise" operator="in" />
        </filter>
    </svg>

    <!-- HINTERGRUND & RASTER -->
    <div class="bg-texture"></div>
    <canvas id="magnetic-grid"></canvas>

    <!-- LOGO STEMPEL (Nutzt favicon-black.png) -->
    <img src="images/favicon-black.png" class="brand-stamp" alt="Klebekunst Logo">

    <!-- TYPOGRAFIE -->
    <div class="hero-typography">
        <span class="hero-line cutout" data-text="KLEBE">KLEBE</span>
        <span class="hero-line offset" data-text="KUNST">KUNST</span>
    </div>

    <!-- UI -->
    <main class="ui-container">
        <div class="action-box">
            <?php if ($drop_is_live || $isLoggedIn): ?>
                <div class="status-bar live">ZUGANG <span style="background: var(--accent);">OFFEN</span></div>
                <form method="POST" action="">
                    <button type="submit" name="enter_shop" value="1" class="neo-btn">SHOP BETRETEN</button>
                </form>
            <?php else: ?>
                <div class="closed-notice-wrap">
                    <div class="closed-headline"><?= htmlspecialchars($settings['closed_title'] ?? 'AKTUELL IST ALLES AUSVERKAUFT') ?></div>
                    <div class="closed-subline"><?= htmlspecialchars($settings['closed_subtitle'] ?? 'Bald folgt mehr.') ?></div>
                </div>

                <!-- Dezenter VIP-Zugang -->
                <div class="vip-access-wrap">
                    <button type="button" class="vip-toggle-btn" id="vipToggleBtn" onclick="toggleVipForm()">
                        <svg class="vip-lock-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        ZUGANG
                    </button>
                    <form method="POST" action="" id="vipForm" class="vip-form" style="<?= !empty($error_msg) ? 'display:flex;' : 'display:none;' ?>">
                        <div class="vip-input-row">
                            <input type="password" name="pwd" placeholder="PASSWORT" class="vip-input" required autofocus>
                            <button type="submit" class="vip-submit-btn" title="Passwort bestätigen" aria-label="Passwort bestätigen">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="4" y1="12" x2="20" y2="12"></line>
                                    <polyline points="13 5 20 12 13 19"></polyline>
                                </svg>
                            </button>
                        </div>
                        <?php if (!empty($error_msg)): ?>
                            <div class="error-badge"><?php echo htmlspecialchars($error_msg); ?></div>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <!-- ADMIN BUTTON -->
        <a href="admin.php" class="admin-link-bottom">ADMIN</a>
    </main>

    <script src="canvas.js"></script>
    <script>
        function toggleVipForm() {
            const form = document.getElementById('vipForm');
            if (!form) return;
            const isHidden = form.style.display === 'none' || form.style.display === '';
            form.style.display = isHidden ? 'flex' : 'none';
            if (isHidden) {
                const inp = form.querySelector('input[name="pwd"]');
                if (inp) inp.focus();
            }
        }
    </script>

    <footer class="brutal-footer">
        <div class="footer-brand">KLEBEKUNST</div>
        <div class="footer-links">
            <a href="impressum.php">Impressum</a>
            <a href="datenschutz.php">Datenschutz</a>
            <a href="https://instagram.com/1899klebekunst" target="_blank">Instagram</a>
        </div>
    </footer>

</body>
</html>