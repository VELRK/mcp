<?php
$page_title = 'Automation';
$breadcrumb = ['Automation'];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
?>
<div class="card">
  <div class="card-header">
    <h5 class="card-title">Automation tasks</h5>
    <a href="<?= site_url('admin/automation_tasks/create') ?>" class="btn btn-sm btn-primary"><i class="mdi mdi-plus me-1"></i>Add task</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr><th>Name</th><th>Schedule</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $task): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($task['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($task['schedule'] ?? '') ?></td>
            <td><span class="badge badge-label-primary"><?= htmlspecialchars($task['status'] ?? '') ?></span></td>
            <td class="text-end">
              <a href="<?= site_url('admin/automation_tasks/edit/'.$task['id']) ?>" class="btn btn-sm btn-label-primary">Edit</a>
              <a href="<?= site_url('admin/automation_tasks/delete/'.$task['id']) ?>" class="btn btn-sm btn-label-danger" onclick="return confirm('Delete this task?')">Delete</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($tasks)): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">No automation tasks yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
