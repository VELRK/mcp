<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Automation_tasks extends Sk_Base {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Automation_task_model');
        $this->_ensure_schema();
    }

    public function index()
    {
        $data['tasks'] = $this->Automation_task_model->get_all();
        $data['title'] = 'Automation Tasks';
        $this->render('automation_tasks/list', $data);
    }

    public function create()
    {
        $data['title'] = 'Add Automation Task';
        $data['task'] = [];
        $this->render('automation_tasks/form', $data);
    }

    public function store()
    {
        $name = trim((string)$this->input->post('name', true));
        if ($name === '') {
            $this->session->set_flashdata('error', 'Task name is required.');
            redirect('admin/automation_tasks/create');
            return;
        }
        $this->Automation_task_model->insert([
            'name' => $name,
            'schedule' => trim((string)$this->input->post('schedule', true)),
            'status' => $this->input->post('status', true) ?: 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->session->set_flashdata('success', 'Automation task created');
        redirect('admin/automation_tasks');
    }

    public function edit($id)
    {
        $task = $this->Automation_task_model->get($id);
        if (!$task) {
            show_404();
        }
        $data['task'] = $task;
        $data['title'] = 'Edit Automation Task';
        $this->render('automation_tasks/form', $data);
    }

    public function update($id)
    {
        if (!$this->Automation_task_model->get($id)) {
            show_404();
        }
        $this->Automation_task_model->update($id, [
            'name' => trim((string)$this->input->post('name', true)),
            'schedule' => trim((string)$this->input->post('schedule', true)),
            'status' => $this->input->post('status', true) ?: 'pending',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->session->set_flashdata('success', 'Automation task updated');
        redirect('admin/automation_tasks');
    }

    public function delete($id)
    {
        $this->Automation_task_model->delete($id);
        $this->session->set_flashdata('success', 'Automation task deleted');
        redirect('admin/automation_tasks');
    }

    private function _ensure_schema(): void
    {
        if (!$this->db->table_exists('automation_tasks')) {
            $this->db->query("CREATE TABLE `automation_tasks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(190) NOT NULL,
                `schedule` VARCHAR(190) NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return;
        }
        if (!$this->db->field_exists('created_at', 'automation_tasks')) {
            $this->db->query("ALTER TABLE `automation_tasks` ADD COLUMN `created_at` DATETIME NULL");
        }
        if (!$this->db->field_exists('updated_at', 'automation_tasks')) {
            $this->db->query("ALTER TABLE `automation_tasks` ADD COLUMN `updated_at` DATETIME NULL");
        }
        if (!$this->db->field_exists('schedule', 'automation_tasks')) {
            $this->db->query("ALTER TABLE `automation_tasks` ADD COLUMN `schedule` VARCHAR(190) NULL");
        }
        if (!$this->db->field_exists('status', 'automation_tasks')) {
            $this->db->query("ALTER TABLE `automation_tasks` ADD COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'pending'");
        }
    }
}
