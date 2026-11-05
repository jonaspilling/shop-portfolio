<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KLEBEKUNST | DATENSCHUTZ</title>
    
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
        .legal-container { width: 100%; max-width: 2000px; margin: 0 auto; padding: 0 5vw 100px 5vw; position: relative; z-index: 10; flex: 1; }
        
        .action-box { position: relative; background-color: #fcfcfc; border: var(--border-thick); padding: 40px 50px; box-shadow: 12px 12px 0px rgba(0,0,0,1); border-radius: 2px 255px 3px 225px / 255px 5px 225px 3px; transform: rotate(-0.5deg); }
        .action-box::before { content: ""; position: absolute; top: -15px; left: 50%; transform: translateX(-50%) rotate(-2deg); width: 120px; height: 35px; background-color: #cccccc; background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px), repeating-linear-gradient(-45deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px); border-left: 3px dashed rgba(0,0,0,0.25); border-right: 3px dashed rgba(0,0,0,0.25); box-shadow: 2px 3px 4px rgba(0,0,0,0.2); z-index: 5; }

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
        <h1 class="headline-cutout">DATENSCHUTZ</h1>
    </header>

    <main class="legal-container">
        <div class="action-box">
            <div class="legal-content">
                <h3>1. Datenschutz auf einen Blick</h3>
                <h4>Allgemeine Hinweise</h4>
                <p>
                    Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit deinen personenbezogenen Daten passiert, wenn du diese Website besuchst. Personenbezogene Daten sind alle Daten, mit denen du persönlich identifiziert werden kannst. Ausführliche Informationen zum Thema Datenschutz entnimmst du unserer nachfolgend aufgeführten Datenschutzerklärung.
                </p>

                <h4>Datenerfassung auf dieser Website</h4>
                <p>
                    <strong>Wer ist verantwortlich für die Datenerfassung auf dieser Website?</strong><br>
                    Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten kannst du dem Abschnitt „Verantwortliche Stelle“ dieser Datenschutzerklärung sowie unserem Impressum entnehmen.
                </p>
                <p>
                    <strong>Wie erfassen wir deine Daten?</strong><br>
                    Deine Daten werden zum einen dadurch erhoben, dass du uns diese mitteilst. Hierbei handelt es sich z.&nbsp;B. um Daten, die du in das Bestellformular (Kasse) eingibst. Andere Daten werden automatisch oder nach deiner Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst. Das sind vor allem technische Daten (z.&nbsp;B. Internetbrowser, Betriebssystem oder Uhrzeit des Seitenaufrufs).
                </p>
                <p>
                    <strong>Wofür nutzen wir deine Daten?</strong><br>
                    Ein Teil der Daten wird erhoben, um eine fehlerfreie Bereitstellung der Website zu gewährleisten und Angriffe abzuwehren. Andere Daten dienen zur Abwicklung deiner Bestellung, zur Rechnungsstellung, zur Vorbereitung des Versands und zur Benachrichtigung über den Bestellstatus.
                </p>
                <p>
                    <strong>Welche Rechte hast du bezüglich deiner Daten?</strong><br>
                    Du hast jederzeit das Recht, unentgeltlich Auskunft über Herkunft, Empfänger und Zweck deiner gespeicherten personenbezogenen Daten zu erhalten. Du hast außerdem ein Recht, die Berichtigung oder Löschung dieser Daten zu verlangen. Wenn du eine Einwilligung zur Datenverarbeitung erteilt hast, kannst du diese jederzeit für die Zukunft widerrufen. Zudem hast du das Recht, die Einschränkung der Verarbeitung deiner Daten zu verlangen sowie ein Beschwerderecht bei der zuständigen Aufsichtsbehörde.
                </p>

                <h3>2. Verantwortliche Stelle</h3>
                <p>
                    Verantwortlicher für die Datenverarbeitung auf dieser Website ist:<br><br>
                    <strong>Jonas Pilling</strong><br>
                    Klebekunst<br>
                    Langwiesenweg 41<br>
                    61267 Neu-Anspach<br>
                    Deutschland<br><br>
                    E-Mail: <a href="mailto:eintrachtfrankfurt.klebekunst@gmail.com">eintrachtfrankfurt.klebekunst@gmail.com</a>
                </p>
                <p>
                    Verantwortliche Stelle ist die natürliche oder juristische Person, die allein oder gemeinsam mit anderen über die Zwecke und Mittel der Verarbeitung von personenbezogenen Daten (z.&nbsp;B. Namen, E-Mail-Adressen o.&nbsp;Ä.) entscheidet.
                </p>

                <h3>3. Rechtsgrundlagen der Datenverarbeitung</h3>
                <p>
                    Wir verarbeiten personenbezogene Daten nur unter Einhaltung der einschlägigen Datenschutzvorschriften (DSGVO, BDSG und TDDDG):
                </p>
                <ul>
                    <li><strong>Vertragserfüllung und vorvertragliche Maßnahmen (Art. 6 Abs. 1 lit. b DSGVO):</strong> Die Verarbeitung deiner Adress-, Kontakt- und Bestelldaten ist für die Abwicklung des Kaufvertrags sowie zur Bereitstellung deines Warenkorbs erforderlich.</li>
                    <li><strong>Rechtliche Verpflichtung (Art. 6 Abs. 1 lit. c DSGVO):</strong> Wir unterliegen steuer- und handelsrechtlichen Aufbewahrungspflichten (§ 147 AO, § 257 HGB), die uns zur Speicherung von Rechnungs- und Transaktionsdaten verpflichten.</li>
                    <li><strong>Berechtigte Interessen (Art. 6 Abs. 1 lit. f DSGVO):</strong> Soweit erforderlich, verarbeiten wir technische Protokolldaten zur Gewährleistung der IT-Sicherheit, zur Abwehr von Brute-Force-Angriffen und zum störungsfreien Betrieb unseres Webservers.</li>
                    <li><strong>Einwilligung (Art. 6 Abs. 1 lit. a DSGVO):</strong> Sofern du uns für bestimmte Verarbeitungen eine ausdrückliche Einwilligung erteilst, erfolgt die Verarbeitung auf dieser Grundlage. Die Einwilligung kann jederzeit mit Wirkung für die Zukunft widerrufen werden.</li>
                </ul>

                <h3>4. Hosting & Auftragsverarbeitung</h3>
                <h4>Hetzner Online</h4>
                <p>
                    Wir hosten unsere Website bei der <strong>Hetzner Online GmbH</strong>, Industriestr. 25, 91710 Gunzenhausen, Deutschland.
                </p>
                <p>
                    Wenn du unsere Website besuchst, erfasst Hetzner verschiedene Protokolldaten (Logfiles), inklusive deiner IP-Adresse. Details entnimmst du der Datenschutzerklärung von Hetzner: <a href="https://www.hetzner.com/de/legal/privacy-policy" target="_blank" rel="noopener noreferrer">https://www.hetzner.com/de/legal/privacy-policy</a>.
                </p>
                <p>
                    Die Verwendung von Hetzner erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Wir haben ein berechtigtes Interesse an einer möglichst zuverlässigen und sicheren Bereitstellung unserer Website.
                </p>
                <p>
                    <strong>Auftragsverarbeitung:</strong> Wir haben mit der Hetzner Online GmbH einen Vertrag über Auftragsverarbeitung (AVV) gemäß Art. 28 DSGVO abgeschlossen. Hierdurch wird gewährleistet, dass Hetzner die personenbezogenen Daten unserer Webseitenbesucher nur nach unseren Weisungen und unter Einhaltung der DSGVO verarbeitet.
                </p>

                <h3>5. Datenerfassung auf dieser Website</h3>
                <h4>Server-Log-Dateien</h4>
                <p>
                    Der Provider der Seiten erhebt und speichert automatisch Informationen in so genannten Server-Log-Dateien, die dein Browser automatisch an uns übermittelt. Dies sind:
                </p>
                <ul>
                    <li>Browsertyp und Browserversion</li>
                    <li>Verwendetes Betriebssystem</li>
                    <li>Referrer URL (zuvor besuchte Seite)</li>
                    <li>Hostname des zugreifenden Rechners</li>
                    <li>Uhrzeit der Serveranfrage</li>
                    <li>IP-Adresse (in gekürzter bzw. anonymisierter Form zur Missbrauchserkennung)</li>
                </ul>
                <p>
                    Eine Zusammenführung dieser Daten mit anderen Datenquellen wird nicht vorgenommen. Die Erfassung dieser Daten erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Unser berechtigtes Interesse liegt in der technisch fehlerfreien Darstellung, der Systemstabilität und der Optimierung unserer Website sowie dem Schutz vor Missbrauch und Denial-of-Service-Angriffen. Logdateien werden aus Sicherheitsgründen maximal 14 Tage aufbewahrt und danach automatisiert gelöscht.
                </p>

                <h4>Technisch notwendige Cookies (Session)</h4>
                <p>
                    Unsere Website verwendet ausschließlich ein technisch notwendiges Session-Cookie (Name: <code>KLEBEKUNST_SESSION</code>).
                </p>
                <p>
                    Dieses Cookie speichert eine zufällig generierte Sitzungs-ID (Session-ID), um deinen Warenkorb zwischen verschiedenen Unterseiten zuzuordnen und dir während deines Besuchs den Zugang zu aktiven Produkt-Drops zu ermöglichen. Das Cookie speichert keine Tracking-Informationen und kein Nutzerprofil.
                </p>
                <p>
                    <strong>Rechtsgrundlage:</strong> Die Speicherung von Informationen in der Endeinrichtung des Nutzers und der Zugriff darauf erfolgt auf Grundlage von <strong>§ 25 Abs. 2 Nr. 2 TDDDG</strong>, da dieser Vorgang unbedingt erforderlich ist, um den von dir ausdrücklich gewünschten Telemediendienst (Warenkorb & Bestellanfrage) bereitzustellen. Die nachgelagerte Verarbeitung erfolgt auf Basis von Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einem funktionierenden Online-Shop) sowie Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung).
                </p>
                <p>
                    <strong>Speicherdauer:</strong> Das Cookie verfällt automatisch beim Schließen deines Browsers bzw. nach maximal 30 Minuten Inaktivität. Da wir keinerlei Marketing-, Tracking- oder Third-Party-Cookies verwenden, ist kein gesondertes Cookie-Einwilligungsbanner erforderlich.
                </p>

                <h4>Privacy by Design: Keine externen Schriften & kein Tracking</h4>
                <p>
                    Wir legen großen Wert auf deine Privatsphäre:
                </p>
                <ul>
                    <li><strong>Lokale Schriftarten:</strong> Alle Schriften (wie z.&nbsp;B. Inter, Bebas Neue und unsere Hausschrift) sind lokal auf unserem Server eingebunden. Beim Laden der Website wird keine Verbindung zu externen Servern von Google Fonts oder sonstigen Schriftanbietern hergestellt. Deine IP-Adresse wird hierbei an niemanden weitergegeben.</li>
                    <li><strong>Kein Web-Tracking & keine Analysetools:</strong> Wir setzen bewusst keine Tracking-Dienste wie Google Analytics, Meta-Pixel (Facebook-Pixel), TikTok-Pixel oder sonstige Nutzer-Tracking-Tools ein. Wir erstellen keine Nutzungsprofile.</li>
                </ul>

                <h3>6. Bestellabwicklung im Shop</h3>
                <p>
                    Wenn du in unserem Shop Artikel bestellst, erfassen wir im Rahmen des Bestellformulars folgende Pflichtdaten:
                </p>
                <ul>
                    <li>Vor- und Nachname</li>
                    <li>Straße und Hausnummer</li>
                    <li>Postleitzahl und Ort</li>
                    <li>Lieferland</li>
                    <li>E-Mail-Adresse</li>
                    <li>Ausgewählte Artikel, Größen, Mengen und ggf. verwendete Rabattcodes</li>
                </ul>
                <p>
                    <strong>Zweck und Rechtsgrundlage:</strong> Die Verarbeitung dieser Daten erfolgt zur Erfüllung des mit dir geschlossenen Kaufvertrags (Art. 6 Abs. 1 lit. b DSGVO), namentlich zur Bearbeitung deiner Bestellung, zur Bereitstellung der Ware, zur Rechnungslegung und zur Versendung von Auftrags- und Versandbestätigungen.
                </p>
                <p>
                    <strong>Speicherdauer:</strong> Wir speichern deine Bestelldaten für die Dauer der Vertragsabwicklung sowie für die Dauer der gesetzlichen Gewährleistungs- und Garantiefristen. Darüber hinaus sind wir nach dem Handelsgesetzbuch (§ 257 HGB) und der Abgabenordnung (§ 147 AO) verpflichtet, steuer- und buchhaltungsrelevante Unterlagen (Rechnungen, Buchungsbelege, Bestellnachweise) für bis zu 10 Jahre aufzubewahren. Nach Ablauf dieser gesetzlichen Aufbewahrungsfristen werden die Daten gelöscht.
                </p>

                <h3>7. Versand & Logistikdienstleister</h3>
                <p>
                    Zur Auslieferung deiner bestellten Waren geben wir deine Adressdaten (Name, Anschrift, ggf. E-Mail für Sendungsverfolgungsbenachrichtigungen) an die folgenden Versandunternehmen weiter:
                </p>
                <ul>
                    <li><strong>Deutsche Post AG</strong>, Charles-de-Gaulle-Straße 20, 53113 Bonn, Deutschland</li>
                    <li><strong>DHL Paket GmbH</strong>, Sträßchensweg 10, 53113 Bonn, Deutschland</li>
                    <li><strong>Sendcloud B.V.</strong>, Stadhuisplein 10, 5611 EM Eindhoven, Niederlande (als technischer Versandabwickler zur Erstellung von Versandetiketten)</li>
                </ul>
                <p>
                    Die Übermittlung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO zur Erfüllung unseres Vertrages mit dir.
                </p>

                <h3>8. Zahlungsanbieter</h3>
                <h4>PayPal</h4>
                <p>
                    Auf dieser Website bieten wir die Bezahlung via PayPal an. Anbieter dieses Zahlungsdienstes ist die <strong>PayPal (Europe) S.à.r.l. et Cie, S.C.A.</strong>, 22-24 Boulevard Royal, L-2449 Luxemburg.
                </p>
                <p>
                    Wenn du die Zahlung via PayPal auswählst, werden die von dir eingegebenen Zahlungsdaten (z.&nbsp;B. Kaufbetrag, Währung, Bestellnummer und ggf. deine Bestelldaten) an PayPal übermittelt.
                </p>
                <p>
                    Die Übermittlung deiner Daten an PayPal erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung). PayPal behält sich für verschiedene Zahlungsmethoden die Durchführung einer Bonitätsauskunft vor. Hierauf haben wir keinen Einfluss.
                </p>
                <p>
                    Details zur Datenverarbeitung durch PayPal findest du in der Datenschutzerklärung von PayPal: <br>
                    <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full" target="_blank" rel="noopener noreferrer">https://www.paypal.com/de/webapps/mpp/ua/privacy-full</a>.
                </p>

                <h3>9. E-Mail-Kommunikation</h3>
                <h4>Transaktions-E-Mails via Google Workspace (Gmail SMTP)</h4>
                <p>
                    Für den automatisierten Versand von Transaktionsnachrichten (Bestellbestätigung, Versandbenachrichtigung mit Tracking-Link oder Stornierungsnachricht) nutzen wir den E-Mail-Dienst der <strong>Google Ireland Limited</strong>, Gordon House, Barrow Street, Dublin 4, Irland (Mutterkonzern: Google LLC, 1600 Amphitheatre Parkway, Mountain View, CA 94043, USA).
                </p>
                <p>
                    Hierbei werden deine E-Mail-Adresse, dein Name und die Bestelldaten über gesicherte TLS-Verbindungen an die Server von Google übermittelt, um die Nachricht an dein Postfach zuzustellen.
                </p>
                <p>
                    Die Nutzung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung zur Zusendung der Bestellbestätigung) sowie unserem berechtigten Interesse an einer zuverlässigen und schnellen E-Mail-Zustellung (Art. 6 Abs. 1 lit. f DSGVO). Die Datenübertragung in die USA wird auf die Standardvertragsklauseln (SCC) der EU-Kommission sowie die Zertifizierung von Google LLC unter dem EU-U.S. Data Privacy Framework (DPF) gestützt.
                </p>

                <h3>10. SSL- bzw. TLS-Verschlüsselung</h3>
                <p>
                    Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte, wie zum Beispiel Bestellungen oder Anfragen, die du an uns als Seitenbetreiber sendest, eine SSL- bzw. TLS-Verschlüsselung. Eine verschlüsselte Verbindung erkennst du daran, dass die Adresszeile des Browsers von „http://“ auf „https://“ wechselt und an dem Schloss-Symbol in deiner Browserzeile.
                </p>
                <p>
                    Wenn die SSL- bzw. TLS-Verschlüsselung aktiviert ist, können die Daten, die du an uns übermittelst, nicht von Dritten mitgelesen werden.
                </p>

                <h3>11. Deine Betroffenenrechte (Art. 15–22 DSGVO)</h3>
                <p>
                    Im Rahmen der geltenden gesetzlichen Bestimmungen hast du als von der Datenverarbeitung betroffene Person folgende Rechte:
                </p>
                <ul>
                    <li><strong>Recht auf Auskunft (Art. 15 DSGVO):</strong> Du hast das Recht, jederzeit unentgeltlich Auskunft über deine bei uns gespeicherten personenbezogenen Daten, deren Herkunft, Empfänger und den Zweck der Datenverarbeitung zu verlangen.</li>
                    <li><strong>Recht auf Berichtigung (Art. 16 DSGVO):</strong> Du hast das Recht, unverzüglich die Berichtigung unrichtiger oder die Vervollständigung deiner bei uns gespeicherten Daten zu verlangen.</li>
                    <li><strong>Recht auf Löschung (Art. 17 DSGVO):</strong> Du hast das Recht, die Löschung deiner personenbezogenen Daten zu verlangen, sofern keine gesetzlichen Aufbewahrungspflichten (z.&nbsp;B. steuerrechtliche Aufbewahrungspflichten nach HGB/AO) entgegenstehen.</li>
                    <li><strong>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO):</strong> Du hast das Recht, die Einschränkung der Verarbeitung deiner personenbezogenen Daten zu verlangen, wenn die Richtigkeit der Daten von dir bestritten wird, die Verarbeitung unrechtmäßig ist oder du Widerspruch eingelegt hast.</li>
                    <li><strong>Recht auf Datenübertragbarkeit (Art. 20 DSGVO):</strong> Du hast das Recht, Daten, die wir auf Grundlage deiner Einwilligung oder in Erfüllung eines Vertrags automatisiert verarbeiten, an dich oder an einen Dritten in einem gängigen, maschinenlesbaren Format aushändigen zu lassen.</li>
                    <li><strong>Widerruf deiner Einwilligung (Art. 7 Abs. 3 DSGVO):</strong> Eine bereits erteilte Einwilligung zur Datenverarbeitung kannst du jederzeit für die Zukunft widerrufen. Die Rechtmäßigkeit der bis zum Widerruf erfolgten Datenverarbeitung bleibt vom Widerruf unberührt.</li>
                </ul>

                <h4>Widerspruchsrecht gegen die Datenverarbeitung (Art. 21 DSGVO)</h4>
                <p>
                    <strong>WENN DIE DATENVERARBEITUNG AUF GRUNDLAGE VON ART. 6 ABS. 1 LIT. E ODER F DSGVO ERFOLGT, HAST DU JEDERZEIT DAS RECHT, AUS GRÜNDEN, DIE SICH AUS DEINER BESONDEREN SITUATION ERGEBEN, GEGEN DIE VERARBEITUNG DEINER PERSONENBEZOGENEN DATEN WIDERSPRUCH EINZULEGEN. WIR VERARBEITEN DIE BETROFFENEN PERSONENBEZOGENEN DATEN DANN NICHT MEHR, ES SEI DENN, WIR KÖNNEN ZWINGENDE SCHUTZWÜRDIGE GRÜNDE FÜR DIE VERARBEITUNG NACHWEISEN, DIE DEINE INTERESSEN, RECHTE UND FREIHEITEN ÜBERWIEGEN, ODER DIE VERARBEITUNG DIENT DER GELTENDMACHUNG, AUSÜBUNG ODER VERTEIDIGUNG VON RECHTSANSPRÜCHEN (WIDERSPRUCH NACH ART. 21 ABS. 1 DSGVO).</strong>
                </p>

                <h4>Beschwerderecht bei der zuständigen Aufsichtsbehörde (Art. 77 DSGVO)</h4>
                <p>
                    Im Falle von Verstößen gegen die DSGVO steht den Betroffenen ein Beschwerderecht bei einer Aufsichtsbehörde, insbesondere in dem Mitgliedstaat ihres gewöhnlichen Aufenthalts, ihres Arbeitsplatzes oder des Orts des mutmaßlichen Verstoßes zu. Die für unseren Unternehmenssitz in Hessen zuständige Datenschutz-Aufsichtsbehörde ist:<br><br>
                    <strong>Der Hessische Beauftragte für Datenschutz und Informationsfreiheit (HBDI)</strong><br>
                    Gustav-Stresemann-Ring 1<br>
                    65189 Wiesbaden<br>
                    Telefon: +49 611 1408 - 0<br>
                    Website: <a href="https://datenschutz.hessen.de" target="_blank" rel="noopener noreferrer">https://datenschutz.hessen.de</a>
                </p>
                <p>
                    Für die Ausübung deiner Rechte genügt eine formlose E-Mail an: <a href="mailto:eintrachtfrankfurt.klebekunst@gmail.com">eintrachtfrankfurt.klebekunst@gmail.com</a>.
                </p>
            </div>
        </div>
    </main>

    <footer class="brutal-footer">
        <div class="footer-brand">KLEBEKUNST</div>
        <div class="footer-links">
            <a href="impressum.php">Impressum</a>
            <a href="https://instagram.com/1899klebekunst" target="_blank">Instagram</a>
        </div>
    </footer>

    <script src="canvas.js"></script>
</body>
</html>