<?php
$task = $task ?? [];
$isEdit = !empty($task['id']);
$page_title = $isEdit ? 'Edit automation task' : 'Add automation task';
$breadcrumb = [
    ['label' => 'Automation', 'url' => site_url('admin/automation_tasks')],
    $isEdit ? 'Edit' : 'Add',
];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
?>
<form class="card" method="post" action="<?= site_url($isEdit ? 'admin/automation_tasks/update/'.$task['id'] : 'admin/automation_tasks/store') ?>">
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($task['name'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Schedule</label>
      <input type="text" name="schedule" class="form-control" placeholder="daily, hourly, or a cron expression" value="<?= htmlspecialchars($task['schedule'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['pending', 'active', 'paused'] as $st): ?>
        <option value="<?= $st ?>" <?= (($task['status'] ?? 'pending') === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="card-footer d-flex gap-2">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="<?= site_url('admin/automation_tasks') ?>" class="btn btn-light">Cancel</a>
  </div>
</form>
