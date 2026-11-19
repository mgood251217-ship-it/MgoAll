<?php
function productStockValue($value) {
    if (!is_scalar($value) || is_bool($value)) return null;
    $value = trim((string)$value);
    if ($value === '') $value = '0';
    return preg_match('/^-?[0-9]{1,11}(?:\.[0-9]{1,4})?$/D', $value) ? $value : null;
}
