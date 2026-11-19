<?php
function moneyCents($value) {
    if (!is_scalar($value) || is_bool($value)) return null;
    if (is_float($value)) $value = number_format($value, 2, '.', '');
    if (!preg_match('/^(-?)([0-9]{1,15})(?:\.([0-9]+))?$/D', trim((string)$value), $parts)) return null;
    $fraction = str_pad($parts[3] ?? '', 3, '0');
    $cents = (int)$parts[2] * 100 + (int)substr($fraction, 0, 2);
    if ((int)$fraction[2] >= 5) $cents++;
    return $parts[1] === '-' ? -$cents : $cents;
}

function moneyDecimal($cents) {
    return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string)(abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}

function paymentInputCents($value) {
    if (!is_scalar($value) || is_bool($value) || !preg_match('/^[0-9]{1,13}(?:\.[0-9]{1,2})?$/D', trim((string)$value))) return null;
    return moneyCents($value);
}

function formatMoneyNumber($value) {
    $cents = moneyCents($value) ?? 0;
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string)intdiv(abs($cents), 100));
    $fraction = abs($cents) % 100;
    return ($cents < 0 ? '-' : '') . $whole . ($fraction ? ',' . str_pad((string)$fraction, 2, '0', STR_PAD_LEFT) : '');
}

function moneyInputDecimal($value) {
    if (!is_scalar($value) || is_bool($value)) return null;
    $value = trim((string)$value);
    if (!preg_match('/^-?[0-9]{1,13}(?:\.[0-9]{1,2})?$/D', $value)) return null;
    return moneyDecimal(moneyCents($value));
}
