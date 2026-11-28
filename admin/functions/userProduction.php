<?php
const PRODUCTION_SECTIONS = ['OUTDOOR', 'INDOOR', 'DTF', 'SUBLIM', 'ENGRAVE'];

function normalizeProductionSections($value) {
    if ($value === null || $value === '') return [];
    if (is_string($value)) $value = explode(',', $value);
    if (!is_array($value)) throw new InvalidArgumentException('Bagian produksi tidak valid.');
    $selected = [];
    foreach ($value as $section) {
        if (!is_string($section)) throw new InvalidArgumentException('Bagian produksi tidak valid.');
        $section = strtoupper(trim($section));
        if (!in_array($section, PRODUCTION_SECTIONS, true)) throw new InvalidArgumentException('Bagian produksi tidak valid.');
        $selected[] = $section;
    }
    return array_values(array_intersect(PRODUCTION_SECTIONS, $selected));
}

function effectiveProductionSections($value, $role) {
    if (strtoupper(trim($role)) !== 'PRODUKSI') return [];
    $sections = normalizeProductionSections($value);
    return [$sections[0] ?? 'OUTDOOR'];
}

function requestedProductionSections($input, $role, $previous, $available) {
    if (strtoupper(trim($role)) !== 'PRODUKSI') return '';
    $supplied = array_key_exists('production_sections', $input) || array_key_exists('production_sections_present', $input);
    $sections = normalizeProductionSections($supplied ? ($input['production_sections'] ?? []) : $previous);
    if ($supplied && count($sections) > 1) throw new InvalidArgumentException('Pilih satu bagian produksi.');
    if (!$available && $sections) throw new InvalidArgumentException('Jalankan SQL pengaturan bagian produksi terlebih dahulu.');
    return implode(',', $available ? effectiveProductionSections($sections, $role) : []);
}
