<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Automation_tasks extends CI_Controller {
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Automation_task_model');
        // Load helpers if needed
        $this->load->helper(['url', 'form']);
    }

    // List all tasks
    public function index()
    {
        $data['tasks'] = $this->Automation_task_model->get_all();
        $data['title'] = 'Automation Tasks';
        $this->load->view('admin/layout/header', $data);
        $this->load->view('admin/layout/sidebar');
        $this->load->view('admin/automation_tasks/list', $data);
        $this->load->view('admin/layout/footer');
    }

    // Show create form
    public function create()
    {
        $data['title'] = 'Add Automation Task';
        $this->load->view('admin/layout/header', $data);
        $this->load->view('admin/layout/sidebar');
        $this->load->view('admin/automation_tasks/form', $data);
        $this->load->view('admin/layout/footer');
    }

    // Store new task
    public function store()
    {
        $task = [
            'name' => $this->input->post('name', true),
            'schedule' => $this->input->post('schedule', true),
            'status' => $this->input->post('status', true) ?: 'pending',
        ];
        $this->Automation_task_model->insert($task);
        $this->session->set_flashdata('success', 'Automation task created');
        redirect('admin/automation_tasks');
    }

    // Show edit form
    public function edit($id)
    {
        $data['task'] = $this->Automation_task_model->get($id);
        $data['title'] = 'Edit Automation Task';
        $this->load->view('admin/layout/header', $data);
        $this->load->view('admin/layout/sidebar');
        $this->load->view('admin/automation_tasks/form', $data);
        $this->load->view('admin/layout/footer');
    }

    // Update task
    public function update($id)
    {
        $task = [
            'name' => $this->input->post('name', true),
            'schedule' => $this->input->post('schedule', true),
            'status' => $this->input->post('status', true),
        ];
        $this->Automation_task_model->update($id, $task);
        $this->session->set_flashdata('success', 'Automation task updated');
        redirect('admin/automation_tasks');
    }

    // Delete task
    public function delete($id)
    {
        $this->Automation_task_model->delete($id);
        $this->session->set_flashdata('success', 'Automation task deleted');
        redirect('admin/automation_tasks');
    }
}
?>
