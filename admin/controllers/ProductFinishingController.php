<?php
require_once __DIR__ . '/../models/ProductFinishing.php';
require_once __DIR__ . '/ProductLaserConversionController.php';

class ProductFinishingController {
    private $model;
    public function __construct($db) { $this->model = new ProductFinishing($db); }
    public function isAvailable() { return $this->model->isAvailable(); }
    public function linksByStore($store) { return $this->model->linksByStore($store); }

    public function getByProduct($store, $product, $item = 0) {
        $rows = $this->model->getByProduct($store, $product);
        $previous = $this->model->previousIds($store, $product, $item);
        $extra = array_values(array_diff($previous, array_column($rows, 'finishing_id')));
        return array_merge($rows, $this->model->finishingsByIds($store, $extra));
    }

    public function validate($store, $product, $finishing, $item = 0) {
        $ids = ProductLaserConversionController::finishingIds($finishing);
        if (count($this->model->finishingsByIds($store, $ids)) !== count($ids)) {
            throw new InvalidArgumentException('Ada finishing yang sudah dihapus atau tidak tersedia. Muat ulang data produk.');
        }
        $row = $this->model->product($store, $product);
        if (!$row || $row['finishing_mode'] !== 'PRODUCT') return;
        $allowed = array_merge(array_column($this->model->getByProduct($store, $product), 'finishing_id'), $this->model->previousIds($store, $product, $item));
        if (array_diff(ProductLaserConversionController::finishingIds($finishing), $allowed)) {
            throw new InvalidArgumentException('Finishing tidak tersedia untuk produk ini. Lepas finishing tersebut atau perbarui aplikasi.');
        }
    }
}
