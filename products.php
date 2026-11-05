<?php
// products.php
require_once __DIR__ . '/products_helper.php';

global $products;
$products = get_all_products();
$GLOBALS['products'] = $products;

if (!function_exists('get_clean_price')) {
    function get_clean_price($priceRaw) {
        return (float)str_replace(['€', ' ', ','], ['', '', '.'], (string)$priceRaw);
    }
}

if (!function_exists('resolve_product_base_id')) {
    function resolve_product_base_id($cartKey, $products) {
        $bestMatch = null;
        $bestLen = 0;
        foreach ($products as $pId => $prod) {
            $realId = isset($prod['id']) ? (string)$prod['id'] : (string)$pId;
            if ((string)$cartKey === $realId || strpos((string)$cartKey, $realId . '_') === 0) {
                if ($bestMatch === null || strlen($realId) > $bestLen) {
                    $bestMatch = $pId;
                    $bestLen = strlen($realId);
                }
            }
        }
        return $bestMatch;
    }
}

/**
 * Berechnet die Versandkosten nach 3-stufiger Staffel (Packungsanzahl + Land).
 */
if (!function_exists('calculate_shipping')) {
    function calculate_shipping($packsCount, $country = 'Deutschland') {
        $packs = (int)$packsCount;
        if ($packs <= 0) {
            return [
                'price' => 0.00,
                'label' => '',
                'formatted' => '0,00 €'
            ];
        }

        $rawCountry = (string)$country;
        $c = trim(function_exists('mb_strtolower') ? mb_strtolower($rawCountry, 'UTF-8') : strtolower($rawCountry));
        $isDomestic = ($c === 'deutschland' || $c === 'de' || $c === 'deu' || $c === 'germany');

        if ($packs <= 4) {
            $price = $isDomestic ? 1.80 : 3.30;
            $label = $isDomestic ? 'Großbrief (1–4 Packungen)' : 'Großbrief International (1–4 Packungen)';
        } elseif ($packs <= 9) {
            $price = $isDomestic ? 2.90 : 6.50;
            $label = $isDomestic ? 'Maxibrief (5–9 Packungen)' : 'Maxibrief International (5–9 Packungen)';
        } else {
            $price = $isDomestic ? 4.19 : 10.49;
            $label = $isDomestic ? '2 kg Päckchen (ab 10 Packungen)' : 'Päckchen International (ab 10 Packungen)';
        }

        return [
            'price' => $price,
            'label' => $label,
            'formatted' => number_format($price, 2, ',', '.') . ' €'
        ];
    }
}