<?php if (!$this->form->get(Form::KEY_READONLY)) { ?>
    <label id="s3_file_area<?= $unique ?>" <?php if ($file_data) echo 'style="display: none;"' ?>
           class="field prepend-icon append-button file">
        <span class="button btn-primary">選擇檔案</span>
        <input type="file" class="gui-file" accept="<?= $accept ?>" select_id="<?= $unique ?>" name="<?= $field_name ?>"
               id="<?= $field_name ?>"/>
        <input type="text" data-text="<?= $unique ?>" class="gui-input" id="uploader1"
               placeholder="Please Select A File">
        <label class="field-icon">
            <i class="fa fa-upload"></i>
        </label>
    </label>
<?php } ?>
<?php if ($file_data) { ?>
    <?php
    $file_data = json_decode($file_data);
    ?>
    <p id="s3_button_<?= $unique ?>">
        <button type="button" class="btn btn-primary btn-sm" onclick="window.open('<?= $file_data->url ?>')">
            <i class="fa fa-download"></i> <?= $file_data->name ?> (<?= formatBytes($file_data->size) ?>)
        </button>
        <button id="delete_<?= $unique ?>" type="button" class="btn btn-danger btn-sm">
            <i class="fa fa-ban"></i> 刪除
        </button>
    </p>
<?php } ?>
<script>
    $(function () {
        $('[select_id="<?=$unique?>"]').change(function () {
            $('[data-text="<?=$unique?>"]').val($('#<?=$field_name?>').val());
        });

        $('#delete_<?= $unique ?>').click(function () {
            if (confirm('確定要刪除檔案?')) {
                $.ajax({
                    // url: '<?= base_url(sprintf('%s/remove_s3_file/%s/%d',
            $this->crud->get_module_url(),$field_name,$this->form->get_primary_key())) ?>', // form action url
                    url: '<?= base_url($this->crud->get_module_url().'/remove_s3_file/'.$field_name.'/'.$this->form->get_primary_key()) ?>', // form action url
                    cache: false,
                    contentType: false,
                    processData: false,
                    beforeSend: function () {
                        $.blockUI({message: null});
                    },
                    success: function () {
                        $('#s3_file_area<?= $unique ?>').show();
                        $('#s3_button_<?= $unique ?>').remove();
                        $.unblockUI();
                    },
                    error: function (e) {
                        $.unblockUI();
                    }
                });
            }
        });
    });
</script>