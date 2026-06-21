<?php
function renderTable($config = []) {
    ob_start();
    
    $data         = $config['data'] ?? [];
    $emptyMsg     = $config['empty_message'] ?? 'Tidak ada data.';
    $tableClass   = $config['table_class'] ?? 'table table-bordered table-striped';
    $theadClass   = $config['thead_class'] ?? 'table-primary';
    $tbodyTrClass = $config['tbody_tr_class'] ?? '';
    $columns      = $config['columns'] ?? [];

    if (empty($data)) {
        echo '<div class="alert alert-warning">' . $emptyMsg . '</div>';
        return ob_get_clean();
    }
    ?>
    <div class="table-responsive mb-5">
        <table class="<?= $tableClass ?>">
            <thead class="<?= $theadClass ?>">
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <?php if (isset($col['visible']) && $col['visible'] === false) continue; ?>
                        <th <?= (($col['header'] ?? '') === 'Aksi' || ($col['type'] ?? '') === 'action_buttons') ? 'class="text-nowrap"' : '' ?>><?= $col['header'] ?? '' ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $index => $row): ?>
                    <?php 
                    $rowAttrStr = '';
                    if (isset($config['row_attributes']) && is_callable($config['row_attributes'])) {
                        $rowAttrStr = ' ' . $config['row_attributes']($row, $index);
                    }
                    ?>
                    <tr class="<?= $tbodyTrClass ?>"<?= $rowAttrStr ?>>
                        <?php foreach ($columns as $col): ?>
                            <?php if (isset($col['visible']) && $col['visible'] === false) continue; ?>
                            
                            <?php 
                            $cellTag   = ($col['is_header_cell'] ?? false) ? 'th' : 'td'; 
                            $type      = $col['type'] ?? 'text';
                            $classAttr = (($col['header'] ?? '') === 'Aksi' || $type === 'action_buttons') ? ' class="text-nowrap"' : '';
                            ?>
                            
                            <<?= $cellTag ?><?= $classAttr ?>>
                                <?php 
                                if (isset($col['render']) && is_callable($col['render'])) {
                                    echo $col['render']($row, $index);
                                }
                                elseif ($type === 'number') {
                                    echo $index + 1;
                                } 
                                elseif ($type === 'currency') {
                                    echo number_format($row[$col['field']] ?? 0, 0, ',', '.');
                                } 
                                elseif ($type === 'checkbox') {
                                    $idField = $col['id_field'] ?? 'id';
                                    $idVal   = htmlspecialchars($row[$idField] ?? '');
                                    echo '<input class="form-check-input check-barang" data-id="' . $idVal . '" type="checkbox" value="">';
                                } 
                                elseif ($type === 'action_buttons') {
                                    foreach ($col['buttons'] as $btn) {
                                        $btnClass = $btn['class'] ?? '';
                                        $btnColor = $btn['color'] ?? 'primary';
                                        
                                        $targetModal = isset($btn['modal']) ? 'data-bs-toggle="modal" data-bs-target="#' . $btn['modal'] . '"' : '';
                                        
                                        $dataAttrs = '';
                                        if (isset($btn['data_attributes'])) {
                                            foreach ($btn['data_attributes'] as $attrName => $rowKey) {
                                                $val = htmlspecialchars($row[$rowKey] ?? '');
                                                $dataAttrs .= ' data-' . $attrName . '="' . $val . '"';
                                            }
                                        }
                                        
                                        echo '<button type="button" class="btn btn-' . $btnColor . ' btn-sm ' . $btnClass . '" style="line-height: 0; padding: .25rem .5rem; margin-right: 4px;" ' . $targetModal . $dataAttrs . '>';
                                        echo $btn['icon'] ?? '';
                                        echo '</button>';
                                    }
                                } 
                                elseif (isset($col['field'])) {
                                    echo htmlspecialchars($row[$col['field']] ?? '');
                                }
                                ?>
                            </<?= $cellTag ?>>
                            
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
?>