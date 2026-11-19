<?php
function productPriceValues($data) {
    $prices = [];
    foreach (['price', 'reasonable_price', 'failed_price'] as $field) {
        $value = $data[$field] ?? '0';
        if (!is_scalar($value) || is_bool($value)) return null;
        $value = trim((string)$value);
        if ($value === '') $value = '0';
        if (!preg_match('/^[0-9]{1,13}(?:\.[0-9]{1,2})?$/D', $value)) return null;
        $prices[] = $value;
    }
    return $prices;
}
