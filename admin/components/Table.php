<?php
function renderTable($config = []) {
    ob_start();
    
    $tableId      = isset($config['id']) ? ' id="' . htmlspecialchars($config['id']) . '"' : '';
    $data         = $config['data'] ?? [];
    $emptyMsg     = $config['empty_message'] ?? 'Tidak ada data.';
    $tableClass   = $config['table_class'] ?? 'table table-bordered table-striped';
    $theadClass   = $config['thead_class'] ?? 'table-primary';
    $tbodyTrClass = $config['tbody_tr_class'] ?? '';
    $columns      = $config['columns'] ?? [];
    $tfootHtml    = $config['tfoot'] ?? '';

    if (empty($data)) {
        echo '<div class="alert alert-warning">' . $emptyMsg . '</div>';
        return ob_get_clean();
    }
    ?>
    <div class="table-responsive mb-5">
        <table class="<?= $tableClass ?>"<?= $tableId ?>>
            <thead class="<?= $theadClass ?>">
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <?php if (isset($col['visible']) && $col['visible'] === false) continue; ?>
                        <th <?= (($col['header'] ?? '') === 'Aksi' || ($col['type'] ?? '') === 'action_buttons') ? 'class="text-nowrap text-center"' : '' ?>><?= $col['header'] ?? '' ?></th>
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
                            $classAttr = (($col['header'] ?? '') === 'Aksi' || $type === 'action_buttons') ? ' class="text-nowrap align-middle"' : ' class="align-middle"';
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
                                elseif ($type === 'badge') {
                                    $badgeClass = $col['badge_class'] ?? 'bg-primary';
                                    echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($row[$col['field']] ?? '') . '</span>';
                                }
                                elseif ($type === 'image') {
                                    $val = $row[$col['field']] ?? '';
                                    if (!empty($val)) {
                                        $basePath = $col['base_path'] ?? '';
                                        $width = $col['width'] ?? 40;
                                        $height = $col['height'] ?? 40;
                                        $imgClass = $col['img_class'] ?? 'img-thumbnail rounded';
                                        echo '<img src="' . BASE_URL . $basePath . '/' . htmlspecialchars($val) . '" width="' . $width . '" height="' . $height . '" class="' . $imgClass . '">';
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                }
                                elseif ($type === 'checkbox') {
                                    $idField = $col['id_field'] ?? 'id';
                                    $idVal   = htmlspecialchars($row[$idField] ?? '');
                                    echo '<input class="form-check-input check-barang" data-id="' . $idVal . '" type="checkbox" value="">';
                                } 
                                elseif ($type === 'action_buttons') {
                                    foreach ($col['buttons'] as $btn) {
                                        $btnType  = $btn['type'] ?? 'button'; 
                                        $btnClass = $btn['class'] ?? '';
                                        $btnColor = $btn['color'] ?? 'primary';
                                        $btnText  = $btn['text'] ?? '';
                                        $icon     = $btn['icon'] ?? '';
                                        
                                        $btnStyle = 'line-height: 0; padding: .25rem .5rem; margin-right: 4px;';
                                        
                                        if ($btnType === 'form') {
                                            $actionUrl = $btn['action_url'] ?? '#';
                                            $formClass = $btn['form_class'] ?? '';
                                            
                                            echo '<form method="POST" action="' . $actionUrl . '" class="d-inline ' . $formClass . '">';
                                            if (isset($btn['hidden_inputs'])) {
                                                foreach ($btn['hidden_inputs'] as $inputName => $rowKey) {
                                                    echo '<input type="hidden" name="' . $inputName . '" value="' . htmlspecialchars($row[$rowKey] ?? '') . '">';
                                                }
                                            }
                                            echo '<button type="submit" class="btn btn-' . $btnColor . ' btn-sm ' . $btnClass . '" style="' . $btnStyle . '">' . $icon . $btnText . '</button>';
                                            echo '</form> ';
                                        } 
                                        elseif ($btnType === 'link') {
                                            $urlBase = $btn['href'] ?? '#';
                                            $paramKey = $btn['param_field'] ?? '';
                                            $url = $urlBase . ($paramKey ? urlencode($row[$paramKey] ?? '') : '');
                                            $confirmAttr = isset($btn['confirm']) ? ' onclick="return confirm(\'' . htmlspecialchars($btn['confirm'], ENT_QUOTES) . '\')"' : '';
                                            
                                            echo '<a href="' . $url . '" class="btn btn-' . $btnColor . ' btn-sm ' . $btnClass . '" style="' . $btnStyle . '"' . $confirmAttr . '>';
                                            echo $icon . $btnText;
                                            echo '</a>';
                                        }
                                        else {
                                            $targetModal = isset($btn['modal']) ? 'data-bs-toggle="modal" data-bs-target="#' . $btn['modal'] . '"' : '';
                                            
                                            $dataAttrs = '';
                                            if (isset($btn['data_attributes'])) {
                                                foreach ($btn['data_attributes'] as $attrName => $rowKey) {
                                                    $val = htmlspecialchars($row[$rowKey] ?? '');
                                                    $dataAttrs .= ' data-' . $attrName . '="' . $val . '"';
                                                }
                                            }
                                            
                                            if (isset($btn['data_json'])) {
                                                $jsonStr = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                                $dataAttrs .= ' data-' . $btn['data_json'] . '="' . $jsonStr . '"';
                                            }

                                            $onclickAttr = '';
                                            if (isset($btn['onclick'])) {
                                                $fnName = $btn['onclick']['function'];
                                                
                                                if (isset($btn['onclick']['param_fields']) && is_array($btn['onclick']['param_fields'])) {
                                                    $params = [];
                                                    foreach ($btn['onclick']['param_fields'] as $key) {
                                                        $val = htmlspecialchars(addslashes($row[$key] ?? ''), ENT_QUOTES);
                                                        $params[] = "'" . $val . "'";
                                                    }
                                                    $onclickAttr = ' onclick="' . $fnName . '(' . implode(', ', $params) . ')"';
                                                } 
                                                elseif (isset($btn['onclick']['param_field'])) {
                                                    $paramKey = $btn['onclick']['param_field'];
                                                    $paramVal = htmlspecialchars($row[$paramKey] ?? '');
                                                    $onclickAttr = ' onclick="' . $fnName . '(' . $paramVal . ')"';
                                                }
                                            }
                                            
                                            echo '<button type="button" class="btn btn-' . $btnColor . ' btn-sm ' . $btnClass . '" style="' . $btnStyle . '" ' . $targetModal . $dataAttrs . $onclickAttr . '>';
                                            echo $icon . $btnText;
                                            echo '</button>';
                                        }
                                    }
                                } 
                                elseif (isset($col['field'])) {
                                    $textClass = $col['text_class'] ?? '';
                                    if ($textClass) {
                                        echo '<span class="' . $textClass . '">' . htmlspecialchars($row[$col['field']] ?? '') . '</span>';
                                    } else {
                                        echo htmlspecialchars($row[$col['field']] ?? '');
                                    }
                                }
                                ?>
                            </<?= $cellTag ?>>
                            
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($tfootHtml)): ?>
            <tfoot>
                <?= $tfootHtml ?>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
?>