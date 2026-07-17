<?php
// public/templates/admins/layout.php — platform admin chrome
$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title ?? 'Platform'); ?> — Mbita POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo public_path('assets/css/curlz-dashboard.css'); ?>">
    <?php echo $extra_css ?? ''; ?>
</head>
<body class="cd-body">
<div class="cd-wrap">
    <?php include __DIR__ . '/../../components/admins/sidebar.php'; ?>
    <div class="cd-main">
        <header class="cd-header">
            <div class="cd-header-left">
                <button type="button" class="cd-hamburger" onclick="cdToggleSidebar()" aria-label="Menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="cd-header-right">
                <button type="button" class="cd-user">
                    <span class="cd-avatar"><i class="fas fa-user-shield"></i></span>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </button>
            </div>
        </header>

        <div class="cd-content">
            <?php if (!empty($_SESSION['flash']['success'])): ?>
                <div class="cd-flash ok"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash']['error'])): ?>
                <div class="cd-flash err"><?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
            <?php endif; ?>

            <?php if (empty($hide_page_header)): ?>
            <h1 class="cd-page-title"><?php echo htmlspecialchars($page_title ?? 'Platform'); ?></h1>
            <div class="cd-breadcrumb">
                <a href="<?php echo public_path('admins/dashboard/'); ?>">Platform</a> / <?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?>
            </div>
            <?php endif; ?>

            <?php echo $content ?? ''; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php echo $extra_js ?? ''; ?>
</body>
</html>
