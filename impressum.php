<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KLEBEKUNST | IMPRESSUM</title>
    
    <link rel="icon" type="image/png" href="images/favicon-black.png?v=2" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" href="images/favicon-white.png?v=2" media="(prefers-color-scheme: dark)">
    
    <style>
        @font-face { font-family: 'Klebekunst Headline'; src: url('fonts/Klebekunst-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Inter'; src: url('fonts/Inter-Variable.woff2') format('woff2-variations'), url('fonts/Inter-VariableFont_opsz,wght.ttf') format('truetype'); font-weight: 100 900; font-style: normal; font-display: swap; }

        *, *:before, *:after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg-color: #f0f0e8; --black: #000000; --accent: #ff2a00; --border-thick: 4px solid var(--black); }

        /* overflow-y: scroll; zwingt die Scrollbar immer sichtbar zu sein und verhindert Layout-Sprünge */
        body { 
            color: var(--black); 
            font-family: 'Inter', sans-serif; 
            min-height: 100vh; 
            position: relative; 
            overflow-x: hidden; 
            display: flex; 
            flex-direction: column; 
            overscroll-behavior: none; /* NEU: Blockiert das Über-Scrollen (oben & unten) */
        }

        /* =========================================
           NEO-BRUTALISMUS SCROLLBAR 
           ========================================= */
        /* Für Firefox */
        html {
            scrollbar-width: auto;
            scrollbar-color: var(--black) var(--bg-color);
            overscroll-behavior: none; /* NEU: Blockiert das Über-Scrollen (oben & unten) */
        }

        /* Für Chrome, Edge, Safari */
        ::-webkit-scrollbar {
            width: 18px; /* Schön klobig und fett */
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-color);
            border-left: 4px solid var(--black); /* Zieht eine harte schwarze Linie am Rand runter */
        }
        ::-webkit-scrollbar-thumb {
            background: var(--black);
            border: 3px solid var(--bg-color); /* Gibt dem Anfasser einen Rahmen im Track */
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent); /* Knallrot, wenn man die Scrollbar anfasst! */
        }

        #magnetic-grid { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 1; pointer-events: none; }

        /* Navbar */
        .shop-nav { position: sticky; top: 0; left: 0; width: 100%; background: var(--bg-color); border-bottom: var(--border-thick); display: flex; justify-content: flex-end; align-items: center; padding: 15px 5vw; z-index: 100; box-shadow: 0px 8px 0px rgba(0,0,0,1), 0px -50vh 0px var(--bg-color); }

        /* CART Button */
        .cart-btn { background: var(--accent); color: #fff; font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; padding: 8px 20px; border: 3px solid var(--black); border-radius: 255px 15px 225px 5px / 15px 255px 5px 225px; text-decoration: none; box-shadow: 4px 4px 0px var(--black); transition: all 0.1s; cursor: pointer; }
        .cart-btn:hover { transform: translate(-2px, -2px) rotate(2deg); box-shadow: 6px 6px 0px var(--black); }

        /* ZURÜCK Button */
        .back-btn { background: var(--bg-color); color: var(--black); font-family: 'Klebekunst Headline', sans-serif; font-size: 1.8rem; padding: 8px 20px; border: 3px solid var(--black); border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; text-decoration: none; text-transform: uppercase; box-shadow: 4px 4px 0px var(--black); transition: all 0.1s; cursor: pointer; }
        .back-btn:hover { background: var(--black); color: var(--bg-color); box-shadow: 6px 6px 0px var(--accent); transform: translate(-2px, -2px) rotate(-1deg); }

        /* Zentriertes Logo (Fixiert, ohne Hover-Effekt) */
        .nav-logo-center { position: absolute; left: 50%; top: 10px; transform: translateX(-50%); display: block; text-decoration: none; z-index: 150; }
        
        /* Durchsichtiger Platzhalter (ohne Animation) */
        .logo-placeholder { width: 220px; height: 110px; background-color: transparent; border: 2px dashed var(--black); }
        
        /* Echtes Bild (Clean) */
        .logo-image { height: 180px; width: auto; object-fit: contain; }

        .shop-header { padding: 130px 5vw 20px 5vw; position: relative; z-index: 10; text-align: center; }
        .headline-cutout { 
            font-family: 'Klebekunst Headline', sans-serif; 
            font-size: clamp(3rem, 10vw, 8rem); 
            color: #ffffff; /* Weiße Füllung macht es sofort gut lesbar */
            -webkit-text-stroke: 3px var(--black); /* Fetter schwarzer Rand */
            text-shadow: 8px 8px 0 var(--accent); /* Klarer roter Versatz-Schatten */
            text-transform: uppercase; 
            line-height: 0.9; 
            display: inline-block; 
            transform: rotate(-1.5deg); 
            letter-spacing: 1px;
        }

        /* Der Container für Impressum/Datenschutz: Richtig massiv und breit auf dem Desktop */
        .legal-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 5vw 100px 5vw; position: relative; z-index: 10; flex: 1; }
        
        .action-box { position: relative; background-color: #fcfcfc; border: var(--border-thick); padding: 40px 50px; box-shadow: 12px 12px 0px rgba(0,0,0,1); border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; transform: rotate(1deg); }
        .action-box::before { content: ""; position: absolute; top: -15px; left: 50%; transform: translateX(-50%) rotate(2deg); width: 120px; height: 35px; background-color: #cccccc; background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px), repeating-linear-gradient(-45deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px); border-left: 3px dashed rgba(0,0,0,0.25); border-right: 3px dashed rgba(0,0,0,0.25); box-shadow: 2px 3px 4px rgba(0,0,0,0.2); z-index: 5; }

        /* CONTENT STYLES (IMPRESSUM & DATENSCHUTZ) */
        .legal-content { 
            font-size: 1.05rem; 
            line-height: 1.6; 
            color: #111; 
            /* Sorgt für saubere Umbrüche an Leerzeichen, ohne die Box zu sprengen */
            overflow-wrap: break-word; 
            word-wrap: break-word; 
            /* Verbietet abgehackte Trennungen (keine halben Wörter am Zeilenende) */
            hyphens: none; 
            -webkit-hyphens: none;
        }
        .legal-content h3 { font-family: 'Klebekunst Headline', sans-serif; font-size: 2rem; border-bottom: 3px solid var(--black); padding-bottom: 5px; margin-top: 35px; margin-bottom: 15px; text-transform: uppercase; }
        .legal-content h3:first-child { margin-top: 0; }
        .legal-content h4 { font-size: 1.15rem; font-weight: 900; margin-top: 25px; margin-bottom: 10px; text-transform: uppercase; color: var(--accent); }
        .legal-content p, .legal-content ul { margin-bottom: 20px; font-weight: 500; }
        .legal-content ul { padding-left: 25px; }
        .legal-content li { margin-bottom: 5px; }
        
        .legal-content a { 
            color: var(--accent); 
            font-weight: 800; 
            text-decoration: none; 
            /* WICHTIG: Zwingt lange Links (URLs) zum Umbruch, damit sie nie aus der Box laufen! */
            word-break: break-all; 
        }
        .legal-content a:hover { text-decoration: underline; text-decoration-thickness: 2px; }

        .brutal-footer { background: var(--black); color: var(--bg-color); border-top: var(--border-thick); padding: 30px 5vw; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10; font-family: 'Klebekunst Headline', sans-serif; text-transform: uppercase; margin-top: auto; box-shadow: 0px 10px 0px var(--black); }
        .brutal-footer .footer-brand { font-size: 2.5rem; transform: rotate(-2deg); }
        .brutal-footer .footer-links { display: flex; gap: 30px; }
        .brutal-footer a { color: var(--bg-color); text-decoration: none; font-size: 1.5rem; transition: color 0.1s; }
        .brutal-footer a:hover { color: var(--accent); text-decoration: underline; text-decoration-thickness: 3px; text-underline-offset: 4px; }

        @media (max-width: 850px) {
            /* Buttons kompakter machen, damit in der Breite Platz fürs Logo ist */
            .shop-nav { padding: 12px 4vw; }
            .cart-btn, .back-btn { font-size: 1.3rem; padding: 6px 12px; border-width: 2px; box-shadow: 3px 3px 0px var(--black); }
            
            /* Logo (Handy: Linksbündig, etwas höher, nicht abgeschnitten!) */
            .nav-logo-center { 
                left: 4vw; 
                top: 8px; /* Ein Stück höher gezogen */
                transform: none; /* WICHTIG: Schaltet die Desktop-Zentrierung aus */
            }
            .logo-placeholder { width: 120px; height: 60px; border: 2px dashed var(--black); }
            .logo-image { height: 60px; max-width: 45vw; }
            
            /* WICHTIG: Den Header tief nach unten schieben, damit das Logo nicht in die Schrift hängt */
            .shop-header { padding: 30px 4vw 15px 4vw; }
            .headline-cutout { 
                font-size: clamp(2.2rem, 11vw, 4rem); 
                -webkit-text-stroke: 2px var(--black); 
                text-shadow: 5px 5px 0 var(--accent); 
            }
            .legal-container { padding: 0 4vw 60px 4vw; }
            /* Innenabstand der Box auf Handys verkleinern, damit mehr Textbreite bleibt */
            .action-box { 
                padding: 24px 16px; 
                transform: rotate(0deg); 
            }

            /* Schriftgrößen kompakt halten */
            .legal-content { 
                font-size: 0.95rem; 
                line-height: 1.5;
            }
            .legal-content h3 { 
                font-size: 1.5rem; 
                margin-top: 25px; 
            }
            .legal-content h4 { 
                font-size: 1rem; 
            }
            .legal-content ul { 
                padding-left: 18px; 
            }
            .brutal-footer { flex-direction: column; gap: 20px; text-align: center; padding: 30px 4vw 50px 4vw; }
            .brutal-footer .footer-links { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>

    <canvas id="magnetic-grid"></canvas>

    <nav class="shop-nav">
        <!-- Zentriertes Logo -->
        <a href="index.php" class="nav-logo-center">
            <img src="images/LongLogo.png" class="logo-image" alt="KLEBEKUNST">
        </a>

        <!-- Rechter Button -->
        <a href="shop.php" class="back-btn">&lsaquo; ZURÜCK</a>
    </nav>

    <header class="shop-header">
        <h1 class="headline-cutout">IMPRESSUM</h1>
    </header>

    <main class="legal-container">
        <div class="action-box">
            <div class="legal-content">
                <h3>Angaben gemäß § 5 DDG</h3>
                <p>
                    Jonas Pilling<br>
                    Klebekunst<br>
                    Langwiesenweg 41<br>
                    61267 Neu-Anspach<br>
                    Deutschland
                </p>

                <h3>Kontakt</h3>
                <p>
                    E-Mail: <a href="mailto:eintrachtfrankfurt.klebekunst@gmail.com">eintrachtfrankfurt.klebekunst@gmail.com</a><br>
                    Instagram: <a href="https://instagram.com/1899klebekunst" target="_blank">@1899klebekunst</a>
                </p>

                <h3>Umsatzsteuer</h3>
                <p>
                    Gemäß § 19 UStG wird keine Umsatzsteuer berechnet und ausgewiesen (Kleinunternehmerregelung).
                </p>

                <h3>EU-Streitschlichtung</h3>
                <p>
                    Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:<br>
                    <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr/</a>.<br>
                    Unsere E-Mail-Adresse findest du oben im Impressum.
                </p>

                <h3>Verbraucherstreitbeilegung</h3>
                <p>
                    Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
                </p>
            </div>
        </div>
    </main>

    <footer class="brutal-footer">
        <div class="footer-brand">KLEBEKUNST</div>
        <div class="footer-links">
            <a href="datenschutz.php">Datenschutz</a>
            <a href="https://instagram.com/1899klebekunst" target="_blank">Instagram</a>
        </div>
    </footer>

    <script src="canvas.js"></script>
</body>
</html>