<?php
// settings_helper.php - Zentrale Hilfsfunktionen für Shop-Status, Drops und Verkaufsanalyse

function get_settings_file_path() {
    return __DIR__ . '/settings.json';
}

function get_drops_file_path() {
    return __DIR__ . '/drops.json';
}

function get_orders_file_path() {
    return __DIR__ . '/bestellungen/orders.json';
}

/**
 * Lädt die allgemeinen Shop-Einstellungen
 */
function get_shop_settings() {
    $file = get_settings_file_path();
    $defaults = [
        'shop_open'       => false,
        'vip_password'    => 'Waldstadion2026',
        'active_drop_id'  => 'drop_1',
        'closed_title'    => 'AKTUELL IST ALLES AUSVERKAUFT',
        'closed_subtitle' => 'Bald folgt mehr.'
    ];

    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return array_merge($defaults, $data);
        }
    }

    return $defaults;
}

/**
 * Prüft Dateirechte für Einstellungen und Drops (für Debugging & Validierung)
 * Versucht auch automatisch, Schreibrechte via chmod oder Re-Creation zu heilen.
 */
function check_system_file_permissions() {
    $settingsFile = get_settings_file_path();
    $dropsFile = get_drops_file_path();
    $productsFile = __DIR__ . '/products.json';
    $stockFile = __DIR__ . '/stock.json';
    $ordersFile = get_orders_file_path();
    $ordersDir = dirname($ordersFile);
    $dir = dirname($settingsFile);
    $errors = [];

    // Ordner sicherstellen
    if (!file_exists($ordersDir)) {
        @mkdir($ordersDir, 0777, true);
    }
    if (file_exists($ordersDir) && !is_writable($ordersDir)) {
        @chmod($ordersDir, 0777);
    }

    // 1. Automatischer Reparaturversuch per chmod
    foreach ([$settingsFile, $dropsFile, $productsFile, $stockFile, $ordersFile] as $f) {
        if (file_exists($f) && !is_writable($f)) {
            @chmod($f, 0666);
        }
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
    }
    if (file_exists($ordersDir) && !is_writable($ordersDir)) {
        @chmod($ordersDir, 0777);
    }

    // 2. Automatischer Reparaturversuch per Re-Creation (wenn Ordner beschreibbar)
    foreach ([$settingsFile, $dropsFile, $productsFile, $stockFile, $ordersFile] as $f) {
        if (file_exists($f) && !is_writable($f) && is_writable($dir)) {
            $content = @file_get_contents($f);
            if ($content !== false && @unlink($f)) {
                @file_put_contents($f, $content);
                @chmod($f, 0666);
            }
        }
    }

    // 2b. Automatischer Reparaturversuch für den Ordner bestellungen/ (falls vom Webserver neu erstellt werden kann)
    if (file_exists($ordersDir) && !is_writable($ordersDir) && is_writable($dir)) {
        @chmod($ordersDir, 0777);
        if (!is_writable($ordersDir)) {
            $savedOrders = file_exists($ordersFile) ? @file_get_contents($ordersFile) : null;
            $savedHtaccess = file_exists($ordersDir . '/.htaccess') ? @file_get_contents($ordersDir . '/.htaccess') : null;
            $tmpBackup = $ordersDir . '_tmp_perm_' . time();
            if (@rename($ordersDir, $tmpBackup)) {
                if (@mkdir($ordersDir, 0777, true)) {
                    @chmod($ordersDir, 0777);
                    if ($savedOrders !== null) {
                        @file_put_contents($ordersFile, $savedOrders);
                        @chmod($ordersFile, 0666);
                    }
                    if ($savedHtaccess !== null) {
                        @file_put_contents($ordersDir . '/.htaccess', $savedHtaccess);
                    }
                    @unlink($tmpBackup . '/orders.json');
                    @unlink($tmpBackup . '/.htaccess');
                    @rmdir($tmpBackup);
                } else {
                    @rename($tmpBackup, $ordersDir);
                }
            }
        }
    }

    // 2c. Automatischer Reparaturversuch für den Ordner images/
    $imagesDir = __DIR__ . '/images';
    if (!file_exists($imagesDir)) {
        @mkdir($imagesDir, 0777, true);
    }
    if (file_exists($imagesDir) && !is_writable($imagesDir)) {
        @chmod($imagesDir, 0777);
    }

    // Cache leeren, damit chmod- und unlink-Änderungen sofort wirksam sind
    if (function_exists('clearstatcache')) {
        clearstatcache();
    }

    // 3. Status überprüfen
    if (file_exists($settingsFile) && !is_writable($settingsFile)) {
        $errors[] = "settings.json ist schreibgeschützt (Rechte auf 666 / 777 setzen).";
    }
    if (file_exists($dropsFile) && !is_writable($dropsFile)) {
        $errors[] = "drops.json ist schreibgeschützt (Rechte auf 666 / 777 setzen).";
    }
    if (file_exists($productsFile) && !is_writable($productsFile)) {
        $errors[] = "products.json ist schreibgeschützt (Rechte auf 666 / 777 setzen).";
    }
    if (file_exists($stockFile) && !is_writable($stockFile)) {
        $errors[] = "stock.json ist schreibgeschützt (Rechte auf 666 / 777 setzen).";
    }
    if (file_exists($ordersFile) && !is_writable($ordersFile)) {
        $errors[] = "bestellungen/orders.json ist schreibgeschützt (Rechte auf 666 / 777 setzen).";
    }
    if (!file_exists($ordersDir) || !is_writable($ordersDir)) {
        $errors[] = "Ordner 'bestellungen/' ist nicht beschreibbar (Rechte auf 777 setzen).";
    }

    return $errors;
}

/**
 * Speichert die Shop-Einstellungen
 */
function save_shop_settings(array $settings) {
    $file = get_settings_file_path();
    $dir = dirname($file);
    if (!file_exists($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (file_exists($file) && !is_writable($file)) {
        @chmod($file, 0666);
        if (!is_writable($file) && is_writable($dir)) {
            @unlink($file);
        }
    }
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    // file_put_contents mit LOCK_EX, Fallback ohne Lock falls Filesystem kein Locking unterstützt
    $res = @file_put_contents($file, $json, LOCK_EX);
    if ($res === false) {
        $res = @file_put_contents($file, $json);
    }
    if ($res !== false) {
        @chmod($file, 0666);
        return true;
    }
    return false;
}

/**
 * Lädt alle angelegten Drops
 */
function get_all_drops() {
    $file = get_drops_file_path();
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

/**
 * Speichert alle Drops
 */
function save_all_drops(array $drops) {
    $file = get_drops_file_path();
    $dir = dirname($file);
    if (!file_exists($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (file_exists($file) && !is_writable($file)) {
        @chmod($file, 0666);
        if (!is_writable($file) && is_writable($dir)) {
            @unlink($file);
        }
    }
    $json = json_encode(array_values($drops), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $res = @file_put_contents($file, $json, LOCK_EX);
    if ($res === false) {
        $res = @file_put_contents($file, $json);
    }
    if ($res !== false) {
        @chmod($file, 0666);
        return true;
    }
    return false;
}

/**
 * Ermittelt den aktuell aktiven Drop
 */
function get_active_drop() {
    $settings = get_shop_settings();
    $activeDropId = $settings['active_drop_id'] ?? null;
    $drops = get_all_drops();

    if ($activeDropId) {
        foreach ($drops as $drop) {
            if ($drop['id'] === $activeDropId) {
                return $drop;
            }
        }
    }

    // Fallback: Drop mit status === 'active' suchen
    foreach ($drops as $drop) {
        if (($drop['status'] ?? '') === 'active') {
            return $drop;
        }
    }

    return null;
}

/**
 * Legt einen neuen Drop an
 * Erwartet Name, Startdatum, Enddatum und Aktiv-Schalter
 */
function create_drop($name, $startDate = null, $endDate = null, $setActive = true) {
    $drops = get_all_drops();
    $startStr = !empty($startDate) ? trim($startDate) : date('Y-m-d');
    $endStr = !empty($endDate) ? trim($endDate) : null;
    $dropId = 'drop_' . uniqid();

    if ($setActive) {
        foreach ($drops as &$d) {
            if (($d['status'] ?? '') === 'active') {
                $d['status'] = 'ended';
                if (empty($d['end_date'])) {
                    $d['end_date'] = date('Y-m-d');
                }
            }
        }
        unset($d);
    }

    $newDrop = [
        'id'         => $dropId,
        'name'       => trim($name),
        'date'       => $startStr,
        'start_date' => $startStr,
        'end_date'   => $endStr,
        'status'     => $setActive ? 'active' : 'ended',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $drops[] = $newDrop;
    $saveDrops = save_all_drops($drops);

    if ($setActive) {
        $settings = get_shop_settings();
        $settings['active_drop_id'] = $dropId;
        save_shop_settings($settings);
    }

    return $saveDrops ? $newDrop : false;
}

/**
 * Markiert den aktiven Drop als beendet
 */
function end_active_drop() {
    $drops = get_all_drops();
    $settings = get_shop_settings();
    $activeDropId = $settings['active_drop_id'] ?? null;

    $updated = false;
    foreach ($drops as &$drop) {
        if (($drop['status'] ?? '') === 'active' || ($activeDropId && $drop['id'] === $activeDropId)) {
            $drop['status'] = 'ended';
            $drop['end_date'] = date('Y-m-d');
            $updated = true;
        }
    }
    unset($drop);

    if ($updated) {
        save_all_drops($drops);
        $settings['active_drop_id'] = null;
        save_shop_settings($settings);
    }

    return $updated;
}

/**
 * Setzt einen bestehenden Drop als aktiven Drop
 */
function set_active_drop($dropId) {
    $drops = get_all_drops();
    $found = false;

    foreach ($drops as &$drop) {
        if ($drop['id'] === $dropId) {
            $drop['status'] = 'active';
            $drop['end_date'] = null;
            $found = true;
        } else {
            if (($drop['status'] ?? '') === 'active') {
                $drop['status'] = 'ended';
                if (empty($drop['end_date'])) {
                    $drop['end_date'] = date('Y-m-d');
                }
            }
        }
    }
    unset($drop);

    if ($found) {
        save_all_drops($drops);
        $settings = get_shop_settings();
        $settings['active_drop_id'] = $dropId;
        save_shop_settings($settings);
    }

    return $found;
}

/**
 * Löscht einen Drop
 */
function delete_drop($dropId) {
    $drops = get_all_drops();
    $remaining = [];
    $wasActive = false;
    $found = false;

    foreach ($drops as $drop) {
        if ($drop['id'] === $dropId) {
            $found = true;
            if (($drop['status'] ?? '') === 'active') {
                $wasActive = true;
            }
        } else {
            $remaining[] = $drop;
        }
    }

    if (!$found) {
        return false;
    }

    $saved = save_all_drops($remaining);

    $settings = get_shop_settings();
    if ($wasActive || ($settings['active_drop_id'] ?? '') === $dropId) {
        $settings['active_drop_id'] = null;
        save_shop_settings($settings);
    }

    return $saved;
}

/**
 * Liest alle Bestellungen aus bestellungen/orders.json
 */
function get_all_orders() {
    $file = get_orders_file_path();
    $orders = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $orders = $data;
        }
    }
    // Sicherheitsnetz: Falls Bestellungen im Fallback-Speicher liegen, zusammenführen
    $fallbackFile = __DIR__ . '/orders_fallback.json';
    if (file_exists($fallbackFile)) {
        $fbData = json_decode(file_get_contents($fallbackFile), true);
        if (is_array($fbData)) {
            $existingIds = array_column($orders, 'id');
            foreach ($fbData as $o) {
                if (!in_array($o['id'] ?? '', $existingIds)) {
                    $orders[] = $o;
                }
            }
        }
    }
    return $orders;
}

/**
 * Speichert alle Bestellungen atomar in bestellungen/orders.json
 */
function save_all_orders(array $orders): bool {
    $file = get_orders_file_path();
    $dir = dirname($file);
    if (!file_exists($dir)) {
        @mkdir($dir, 0777, true);
    }
    @chmod($dir, 0777);

    if (file_exists($file) && !is_writable($file)) {
        @chmod($file, 0666);
        if (!is_writable($file) && is_writable($dir)) {
            @unlink($file);
        }
    }

    $json = json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $res = @file_put_contents($file, $json, LOCK_EX);
    if ($res === false) {
        $res = @file_put_contents($file, $json);
    }
    if ($res !== false) {
        @chmod($file, 0666);
        return true;
    }

    // Notfall-Fallback in den Hauptordner
    $fallbackFile = __DIR__ . '/orders_fallback.json';
    $fbRes = @file_put_contents($fallbackFile, $json, LOCK_EX);
    if ($fbRes !== false) {
        @chmod($fallbackFile, 0666);
        return true;
    }
    return false;
}

/**
 * Fügt eine neue Bestellung atomar hinzu (mit Notfall-Fallback)
 */
function add_order(array $order): bool {
    $file = get_orders_file_path();
    $dir = dirname($file);
    if (!file_exists($dir)) {
        @mkdir($dir, 0777, true);
    }
    @chmod($dir, 0777);

    if (file_exists($file) && !is_writable($file)) {
        @chmod($file, 0666);
    }

    $fp = @fopen($file, 'c+');
    if ($fp) {
        $success = false;
        if (flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $orders = json_decode($raw, true);
            if (!is_array($orders)) {
                $orders = [];
            }
            $orders[] = $order;
            $json = json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            ftruncate($fp, 0);
            rewind($fp);
            $written = fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            $success = ($written !== false);
        }
        fclose($fp);

        if ($success) {
            @chmod($file, 0666);
            return true;
        }
    }

    // 2. Versuch: Normales Speichern
    $orders = get_all_orders();
    $orders[] = $order;
    if (save_all_orders($orders)) {
        return true;
    }

    // 3. Notfall-Versuch: Direkt in orders_fallback.json im Hauptverzeichnis sichern
    $fallbackFile = __DIR__ . '/orders_fallback.json';
    $fbOrders = file_exists($fallbackFile) ? json_decode(file_get_contents($fallbackFile), true) : [];
    if (!is_array($fbOrders)) $fbOrders = [];
    $fbOrders[] = $order;
    $fbRes = @file_put_contents($fallbackFile, json_encode(array_values($fbOrders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($fbRes !== false) {
        @chmod($fallbackFile, 0666);
        return true;
    }

    return false;
}

/**
 * Extrahiert den numerischen Gesamtbetrag einer Bestellung
 */
function parse_order_total_amount($order) {
    if (isset($order['total_numeric']) && is_numeric($order['total_numeric'])) {
        return (float)$order['total_numeric'];
    }
    if (isset($order['gesamt'])) {
        // z. B. "15,00 €" -> 15.00
        $clean = preg_replace('/[^\d,.]/', '', (string)$order['gesamt']);
        if (strpos($clean, ',') !== false) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }
        return (float)$clean;
    }
    return 0.0;
}

/**
 * Weist eine Bestellung einem Drop zu
 */
function resolve_order_drop_id($order, array $drops) {
    if (!empty($order['drop_id'])) {
        foreach ($drops as $drop) {
            if ($drop['id'] === $order['drop_id']) {
                return $drop['id'];
            }
        }
    }

    // Fallback: Matching anhand des Bestelldatums ("13.10.2026 14:30")
    if (!empty($order['date']) && !empty($drops)) {
        $orderTs = null;
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $order['date'], $m)) {
            $orderTs = strtotime("{$m[3]}-{$m[2]}-{$m[1]}");
        }
        if ($orderTs) {
            foreach ($drops as $drop) {
                $startTs = !empty($drop['date']) ? strtotime($drop['date']) : 0;
                $endTs = !empty($drop['end_date']) ? strtotime($drop['end_date'] . ' 23:59:59') : PHP_INT_MAX;
                if ($orderTs >= $startTs && $orderTs <= $endTs) {
                    return $drop['id'];
                }
            }
        }
    }

    return 'unassigned';
}

/**
 * Parst die bestellten Artikel einer Bestellung strukturiert
 */
function parse_order_products($order, array $productsList) {
    // 1. Wenn strukturierte Items vorhanden sind:
    if (!empty($order['items']) && is_array($order['items'])) {
        $parsed = [];
        foreach ($order['items'] as $it) {
            $pId = $it['product_id'] ?? $it['id'] ?? 'unknown';
            $qty = (int)($it['qty'] ?? 1);
            $parsed[] = [
                'id'       => $pId,
                'title'    => $it['title'] ?? ($productsList[$pId]['title'] ?? $pId),
                'size'     => $it['size'] ?? '-',
                'qty'      => $qty,
                'price'    => (float)($it['price'] ?? ($productsList[$pId]['price'] ?? 0))
            ];
        }
        return $parsed;
    }

    // 2. Fallback: Zeilenweises Parsen aus dem Artikel-String $order['artikel']
    $items = [];
    $rawText = $order['artikel'] ?? '';
    $lines = explode("\n", $rawText);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, 'Gutscheincode') !== false) continue;

        // Bsp: "- 50 Stk. Waldstadion (50 Stück) (-)" oder "- 1x Shirt Adler (M)"
        $matched = false;
        if (preg_match('/^-\s*(\d+)\s*(?:Stk\.|x)?\s*(.*?)\s*\(([^)]*)\)$/i', $line, $matches)) {
            $qty = (int)$matches[1];
            $titleRaw = trim($matches[2]);
            $size = isset($matches[3]) ? trim($matches[3]) : '-';

            // passendes Produkt aus $productsList suchen
            $foundId = null;
            $foundPrice = 0.0;
            $foundTitle = $titleRaw;

            foreach ($productsList as $pKey => $p) {
                if (stripos($titleRaw, $p['title']) !== false || stripos($p['title'], $titleRaw) !== false) {
                    $foundId = $pKey;
                    $foundPrice = (float)($p['price'] ?? 0);
                    $foundTitle = $p['title'];
                    break;
                }
            }

            if ($foundId === null) {
                $foundId = 'other';
            }

            $items[] = [
                'id'    => $foundId,
                'title' => $foundTitle,
                'size'  => $size,
                'qty'   => $qty,
                'price' => $foundPrice
            ];
            $matched = true;
        }

        if (!$matched && !empty($line)) {
            $items[] = [
                'id'    => 'unknown',
                'title' => ltrim($line, '- '),
                'size'  => '-',
                'qty'   => 1,
                'price' => 0.0
            ];
        }
    }

    return $items;
}

/**
 * Berechnet vollständige Verkaufsstatistiken und KPIs
 */
function get_sales_analytics($filterDropId = 'all') {
    global $products;
    if (!isset($products) || !is_array($products)) {
        require_once __DIR__ . '/products.php';
    }
    $catalog = (isset($products) && is_array($products)) ? $products : [];

    $drops = get_all_drops();
    $dropsById = [];
    foreach ($drops as $d) {
        $dropsById[$d['id']] = $d;
    }

    $allOrders = get_all_orders();

    $totalRevenueAll = 0.0;
    $totalOrdersCountAll = count($allOrders);
    $totalUnitsAll = 0;

    // Aggregation pro Drop vorbereiten
    $dropStats = [];
    foreach ($drops as $d) {
        $dropStats[$d['id']] = [
            'drop'        => $d,
            'order_count' => 0,
            'revenue'     => 0.0,
            'units'       => 0,
        ];
    }
    $dropStats['unassigned'] = [
        'drop'        => ['id' => 'unassigned', 'name' => 'Ohne Drop / Ältere Bestellungen', 'status' => 'ended', 'date' => '-'],
        'order_count' => 0,
        'revenue'     => 0.0,
        'units'       => 0,
    ];

    $filteredOrders = [];
    $filteredRevenue = 0.0;
    $filteredUnits = 0;

    // Produkt-Statistiken vorbereiten
    $productStats = [];
    foreach ($catalog as $pId => $p) {
        $productStats[$pId] = [
            'id'             => $pId,
            'title'          => $p['title'],
            'image'          => $p['image'] ?? '',
            'price'          => (float)($p['price'] ?? 0),
            'qty_step'       => $p['qty_step'] ?? 1,
            'qty_unit'       => $p['qty_unit'] ?? 'Stk.',
            'has_sizes'      => !empty($p['has_sizes']),
            'total_qty'      => 0,
            'total_units'    => 0,
            'revenue'        => 0.0,
            'sizes'          => []
        ];
    }

    foreach ($allOrders as $order) {
        $orderDropId = resolve_order_drop_id($order, $drops);
        $orderTotal = parse_order_total_amount($order);
        $orderItems = parse_order_products($order, $catalog);

        $orderUnits = 0;
        foreach ($orderItems as $item) {
            $orderUnits += $item['qty'];
        }

        // Drop Aggregation
        if (isset($dropStats[$orderDropId])) {
            $dropStats[$orderDropId]['order_count']++;
            $dropStats[$orderDropId]['revenue'] += $orderTotal;
            $dropStats[$orderDropId]['units'] += $orderUnits;
        }

        $totalRevenueAll += $orderTotal;
        $totalUnitsAll += $orderUnits;

        // Prüfen ob Bestellung in aktuellem Filter liegt
        $match = false;
        if ($filterDropId === 'all') {
            $match = true;
        } elseif ($filterDropId === 'unassigned' && $orderDropId === 'unassigned') {
            $match = true;
        } elseif ($filterDropId === $orderDropId) {
            $match = true;
        }

        if ($match) {
            $filteredOrders[] = array_merge($order, [
                'resolved_drop_id'   => $orderDropId,
                'resolved_drop_name' => $dropsById[$orderDropId]['name'] ?? 'Ohne Drop',
                'parsed_total'       => $orderTotal,
                'parsed_items'       => $orderItems
            ]);
            $filteredRevenue += $orderTotal;
            $filteredUnits += $orderUnits;

            // Produkt-Zahlen für den ausgewählten Filter akkumulieren
            foreach ($orderItems as $it) {
                $pId = $it['id'];
                if (!isset($productStats[$pId])) {
                    $productStats[$pId] = [
                        'id'          => $pId,
                        'title'       => $it['title'],
                        'image'       => '',
                        'price'       => (float)$it['price'],
                        'qty_step'    => 1,
                        'qty_unit'    => 'x',
                        'has_sizes'   => false,
                        'total_qty'   => 0,
                        'total_units' => 0,
                        'revenue'     => 0.0,
                        'sizes'       => []
                    ];
                }

                $productStats[$pId]['total_qty'] += $it['qty'];
                $productStats[$pId]['total_units'] += $it['qty'];
                $productStats[$pId]['revenue'] += ($it['qty'] * $it['price']);

                $sz = !empty($it['size']) && $it['size'] !== '-' ? $it['size'] : 'Standard';
                if (!isset($productStats[$pId]['sizes'][$sz])) {
                    $productStats[$pId]['sizes'][$sz] = 0;
                }
                $productStats[$pId]['sizes'][$sz] += $it['qty'];
            }
        }
    }

    // Anteil am Gesamtumsatz berechnen
    foreach ($productStats as &$ps) {
        $ps['percent'] = ($filteredRevenue > 0) ? round(($ps['revenue'] / $filteredRevenue) * 100, 1) : 0;
    }
    unset($ps);

    // Unassigned nur anzeigen, falls dort Bestellungen existieren
    if ($dropStats['unassigned']['order_count'] === 0 && $filterDropId !== 'unassigned') {
        unset($dropStats['unassigned']);
    }

    $filteredCount = count($filteredOrders);
    $avgOrderValue = ($filteredCount > 0) ? ($filteredRevenue / $filteredCount) : 0.0;

    return [
        'filter_drop_id'    => $filterDropId,
        'drops'             => $drops,
        'active_drop'       => get_active_drop(),
        'drops_summary'     => $dropStats,
        'filtered_orders'   => $filteredOrders,
        'kpi'               => [
            'total_revenue'   => $filteredRevenue,
            'total_orders'    => $filteredCount,
            'total_units'     => $filteredUnits,
            'avg_order_value' => $avgOrderValue
        ],
        'products_stats'    => $productStats,
        'overall'           => [
            'total_revenue' => $totalRevenueAll,
            'total_orders'  => $totalOrdersCountAll,
            'total_units'   => $totalUnitsAll
        ]
    ];
}
