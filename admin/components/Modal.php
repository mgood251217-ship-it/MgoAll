<?php
function renderModal($modalConfig = []) {
    ob_start();
    
    $modalId      = $modalConfig['id'] ?? 'defaultModal';
    $formId       = $modalConfig['form_id'] ?? ''; 
    $formIdAttr   = $formId ? 'id="' . $formId . '"' : '';
    $modalSize    = $modalConfig['size'] ?? '';
    $modalTitle   = $modalConfig['title'] ?? 'Judul Modal';
    $headerClass  = $modalConfig['header_class'] ?? '';
    $bodyClass    = $modalConfig['body_class'] ?? '';
    $formAction   = $modalConfig['action'] ?? '';
    $formMethod   = $modalConfig['method'] ?? 'POST';
    $btnColor     = $modalConfig['btn_color'] ?? 'primary';
    $btnText      = $modalConfig['btn_text'] ?? 'Simpan';
    $customBottom = $modalConfig['custom_bottom'] ?? '';
    $inputs       = $modalConfig['inputs'] ?? [];
    $formLayout   = $modalConfig['layout'] ?? 'vertical';
    $labelWidth   = $modalConfig['label_width'] ?? 'col-sm-4';
    $inputWidth   = $modalConfig['input_width'] ?? 'col-sm-8';

    ?>

    <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog <?= $modalSize ?>">
            <form <?= $formIdAttr ?> action="<?= $formAction ?>" method="<?= $formMethod ?>" class="modal-content">
                
                <div class="modal-header <?= $headerClass ?>">
                    <h5 class="modal-title"><?= $modalTitle ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body <?= $bodyClass ?>">
                    <?php foreach ($inputs as $input): 
                        $type     = $input['type'] ?? 'text';
                        $name     = $input['name'] ?? '';
                        $id       = $input['id'] ?? $name;
                        $label    = $input['label'] ?? '';
                        $value    = $input['value'] ?? '';
                        $required = isset($input['required']) && $input['required'] ? 'required' : '';
                        $custom   = $input['custom_attr'] ?? '';
                        
                        $inputLayout   = $input['layout'] ?? $formLayout;
                        $wrapperBase   = $input['wrapper_class'] ?? 'mb-3'; 
                        $wrapperId     = $input['wrapper_id'] ?? '';
                        $wrapperAttr   = $input['wrapper_attr'] ?? '';
                        $wrapperIdAttr = $wrapperId ? ' id="' . htmlspecialchars($wrapperId, ENT_QUOTES, 'UTF-8') . '"' : '';
                        $wrapperClass  = ($inputLayout === 'horizontal') ? "row $wrapperBase" : $wrapperBase;
                        $lblClass      = ($inputLayout === 'horizontal') ? "$labelWidth col-form-label" : "form-label";
                    ?>
                        
                        <?php if ($type === 'hidden'): ?>
                            <input type="hidden" name="<?= $name ?>" id="<?= $id ?>" value="<?= $value ?>">
                        
                        <?php else: ?>
                            <div class="<?= $wrapperClass ?>"<?= $wrapperIdAttr ?> <?= $wrapperAttr ?>>
                                <label for="<?= $id ?>" class="<?= $lblClass ?>"><?= $label ?></label>
                                
                                <?php if ($inputLayout === 'horizontal'): ?> <div class="<?= $inputWidth ?>"> <?php endif; ?>
                                
                                <?php if ($type === 'select'): ?>
                                    <select name="<?= $name ?>" id="<?= $id ?>" class="form-select" <?= $required ?> <?= $custom ?>>
                                        <?php if(empty($input['no_default_option'])): ?>
                                            <option value="">-- Pilih --</option>
                                        <?php endif; ?>
                                        <?php foreach ($input['options'] ?? [] as $optValue => $optLabel): ?>
                                            <option value="<?= $optValue ?>"><?= $optLabel ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="<?= $type ?>" name="<?= $name ?>" id="<?= $id ?>" class="form-control" value="<?= $value ?>" <?= $required ?> <?= $custom ?>>
                                <?php endif; ?>

                                <?php if ($inputLayout === 'horizontal'): ?> </div> <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </div>
                
                <?= $customBottom ?>
                
                <div class="modal-footer">
                    <button type="submit" class="btn btn-<?= $btnColor ?>"><?= $btnText ?></button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </div>

            </form>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
?>