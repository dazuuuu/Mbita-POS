<?php
// public/templates/staff/layout.php
// Staff chrome. Same tenant branding (staff see the shop they work for), but
// guarded for the 'staff' role. For now it reuses the tenant sidebar, whose
// items are capability-gated, so staff automatically see fewer options. A
// dedicated staff sidebar can be duplicated from the tenant one later.

$__tenant = $__tenant ?? (TenantContext::tenantId()
    ? (new Models\TenantModel(Database::pdo()))->find(TenantContext::tenantId())
    : null);
$shopName = $__tenant['name'] ?? 'My Shop';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?> — <?php echo htmlspecialchars($shopName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *{box-sizing:border-box;} body{margin:0;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f1f5f9;color:#0f172a;}
        .t-wrap{display:flex;min-height:100vh;}
        .t-main{flex:1;margin-left:264px;padding:26px 30px;width:calc(100% - 264px);}
        .t-topbar{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:22px;}
        .t-topbar h1{font-size:1.35rem;margin:0;font-weight:700;}
        .t-flash{border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:.92rem;}
        .t-flash.ok{background:#dcfce7;color:#166534;} .t-flash.err{background:#fee2e2;color:#991b1b;}
        @media (max-width:992px){ .t-main{margin-left:0;width:100%;padding:18px;} .t-topbar{margin-top:54px;} }
        @media (max-width:576px){ .t-main{padding:14px;} .t-topbar h1{font-size:1.15rem;} }
    </style>
    <?php echo $extra_css ?? ''; ?>
</head>
<body>
<div class="t-wrap">
    <?php include __DIR__ . '/../../components/staff/sidebar.php'; ?>
    <main class="t-main">
        <div class="t-topbar">
            <h1><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></h1>
            <div class="text-muted small d-none d-md-block"><?php echo date('l, j M Y'); ?></div>
        </div>
        <?php if (!empty($_SESSION['flash']['success'])): ?>
            <div class="t-flash ok"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash']['error'])): ?>
            <div class="t-flash err"><?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
        <?php endif; ?>
        <?php echo $content ?? ''; ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php echo $extra_js ?? ''; ?>
</body>
</html>