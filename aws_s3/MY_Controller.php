<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    protected $_table = '';

    public $_is_store = false;

    protected $_excel_export_columns = [];

    protected $_excel_import_columns = [];

    public function __construct()
    {
        parent::__construct();
        if ($this->_is_store) {
            $this->auth = new Store_auth();
            $this->crud = new Store_crud();
            $this->sidebar = new Store_sidebar();
        }
        $this->_get_crud()->set_controller($this);
        if ($this->_get_crud()->get_module_name() !== 'login' && !$this->_get_auth()->check()) {
            $this->_get_auth()->redirect_login_page();
        }
        setcookie("a", 1, time() + 3600, '/'); // for ckfinder
        $this->_get_crud()->set(Crud::KEY_TABLE, strtolower(get_class($this)));
        $this->excel->set(Excel::KEY_TABLE, $this->_table);
        $this->load->vars([
            'is_store' => $this->_is_store,
            'store_id' => $this->_is_store ? $this->_get_auth()->get_store_id() : null
        ]);
    }

    public function store_id()
    {
        if ($this->_is_store) {
            return $this->_get_auth()->get_store_id();
        }
        return false;
    }

    public function _form_validation_add()
    {

    }

    public function _form_validation_edit()
    {

    }

    public function table_data()
    {
        echo json_encode($this->crud->get_ajax_data());
    }

    protected function _set_excel_where()
    {

    }

    public function export_event()
    {
        try {
            $count = 0;
            set_time_limit(0);
            ini_set('memory_limit', '2048M');
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            echo new \sakiluWizard\Event([
                'id' => ++$count,
                'type' => 'message',
                'data' => '初始化Excel',
                'retry' => 60000,
            ]);
            ob_flush();
            flush();
            echo new \sakiluWizard\Event([
                'id' => ++$count,
                'type' => 'message',
                'data' => '取得匯出資料',
                'retry' => 60000,
            ]);
            ob_flush();
            flush();
            $this->excel->set(Excel::KEY_COLUMNS, $this->_excel_export_columns);

            $rows = [];
            $header = [];
            foreach ($this->excel->get(Crud::KEY_COLUMNS) as $column) {
                $header[] = $column->get(AbstractColumn::KEY_DISPLAY);
            }
            array_push($rows, $header);
            $this->_set_excel_where();
            foreach ($this->excel->get_data() as $row) {
                array_push($rows, $row);
            }
            echo new \sakiluWizard\Event([
                'id' => ++$count,
                'type' => 'message',
                'data' => sprintf('製作Excel中 0/%d(筆)', count($rows)),
                'retry' => 6000000,
            ]);
            ob_flush();
            flush();
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $_row_index = 1;
            foreach ($rows as $row) {
                $_col_index = 1;
                foreach ($row as $value) {
                    $sheet->setCellValueExplicitByColumnAndRow($_col_index, $_row_index, $value,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $_col_index++;
                }
                $_row_index++;
                if (($_row_index - 1) % 100 === 0) echo new \sakiluWizard\Event([
                    'id' => ++$count,
                    'type' => 'message',
                    'data' => sprintf('製作Excel中 %d/%d(筆)', $_row_index - 1, count($rows)),
                    'retry' => 6000000,
                ]);
                ob_flush();
                flush();
            }
            echo new \sakiluWizard\Event([
                'id' => ++$count,
                'type' => 'message',
                'data' => 'Excel製作完成，儲存中。',
                'retry' => 6000000,
            ]);
            ob_flush();
            flush();
            $tmpfname = tempnam("/tmp", "excel_") . 'xls';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tmpfname);
            $obj = s3_file_upload($tmpfname, [
                'key' => sprintf('excel_export/excel_%s', uniqid() . '.xlsx')
            ]);
            echo new \sakiluWizard\Event([
                'id' => ++$count,
                'type' => 'finish',
                'data' => $obj,
                'retry' => 60000,
            ]);
            ob_flush();
            flush();
        } catch (Exception $e) {
            echo new \sakiluWizard\Event([
                'id' => ++$count,
                'type' => 'error',
                'data' => $e->getMessage(),
                'retry' => 60000,
            ]);
            ob_flush();
            flush();
        }
    }

    public function export()
    {
        $this->layout->view('crud/content/excel_export', [

        ]);
    }

    protected function set_excel_where($data)
    {

    }

    public function excel_sample()
    {
        $this->excel->set(Excel::KEY_COLUMNS, $this->_excel_import_columns);

        $rows = [];
        $header = [];
        foreach ($this->excel->get(Crud::KEY_COLUMNS) as $column) {
            $header[] = $column->get(AbstractColumn::KEY_DISPLAY);
        }
        array_push($rows, $header);
        foreach ($this->excel->get_data() as $row) {
            array_push($rows, $row);
        }
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $_row_index = 1;
        foreach ($rows as $row) {
            $_col_index = 1;
            foreach ($row as $value) {
                $sheet->setCellValueExplicitByColumnAndRow($_col_index, $_row_index, $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $_col_index++;
            }
            $_row_index++;
        }
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $this->_table . '.xls"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        $writer->save('php://output');
    }

    public function excel_import($action = null)
    {
        $this->excel->set(Excel::KEY_COLUMNS, $this->_excel_import_columns);
        set_time_limit(0);
        ini_set("memory_limit", "128M");

        if ($action == 'load') {
            $this->form_validation->set_error_delimiters('', ',');

            if (empty($_FILES['file'])) {
                echo '請上傳檔案';
                $this->output->set_status_header(400);
                return;
            }
            if ($_FILES['file']['error'] == UPLOAD_ERR_INI_SIZE) {
                echo '檔案過大';
                $this->output->set_status_header(400);
                return;
            }
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
            $_rows = $spreadsheet->getActiveSheet()->toArray();
            $head = array_shift($_rows);

            $fields = [];
            $key_index = false;
            $options = [];
            foreach ($this->excel->get(Crud::KEY_COLUMNS) as $key => $column) {
                $options[$key] = $column->get(Column::KEY_OPTIONS);
                if (trim($head[$key]) != $column->get(AbstractColumn::KEY_DISPLAY)) {
                    echo '欄位名稱不符合';
                    $this->output->set_status_header(400);
                    return;
                }
                $fields[$key] = $column->get(AbstractColumn::KEY_FIELD);
                if ($column->get(AbstractColumn::KEY_FIELD) == $this->excel->get(Excel::KEY_TABLE) . '_id') {
                    $key_index = $key;
                }
            }
            if ($key_index === false) {
                echo 'Excel 沒有系統編號欄位';
                $this->output->set_status_header(400);
                return;
            }

            $add = [];
            $error = [];
            $pass = true;

            foreach ($_rows as $row_index => $v) {
                $option_error = false;
                $data = [];
                $is_add = intval($v[$key_index]) === 0;
                if ($is_add) {
                    $this->_form_validation_add();
                } else {
                    $this->_form_validation_edit();
                }
                foreach ($v as $col_index => $value) {
                    $value = trim($value);
                    $data[$fields[$col_index]] = $value;
                    if (!empty($options[$col_index])) {
                        $_options = array_flip($options[$col_index]);
                        if (!isset($_options[$value])) {
                            $error[] = $value . ' 不存在';
                            $option_error = true;
                            $pass = false;
                            break;
                        }
                    }
                }
                if (!$option_error) {
                    $this->form_validation->set_data($data);
                    if ($this->form_validation->run() == FALSE) {
                        $error[] = validation_errors();
                        $pass = false;
                    } else {
                        $error[] = '';
                    }
                }
                $add[] = $is_add;
                $this->form_validation->reset_validation();
            }
            echo json_encode([
                'rows' => $_rows,
                'head' => $head,
                'key_index' => $key_index,
                'add' => $add,
                'error' => $error,
                'pass' => $pass
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($action == 'save') {
            $key_index = false;
            $fields = [];

            $rows = json_decode($_POST['data']);
            $options = [];
            foreach ($this->excel->get(Crud::KEY_COLUMNS) as $key => $column) {
                $fields[$key] = $column->get(AbstractColumn::KEY_FIELD);
                if ($column->get(AbstractColumn::KEY_FIELD) == $this->excel->get(Excel::KEY_TABLE) . '_id') {
                    $key_index = $key;
                }
                $options[$key] = $column->get(Column::KEY_OPTIONS);
            }
            $store_id = $this->store_id();
            foreach ($rows as $row) {
                $data = [];
                foreach ($row as $k => $v) {
                    $v = trim($v);
                    if (!empty($options[$k])) {
                        $_options = array_flip($options[$k]);
                        $data[$fields[$k]] = $_options[$v];
                        continue;
                    }
                    $data[$fields[$k]] = $v;
                }
                if ($store_id) {
                    $data[$this->excel->get(Excel::KEY_TABLE) . '_store_admin_id'] = $store_id;
                }
                if (!empty($data[$fields[$key_index]])) {
                    $this->db->where($fields[$key_index], $data[$fields[$key_index]]);
                    $this->set_excel_where($data);
                    unset($data[$fields[$key_index]]);
//                    $this->db->update($this->excel->get(Excel::KEY_TABLE), $data);
                } else {
                    unset($data[$fields[$key_index]]);
                    $this->db->insert($this->excel->get(Excel::KEY_TABLE), $data);
                }
            }
            echo base_url(sprintf('%s/index', $this->_get_crud()->get_module_url()));
        } else {
            $this->layout->view('crud/content/excel_import', [

            ]);
        }
    }

    /**
     * @param $id
     */
    public function ajax_remove($id)
    {
        $table = $this->_get_crud()->get(Crud::KEY_TABLE);
        if ($this->db->field_exists($table . '_trash', $table)) {
            $this->db->where($this->_get_crud()->get_primary_key_column_name(), $id);
            $this->db->update($table, [$table . '_trash' => 1]);
            if ($this->db->field_exists($table . '_updated_by', $table)) {
                $this->db->where($this->_get_crud()->get_primary_key_column_name(), $id);
                $this->db->update($table, [$table . '_updated_by' => $this->_get_auth()->get_name()]);
            }
            return;
        }
        $this->_get_crud()->single_delete($table, $id);
        $this->crud_model->file_dlt($table, $id);
    }

    public function state_list_save()
    {
        $this->_get_crud()->state_list_save();
    }

    public function state_list_load()
    {
        echo json_encode($this->_get_crud()->state_list_load());
    }

    public function is_logged()
    {
        echo $this->_get_auth()->get_id() > 0 ? 'yah!good' : 'nope!bad';
    }

    /**
     * @return Logger
     */
    public function _get_logger()
    {
        return $this->logger;
    }

    public function logout()
    {
        $this->_get_auth()->logout();
    }

    public function remove_s3_file($field, $id)
    {
        $row = $this->db->get_where($this->_table, [
            $this->_table . '_contract_code' => $id
        ])->row();
        if ($row) {
            $json_data = $row->{$field};
            if ($json_data) {
                $json_data = json_decode($json_data);
                $key = $json_data->key;
                s3_delete($key);
            }
        }
        $this->db->where($this->_table . '_contract_code', $id);
        $this->db->update($this->_table, [
            $field => ''
        ]);
    }

    public function remove_s3_multiple_file($field, $id, $key)
    {
        $row = $this->db->get_where($this->_table, [
            $this->_table . '_id' => $id
        ])->row();
        $json_data = '';
        if ($row) {
            $json_data = $row->{$field};
            if ($json_data) {
                $rows = json_decode($json_data);
                if (is_array($rows)) {
                    $data = [];
                    foreach ($rows as $k => $row) {
                        if ($row->key == $key) {
                            s3_delete($key);
                            continue;
                        }
                        $data[] = $row;
                    }
                    $json_data = json_encode($data);
                }
            }
        }
        $this->db->where($this->_table . '_id', $id);
        $this->db->update($this->_table, [
            $field => $json_data
        ]);
    }

    public function change_s3_multiple_note($field, $id, $key)
    {
        $row = $this->db->get_where($this->_table, [
            $this->_table . '_id' => $id
        ])->row();
        $json_data = '';
        if ($row) {
            $json_data = $row->{$field};
            if ($json_data) {
                $rows = json_decode($json_data);
                if (is_array($rows)) {
                    $data = [];
                    foreach ($rows as $k => $row) {
                        if ($row->key == $key) {
                            $row->note = $_POST['text'];
                        }
                        $data[] = $row;
                    }
                    $json_data = json_encode($data);
                }
            }
        }
        $this->db->where($this->_table . '_id', $id);
        $this->db->update($this->_table, [
            $field => $json_data
        ]);
    }

    public function change_s3_multiple_check($field, $id, $key)
    {
        $row = $this->db->get_where($this->_table, [
            $this->_table . '_id' => $id
        ])->row();
        $json_data = '';
        if ($row) {
            $json_data = $row->{$field};
            if ($json_data) {
                $rows = json_decode($json_data);
                if (is_array($rows)) {
                    $data = [];
                    foreach ($rows as $k => $row) {
                        if ($row->key == $key) {
                            $row->checked = $_POST['checked'];
                        }
                        $data[] = $row;
                    }
                    $json_data = json_encode($data);
                }
            }
        }
        $this->db->where($this->_table . '_id', $id);
        $this->db->update($this->_table, [
            $field => $json_data
        ]);
    }

    /**
     * @return Sidebar
     */
    protected function _get_sidebar()
    {
        return $this->sidebar;
    }

    /**
     * @return AbstractAuth
     */
    protected function _get_auth()
    {
        return $this->auth;
    }

    /**
     * @return Crud
     */
    protected function _get_crud()
    {
        return $this->crud;
    }

    /**
     * @return Layout
     */
    protected function _get_layout()
    {
        return $this->layout;
    }

    /**
     * @return Form
     */
    protected function _get_form()
    {
        return $this->form;
    }

}
