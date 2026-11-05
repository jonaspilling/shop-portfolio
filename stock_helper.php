<?php
/**
 * stock_helper.php
 * Zentrale Bestandsverwaltung (Stock Management) für Klebekunst.
 * Liest/schreibt stock.json mit atomarem File-Locking gegen Race Conditions / Überbuchung.
 */

if (!defined('STOCK_FILE')) {
    define('STOCK_FILE', __DIR__ . '/stock.json');
}

/**
 * Lädt die aktuelle stock.json.
 * @return array<string, array{stock: int, initial: int}>
 */
if (!function_exists('stock_load')) {
    function stock_load(): array {
        if (!file_exists(STOCK_FILE)) {
            return stock_init_defaults();
        }
        $raw = @file_get_contents(STOCK_FILE);
        if ($raw === false || trim($raw) === '') {
            return stock_init_defaults();
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return stock_init_defaults();
        }
        return $data;
    }
}

/**
 * Initialisiert Standardbestand (5.000 Stück pro Artikel)
 */
if (!function_exists('stock_init_defaults')) {
    function stock_init_defaults(): array {
        $defaults = [
            'sticker1' => ['stock' => 5000, 'initial' => 5000],
            'sticker2' => ['stock' => 5000, 'initial' => 5000],
            'sticker3' => ['stock' => 5000, 'initial' => 5000]
        ];
        stock_save($defaults);
        return $defaults;
    }
}

/**
 * Speichert das Stock-Array in stock.json (atomar mit Lock).
 */
if (!function_exists('stock_save')) {
    function stock_save(array $data): bool {
        $file = STOCK_FILE;
        $dir = dirname($file);
        if (!file_exists($dir)) @mkdir($dir, 0777, true);
        if (file_exists($file) && !is_writable($file)) {
            @chmod($file, 0666);
            if (!is_writable($file) && is_writable($dir)) {
                @unlink($file);
            }
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;

        $res = @file_put_contents($file, $json, LOCK_EX);
        if ($res === false) {
            $res = @file_put_contents($file, $json);
        }
        if ($res !== false) {
            @chmod($file, 0666);
            if (function_exists('clearstatcache')) {
                clearstatcache(true, $file);
            }
            return true;
        }
        return false;
    }
}

/**
 * Gibt den aktuellen Restbestand in Stück für ein Produkt zurück.
 * @param string $productId  z. B. 'sticker1'
 * @return int  Restbestand (>= 0)
 */
if (!function_exists('stock_get')) {
    function stock_get(string $productId): int {
        $data = stock_load();
        if (!isset($data[$productId])) {
            return 5000;
        }
        if (is_numeric($data[$productId])) {
            return max(0, (int)$data[$productId]);
        }
        return max(0, (int)($data[$productId]['stock'] ?? 5000));
    }
}

/**
 * Gibt den Initialbestand in Stück für ein Produkt zurück.
 */
if (!function_exists('stock_get_initial')) {
    function stock_get_initial(string $productId): int {
        $data = stock_load();
        if (!isset($data[$productId])) return 5000;
        if (is_numeric($data[$productId])) return 5000;
        return (int)($data[$productId]['initial'] ?? 5000);
    }
}

/**
 * Ermittelt die Anzahl noch bestellbarer Packungen für ein Produkt.
 * @param string $productId
 * @param int $qtyStep  Stückzahl pro Packung (z. B. 50)
 * @return int
 */
if (!function_exists('stock_get_available_packs')) {
    function stock_get_available_packs(string $productId, int $qtyStep = 1): int {
        $step = max(1, $qtyStep);
        $currentPieces = stock_get($productId);
        return (int)floor($currentPieces / $step);
    }
}

/**
 * Prüft ob eine bestimmte Anzahl an Packungen noch verfügbar ist.
 */
if (!function_exists('stock_is_pack_available')) {
    function stock_is_pack_available(string $productId, int $packs = 1, int $qtyStep = 1): bool {
        if ($packs <= 0) return true;
        return stock_get_available_packs($productId, $qtyStep) >= $packs;
    }
}

/**
 * Gibt alle Stock-Daten aggregiert zurück.
 * @return array<string, array>
 */
if (!function_exists('stock_get_all')) {
    function stock_get_all(): array {
        $data = stock_load();
        
        // Produkte sicher laden ohne Re-Inklusion
        global $products;
        if (!isset($products) || !is_array($products)) {
            require_once __DIR__ . '/products.php';
        }
        $catalog = (isset($products) && is_array($products)) ? $products : [];

        $allProductIds = array_unique(array_merge(array_keys($data), array_keys($catalog)));
        $result = [];

        foreach ($allProductIds as $pid) {
            $entry = $data[$pid] ?? null;
            $initial = 5000;
            $stock = 5000;

            if (is_array($entry)) {
                $initial = (int)($entry['initial'] ?? 5000);
                $stock = max(0, (int)($entry['stock'] ?? 5000));
            } elseif (is_numeric($entry)) {
                $stock = max(0, (int)$entry);
            }

            $prodInfo = $catalog[$pid] ?? [];
            $qtyStep = isset($prodInfo['qty_step']) ? max(1, (int)$prodInfo['qty_step']) : 50;
            $title = $prodInfo['title'] ?? ucfirst($pid);

            $sold = max(0, $initial - $stock);
            $availablePacks = (int)floor($stock / $qtyStep);
            $soldPacks = (int)floor($sold / $qtyStep);
            $initialPacks = (int)floor($initial / $qtyStep);
            $percentRemaining = ($initial > 0) ? round(($stock / $initial) * 100, 1) : 0;
            $percentSold = ($initial > 0) ? round(($sold / $initial) * 100, 1) : 0;

            $result[$pid] = [
                'id'                => $pid,
                'title'             => $title,
                'stock'             => $stock,
                'initial'           => $initial,
                'sold'              => $sold,
                'qty_step'          => $qtyStep,
                'available_packs'   => $availablePacks,
                'sold_packs'        => $soldPacks,
                'initial_packs'     => $initialPacks,
                'is_sold_out'       => ($availablePacks <= 0),
                'percent_remaining' => $percentRemaining,
                'percent_sold'      => $percentSold
            ];
        }

        return $result;
    }
}

/**
 * Zieht Stückzahl vom Bestand ab (mit atomarem Flock).
 * @param string $productId
 * @param int $pieces
 * @return bool
 */
if (!function_exists('stock_deduct')) {
    function stock_deduct(string $productId, int $pieces): bool {
        if ($pieces <= 0) return true;

        $fp = @fopen(STOCK_FILE, 'c+');
        if (!$fp) return false;

        $success = false;
        if (flock($fp, LOCK_EX)) {
            $raw  = stream_get_contents($fp);
            $data = json_decode($raw, true);
            if (!is_array($data)) $data = [];

            if (isset($data[$productId])) {
                $current = is_array($data[$productId]) 
                    ? (int)($data[$productId]['stock'] ?? 5000)
                    : (int)$data[$productId];
                
                $init = is_array($data[$productId]) 
                    ? (int)($data[$productId]['initial'] ?? 5000)
                    : 5000;

                $newStock = max(0, $current - $pieces);
                $data[$productId] = ['stock' => $newStock, 'initial' => $init];
                $success = true;
            } else {
                $data[$productId] = ['stock' => max(0, 5000 - $pieces), 'initial' => 5000];
                $success = true;
            }

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $success;
    }
}

/**
 * Zieht Packungen vom Bestand ab.
 */
if (!function_exists('stock_deduct_packs')) {
    function stock_deduct_packs(string $productId, int $packs, int $qtyStep = 1): bool {
        $pieces = $packs * max(1, $qtyStep);
        return stock_deduct($productId, $pieces);
    }
}

/**
 * Gibt Bestandsinfos für API-Aufrufe zurück.
 */
if (!function_exists('stock_get_all_for_api')) {
    function stock_get_all_for_api(): array {
        return stock_get_all();
    }
}

/**
 * Setzt oder aktualisiert den Bestand für ein Produkt in stock.json.
 * @param string $productId
 * @param int $stock Aktueller Restbestand
 * @param int|null $initial Maximaler Anfangsbestand (falls null, wird bisheriger Wert oder $stock beibehalten)
 * @return bool
 */
if (!function_exists('stock_set')) {
    function stock_set(string $productId, int $stock, ?int $initial = null): bool {
        $data = stock_load();

        $curInit = 5000;
        if (isset($data[$productId]) && is_array($data[$productId])) {
            $curInit = (int)($data[$productId]['initial'] ?? 5000);
        }

        $newInit = ($initial !== null) ? max(0, (int)$initial) : $curInit;
        $newStock = max(0, (int)$stock);

        $data[$productId] = [
            'stock'   => $newStock,
            'initial' => $newInit
        ];

        return stock_save($data);
    }
}

/**
 * Löscht ein Produkt aus stock.json.
 */
if (!function_exists('stock_delete')) {
    function stock_delete(string $productId): bool {
        $file = STOCK_FILE;
        if (!file_exists($file)) return true;
        if (!is_writable($file)) @chmod($file, 0666);

        $fp = @fopen($file, 'c+');
        if (!$fp) return false;

        $success = false;
        if (flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data[$productId])) {
                unset($data[$productId]);
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                ftruncate($fp, 0);
                rewind($fp);
                $written = fwrite($fp, $json);
                fflush($fp);
                $success = ($written !== false);
            } else {
                $success = true;
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $success;
    }
}
