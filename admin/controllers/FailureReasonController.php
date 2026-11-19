<?php
require_once __DIR__ . '/../models/FailureReason.php';

class FailureReasonController {
    const FIELDS = [
        'failure_design' => 'design',
        'failure_print' => 'print',
        'failure_finishing' => 'finishing',
        'failure_cause' => 'cause'
    ];
    private $model;

    public function __construct($db) { $this->model = new FailureReason($db); }
    public function index() { return $this->model->getAll(); }

    public function resolve($data) {
        $ids = $data['failure_reason_ids'] ?? [];
        $known = $this->model->getByIds($ids);
        if (count($known) !== count($ids)) throw new InvalidArgumentException('Pilihan alasan kegagalan tidak tersedia.');
        foreach ($this->requestNames($data) as [$type, $name]) {
            $ids[] = $this->model->findOrCreate($type, $name);
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function requestNames($data) {
        $names = [];
        foreach (self::FIELDS as $field => $type) {
            $values = $data[$field] ?? [];
            if (!is_array($values)) $values = explode(',', (string)$values);
            foreach ($values as $value) {
                $name = preg_replace('/\s+/u', ' ', trim((string)$value));
                if ($name === '') continue;
                if ($name === null || !preg_match('/^.{1,255}$/usD', $name) || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                    throw new InvalidArgumentException('Nama alasan kegagalan tidak valid atau melebihi 255 karakter.');
                }
                $names[] = [$type, $name];
            }
        }
        return $names;
    }

    public function attach($items) {
        if (!$items) return [];
        $ids = [];
        foreach ($items as &$item) {
            $item['failure_reason_ids'] = json_decode($item['failure_reason_ids'] ?? '[]', true) ?: [];
            $ids = array_merge($ids, $item['failure_reason_ids']);
        }
        unset($item);

        $master = [];
        foreach ($this->model->getByIds(array_values(array_unique($ids))) as $reason) {
            $reason['failure_reason_id'] = (int)$reason['failure_reason_id'];
            $master[$reason['failure_reason_id']] = $reason;
        }
        foreach ($items as &$item) {
            $reasons = [];
            foreach ($item['failure_reason_ids'] as $id) {
                if (isset($master[$id])) $reasons[] = $master[$id];
            }
            $item['failure_reasons'] = $reasons;
            $item['failure_reason_ids'] = array_column($reasons, 'failure_reason_id');
            // Tetap menyediakan nama alasan untuk aplikasi yang belum diperbarui.
            foreach (self::FIELDS as $field => $type) {
                $names = array_column(array_filter($reasons, function ($reason) use ($type) {
                    return $reason['type'] === $type;
                }), 'name');
                $item[$field] = implode(',', $names);
            }
        }
        unset($item);
        return $items;
    }
}
