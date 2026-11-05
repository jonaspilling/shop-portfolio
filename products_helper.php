<?php
// products_helper.php - Zentrale Verwaltung für dynamische Produkte, Bilder-Upload und Datenbasis
require_once __DIR__ . '/stock_helper.php';

if (!function_exists('get_products_file_path')) {
    function get_products_file_path(): string {
        return __DIR__ . '/products.json';
    }
}

/**
 * Standardprodukte als Fallback und Initial-Datenbasis
 */
if (!function_exists('get_default_products')) {
    function get_default_products(): array {
        return [
            'sticker1' => [
                'id'          => 'sticker1',
                'title'       => 'Waldstadion (50 Stück)',
                'price'       => 5.00,
                'image'       => 'images/sticker1.png',
                'gallery'     => [
                    'images/sticker1.png',
                    'images/sticker1-d1.png',
                    'images/sticker1-d2.png'
                ],
                'description' => "Runder Aufkleber Waldstadion-Motiv, 8cm Durchmesser. Wetterfestes Outdoor-Vinyl.",
                'size'        => 'Ø 8cm • Rund',
                'has_sizes'   => false,
                'sizes'       => [],
                'has_qty'     => true,
                'max_qty'     => 500,
                'qty_step'    => 50,
                'qty_unit'    => ' Stk.',
                'csv_suffix'  => 'Sticker_Waldstadion',
                'ref_prefix'  => 'Waldstadion',
                'drop_id'     => 'drop_1',
                'status'      => 'active'
            ],
            'sticker2' => [
                'id'          => 'sticker2',
                'title'       => 'Rebelling Kids (50 Stück)',
                'price'       => 5.00,
                'image'       => 'images/sticker2.png',
                'gallery'     => [
                    'images/sticker2.png',
                    'images/sticker2-d1.png',
                    'images/sticker2-d2.png'
                ],
                'description' => "Aufkleber Rebelling Kids-Motiv, 21x3,1cm. Wetterfestes Outdoor-Vinyl.",
                'size'        => '21 × 3,1 cm',
                'has_sizes'   => false,
                'sizes'       => [],
                'has_qty'     => true,
                'max_qty'     => 500,
                'qty_step'    => 50,
                'qty_unit'    => ' Stk.',
                'csv_suffix'  => 'Sticker_RebellingKids',
                'ref_prefix'  => 'RebellingKids',
                'drop_id'     => 'drop_1',
                'status'      => 'active'
            ],
            'sticker3' => [
                'id'          => 'sticker3',
                'title'       => 'Schwenker (50 Stück)',
                'price'       => 5.00,
                'image'       => 'images/sticker3.png',
                'gallery'     => [
                    'images/sticker3.png',
                    'images/sticker3-d1.png',
                    'images/sticker3-d2.png'
                ],
                'description' => "Aufkleber Schwenker-Motiv, 8x11,2cm. Wetterfestes Outdoor-Vinyl.",
                'size'        => '8 × 11,2 cm',
                'has_sizes'   => false,
                'sizes'       => [],
                'has_qty'     => true,
                'max_qty'     => 500,
                'qty_step'    => 50,
                'qty_unit'    => ' Stk.',
                'csv_suffix'  => 'Sticker_Schwenker',
                'ref_prefix'  => 'Schwenker',
                'drop_id'     => 'drop_1',
                'status'      => 'active'
            ]
        ];
    }
}

/**
 * Lädt alle Produkte aus products.json.
 * Erstellt products.json automatisch mit Standarddaten, falls noch nicht vorhanden.
 */
if (!function_exists('get_all_products')) {
    function get_all_products(): array {
        $file = get_products_file_path();
        if (!file_exists($file)) {
            $defaults = get_default_products();
            save_all_products($defaults);
            return $defaults;
        }

        $content = @file_get_contents($file);
        if ($content === false || trim($content) === '') {
            $defaults = get_default_products();
            save_all_products($defaults);
            return $defaults;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data)) {
            $defaults = get_default_products();
            save_all_products($defaults);
            return $defaults;
        }

        return $data;
    }
}

/**
 * Speichert alle Produkte atomar in products.json.
 */
if (!function_exists('save_all_products')) {
    function save_all_products(array $products): bool {
        $file = get_products_file_path();
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

        $json = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
 * Erstellt eine saubere, URL-sichere Produkt-ID
 */
if (!function_exists('generate_product_id')) {
    function generate_product_id(string $title, array $existingProducts): string {
        $clean = function_exists('mb_strtolower') ? mb_strtolower(trim($title), 'UTF-8') : strtolower(trim($title));
        $clean = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $clean);
        $clean = preg_replace('/[^a-z0-9]+/i', '_', $clean);
        $clean = trim($clean, '_');
        if (empty($clean)) {
            $clean = 'produkt_' . uniqid();
        }

        $base = $clean;
        $idx = 2;
        while (isset($existingProducts[$clean])) {
            $clean = $base . '_' . $idx;
            $idx++;
        }
        return $clean;
    }
}

/**
 * Speichert oder aktualisiert ein einzelnes Produkt und synchronisiert den Lagerbestand in stock.json.
 */
if (!function_exists('save_product')) {
    function save_product(array $data, ?string $originalId = null): array {
        $products = get_all_products();
        
        $pId = !empty($data['id']) ? trim($data['id']) : '';
        if (empty($pId)) {
            $pId = generate_product_id($data['title'] ?? 'produkt', $products);
        } else {
            // Slug sauber bereinigen
            $pId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $pId);
        }

        // Falls ID umbenannt wurde, alte ID aus Katalog entfernen
        if (!empty($originalId) && $originalId !== $pId && isset($products[$originalId])) {
            unset($products[$originalId]);
        }

        $existing = $products[$pId] ?? ($originalId && isset($products[$originalId]) ? $products[$originalId] : []);

        // Preis parsen
        $price = 0.0;
        if (isset($data['price'])) {
            $priceRaw = (string)$data['price'];
            $priceClean = str_replace(['€', ' ', ','], ['', '', '.'], $priceRaw);
            $price = max(0.0, (float)$priceClean);
        }

        // Größen parsen (falls angegeben z. B. "S, M, L, XL" oder Array)
        $hasSizes = !empty($data['has_sizes']);
        $sizes = [];
        if ($hasSizes) {
            if (isset($data['sizes'])) {
                if (is_array($data['sizes'])) {
                    $sizes = array_values(array_filter(array_map('trim', $data['sizes'])));
                } else {
                    $sizes = array_values(array_filter(array_map('trim', explode(',', (string)$data['sizes']))));
                }
            }
            if (empty($sizes)) {
                $hasSizes = false;
            }
        }

        // Mengenschritt
        $qtyStep = isset($data['qty_step']) ? max(1, (int)$data['qty_step']) : ($existing['qty_step'] ?? 50);

        // Bild & Galerie
        $image = !empty($data['image']) ? trim($data['image']) : ($existing['image'] ?? 'images/placeholder.png');
        $gallery = [];
        if (isset($data['gallery']) && is_array($data['gallery'])) {
            $gallery = array_values(array_filter(array_map('trim', $data['gallery'])));
        } elseif (!empty($existing['gallery']) && is_array($existing['gallery'])) {
            $gallery = $existing['gallery'];
        }
        if (empty($gallery) && !empty($image)) {
            $gallery = [$image];
        }

        $productRecord = [
            'id'          => $pId,
            'title'       => trim($data['title'] ?? ($existing['title'] ?? 'Neues Produkt')),
            'price'       => $price,
            'image'       => $image,
            'gallery'     => $gallery,
            'description' => trim($data['description'] ?? ($existing['description'] ?? '')),
            'size'        => trim($data['size'] ?? ($existing['size'] ?? '')),
            'has_sizes'   => $hasSizes,
            'sizes'       => $sizes,
            'has_qty'     => isset($data['has_qty']) ? !empty($data['has_qty']) : true,
            'max_qty'     => isset($data['max_qty']) ? max(1, (int)$data['max_qty']) : 500,
            'qty_step'    => $qtyStep,
            'qty_unit'    => trim($data['qty_unit'] ?? ($existing['qty_unit'] ?? ' Stk.')),
            'csv_suffix'  => trim($data['csv_suffix'] ?? ($existing['csv_suffix'] ?? ('Prod_' . $pId))),
            'ref_prefix'  => trim($data['ref_prefix'] ?? ($existing['ref_prefix'] ?? '')),
            'drop_id'     => !empty($data['drop_id']) ? trim($data['drop_id']) : null,
            'status'      => (!empty($data['status']) && in_array($data['status'], ['active', 'hidden'])) ? $data['status'] : 'active'
        ];

        $products[$pId] = $productRecord;
        $saved = save_all_products($products);

        // Bestandsverwaltung synchronisieren (stock.json)
        if (isset($data['stock_pieces']) || isset($data['stock_initial'])) {
            $newInitial = isset($data['stock_initial']) ? max(0, (int)$data['stock_initial']) : stock_get_initial($pId);
            $newStock = isset($data['stock_pieces']) ? max(0, (int)$data['stock_pieces']) : stock_get($pId);
            stock_set($pId, $newStock, $newInitial);
        } else {
            // Wenn Neuprodukt und noch kein Eintrag in stock.json, Standard 5.000 Stück anlegen
            $curStock = stock_get($pId);
            if ($curStock === 5000 && stock_get_initial($pId) === 5000) {
                stock_set($pId, 5000, 5000);
            }
        }

        return [
            'success' => $saved,
            'product' => $productRecord
        ];
    }
}

/**
 * Löscht ein Produkt aus products.json und stock.json
 */
if (!function_exists('delete_product')) {
    function delete_product(string $productId): bool {
        $products = get_all_products();
        if (!isset($products[$productId])) {
            return false;
        }
        unset($products[$productId]);
        $saved = save_all_products($products);
        stock_delete($productId);
        return $saved;
    }
}

/**
 * Skaliert und optimiert ein Bild mit PHP GD (falls verfügbar), erhält Orientierung und Transparenz
 */
if (!function_exists('resize_and_save_image')) {
    function resize_and_save_image(string $srcPath, string $destPath, string $ext, int $maxDim = 1600): bool {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $info = @getimagesize($srcPath);
        if (!$info) return false;

        $width = $info[0];
        $height = $info[1];
        $type = $info[2];

        $srcImg = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                if (function_exists('imagecreatefromjpeg')) $srcImg = @imagecreatefromjpeg($srcPath);
                break;
            case IMAGETYPE_PNG:
                if (function_exists('imagecreatefrompng')) $srcImg = @imagecreatefrompng($srcPath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) $srcImg = @imagecreatefromwebp($srcPath);
                break;
            case IMAGETYPE_GIF:
                if (function_exists('imagecreatefromgif')) $srcImg = @imagecreatefromgif($srcPath);
                break;
        }

        if (!$srcImg) return false;

        // EXIF Orientation korrigieren bei JPEG
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($srcPath);
            if (!empty($exif['Orientation']) && function_exists('imagerotate')) {
                switch ($exif['Orientation']) {
                    case 3: $srcImg = @imagerotate($srcImg, 180, 0); break;
                    case 6: $srcImg = @imagerotate($srcImg, -90, 0); break;
                    case 8: $srcImg = @imagerotate($srcImg, 90, 0); break;
                }
                if ($srcImg) {
                    $width = imagesx($srcImg);
                    $height = imagesy($srcImg);
                }
            }
        }

        // Zielabmessungen berechnen (proportional)
        $newW = $width;
        $newH = $height;
        if ($width > $maxDim || $height > $maxDim) {
            if ($width >= $height) {
                $newW = $maxDim;
                $newH = (int)round(($height / $width) * $maxDim);
            } else {
                $newH = $maxDim;
                $newW = (int)round(($width / $height) * $maxDim);
            }
        }

        $dstImg = imagecreatetruecolor($newW, $newH);
        if (!$dstImg) {
            imagedestroy($srcImg);
            return false;
        }

        // Transparenz für PNG und WEBP erhalten
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
            imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);
        }

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);

        $saved = false;
        switch (strtolower($ext)) {
            case '.jpg':
            case '.jpeg':
                $saved = @imagejpeg($dstImg, $destPath, 88);
                break;
            case '.png':
                $saved = @imagepng($dstImg, $destPath, 8);
                break;
            case '.webp':
                if (function_exists('imagewebp')) {
                    $saved = @imagewebp($dstImg, $destPath, 88);
                } else {
                    $saved = @imagejpeg($dstImg, $destPath, 88);
                }
                break;
            case '.gif':
                $saved = @imagegif($dstImg, $destPath);
                break;
            default:
                $saved = @imagejpeg($dstImg, $destPath, 88);
                break;
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);
        return $saved;
    }
}

/**
 * Speichert clientseitig komprimierte Base64-Bilddaten direkt als Bilddatei
 */
if (!function_exists('save_base64_image')) {
    function save_base64_image(string $base64Data, string $prefix = 'prod'): ?string {
        if (empty($base64Data) || strpos($base64Data, ';base64,') === false) {
            return null;
        }

        list($typePart, $dataPart) = explode(';', $base64Data, 2);
        $mime = '';
        if (strpos($typePart, ':') !== false) {
            list(, $mime) = explode(':', $typePart, 2);
        }
        $rawBase64 = '';
        if (strpos($dataPart, ',') !== false) {
            list(, $rawBase64) = explode(',', $dataPart, 2);
        } else {
            $rawBase64 = $dataPart;
        }

        $decoded = base64_decode($rawBase64);
        if ($decoded === false || strlen($decoded) < 10) {
            return null;
        }

        $ext = '.jpg';
        if (strpos($mime, 'png') !== false) $ext = '.png';
        elseif (strpos($mime, 'webp') !== false) $ext = '.webp';
        elseif (strpos($mime, 'gif') !== false) $ext = '.gif';

        $targetDir = __DIR__ . '/images/';
        if (!file_exists($targetDir)) @mkdir($targetDir, 0777, true);

        $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix);
        $fileName = $safePrefix . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . $ext;
        $targetPath = $targetDir . $fileName;

        $res = @file_put_contents($targetPath, $decoded);
        if ($res === false) {
            @chmod($targetDir, 0777);
            $res = @file_put_contents($targetPath, $decoded);
        }
        if ($res !== false) {
            @chmod($targetPath, 0666);
            return 'images/' . $fileName;
        }

        return null;
    }
}

/**
 * Robuster, sicherer Bilder-Upload für Produkte mit automatischer GD-Skalierung
 */
if (!function_exists('upload_product_image')) {
    function upload_product_image(array $fileArray, string $prefix = 'prod'): ?string {
        if (!isset($fileArray['error']) || $fileArray['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = $fileArray['tmp_name'] ?? '';
        if (empty($tmpName) || !file_exists($tmpName) || filesize($tmpName) === 0) {
            return null;
        }

        $allowedExts = [
            'image/jpeg'  => '.jpg',
            'image/pjpeg' => '.jpg',
            'image/png'   => '.png',
            'image/x-png' => '.png',
            'image/webp'  => '.webp',
            'image/gif'   => '.gif'
        ];

        $ext = null;
        $mime = null;

        // 1. getimagesize (zuverlässigster nativer Check)
        $imgInfo = @getimagesize($tmpName);
        if ($imgInfo && !empty($imgInfo['mime'])) {
            $mime = strtolower($imgInfo['mime']);
        }

        // 2. exif_imagetype
        if (!$mime && function_exists('exif_imagetype')) {
            $type = @exif_imagetype($tmpName);
            if ($type === IMAGETYPE_JPEG) $mime = 'image/jpeg';
            elseif ($type === IMAGETYPE_PNG) $mime = 'image/png';
            elseif ($type === IMAGETYPE_WEBP) $mime = 'image/webp';
            elseif ($type === IMAGETYPE_GIF) $mime = 'image/gif';
        }

        // 3. finfo_open
        if (!$mime && function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $m = @finfo_file($finfo, $tmpName);
                if ($m) $mime = strtolower($m);
                @finfo_close($finfo);
            }
        }

        // 4. mime_content_type
        if (!$mime && function_exists('mime_content_type')) {
            $m = @mime_content_type($tmpName);
            if ($m) $mime = strtolower($m);
        }

        // 5. Dateiendungs-Matching
        $origExt = strtolower(pathinfo($fileArray['name'] ?? '', PATHINFO_EXTENSION));
        if ($mime && isset($allowedExts[$mime])) {
            $ext = $allowedExts[$mime];
        } elseif (in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $ext = ($origExt === 'jpeg') ? '.jpg' : '.' . $origExt;
        } else {
            return null;
        }

        $targetDir = __DIR__ . '/images/';
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix);
        $fileName = $safePrefix . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . $ext;
        $targetPath = $targetDir . $fileName;

        // Zuerst mit GD optimieren & auf max 1600px skalieren
        $resized = false;
        if (function_exists('resize_and_save_image')) {
            $resized = resize_and_save_image($tmpName, $targetPath, $ext, 1600);
        }

        if (!$resized) {
            if (is_uploaded_file($tmpName)) {
                $moved = @move_uploaded_file($tmpName, $targetPath);
            } else {
                $moved = @copy($tmpName, $targetPath);
            }
            if (!$moved) return null;
        }

        @chmod($targetPath, 0666);
        return 'images/' . $fileName;
    }
}

/**
 * Mehrfach-Upload für Detailbilder / Galerie
 */
if (!function_exists('upload_product_gallery')) {
    function upload_product_gallery(array $filesArray, string $prefix = 'prod_gallery'): array {
        $uploaded = [];
        if (!isset($filesArray['name']) || !is_array($filesArray['name'])) {
            return $uploaded;
        }

        $count = count($filesArray['name']);
        for ($i = 0; $i < $count; $i++) {
            if (isset($filesArray['error'][$i]) && $filesArray['error'][$i] === UPLOAD_ERR_OK) {
                $singleFile = [
                    'name'     => $filesArray['name'][$i],
                    'type'     => $filesArray['type'][$i] ?? '',
                    'tmp_name' => $filesArray['tmp_name'][$i],
                    'error'    => $filesArray['error'][$i],
                    'size'     => $filesArray['size'][$i] ?? 0
                ];
                $path = upload_product_image($singleFile, $prefix . '_d' . ($i + 1));
                if ($path) {
                    $uploaded[] = $path;
                }
            }
        }

        return $uploaded;
    }
}

/**
 * Liefert eine Liste aller verfügbaren Produktbilder im Ordner images/ (sortiert: neueste zuerst)
 */
if (!function_exists('get_available_product_images')) {
    function get_available_product_images(): array {
        $detailed = get_available_product_images_detailed();
        return array_map(function($item) {
            return $item['path'];
        }, $detailed);
    }
}

/**
 * Liefert strukturierte Details aller verfügbaren Produktbilder (sortiert: neueste zuerst)
 */
if (!function_exists('get_available_product_images_detailed')) {
    function get_available_product_images_detailed(): array {
        $dir = __DIR__ . '/images/';
        if (!is_dir($dir)) return [];
        $files = @scandir($dir);
        if (!$files) return [];
        
        // System-, Platzhalter- und Markenlogos ausschließen, damit keine Logos als Produktbilder gewählt werden
        $ignored = [
            '.', '..', '.htaccess', 
            'favicon-black.png', 'favicon-white.png', 
            'placeholder.jpg', 'placeholder.png',
            'logo.png', 'longlogo.png', 'klebekunst.png'
        ];

        $items = [];
        $now = time();

        foreach ($files as $f) {
            if (in_array(strtolower($f), $ignored)) continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                $fullPath = $dir . $f;
                $mtime = file_exists($fullPath) ? (int)@filemtime($fullPath) : 0;
                $sizeBytes = file_exists($fullPath) ? (int)@filesize($fullPath) : 0;
                
                // Kategorisierung: Detailbild vs. Hauptmotiv
                $isDetail = (bool)preg_match('/(-d\d|_d\d|detail)/i', $f);
                $isNew = ($now - $mtime < 14 * 86400); // 14 Tage als 'Neu'

                $items[] = [
                    'path'       => 'images/' . $f,
                    'filename'   => $f,
                    'mtime'      => $mtime,
                    'date_str'   => $mtime ? date('d.m.Y', $mtime) : '',
                    'is_detail'  => $isDetail,
                    'is_new'     => $isNew,
                    'type'       => $isDetail ? 'detail' : 'main',
                    'size_human' => ($sizeBytes > 1048576) ? round($sizeBytes / 1048576, 1) . ' MB' : round($sizeBytes / 1024, 0) . ' KB'
                ];
            }
        }

        // Neueste Bilder immer an erster Stelle
        usort($items, function($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });

        return $items;
    }
}

