<?php
// public/super/branches/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$bm  = new Models\BranchModel($pdo);
$error = '';
$old = ['title' => '', 'location' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $old['title']    = trim($_POST['title'] ?? '');
    $old['location'] = trim($_POST['location'] ?? '');
    $res = $bm->create($old['title'], $old['location']);
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Branch "' . $old['title'] . '" created.';
        header('Location: ' . public_path('super/branches/'));
        exit;
    }
    $error = $res['error'];
}

$branches = $bm->listWithCounts();
$page_title = 'Branches';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add a branch</h2>
        <p class="text-muted small mb-3">Each branch is a physical shop location. You can assign staff to a branch once it exists.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Branch name</label>
            <input name="title" class="form-control" placeholder="e.g. Westlands" value="<?php echo htmlspecialchars($old['title']); ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Location <span class="text-muted">(optional)</span></label>
            <input name="location" class="form-control" placeholder="e.g. Ring Road Mall, Shop 12" value="<?php echo htmlspecialchars($old['location']); ?>">
          </div>
          <button class="btn btn-primary">Create branch</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Your branches <span class="badge bg-light text-dark"><?php echo count($branches); ?></span></h2>
        <?php if (!$branches): ?>
          <div class="text-muted">No branches yet. Create your first one on the left.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Branch</th><th>Location</th><th class="text-center">Staff</th></tr></thead>
              <tbody>
                <?php foreach ($branches as $b): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($b['title']); ?></td>
                  <td class="text-muted"><?php echo htmlspecialchars($b['location'] ?? '—'); ?></td>
                  <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$b['staff_count']; ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <a class="btn btn-sm btn-outline-secondary mt-3" href="<?php echo public_path('super/staff/'); ?>">Manage staff</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';