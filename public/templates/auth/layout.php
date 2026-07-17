<?php
// public/templates/auth/layout.php
// Centered card used by register / login / otp-verify / activate.
$__authBrand = Branding::portalBranding(Database::pdo());
$__authName  = $__authBrand['name'];
$__authLogo  = $__authBrand['logo_url'];
$__authTenant = $__authBrand['tenant'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(($page_title ?? 'Sign in') . ' — ' . $__authName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{
            --text:#222222;
            --muted:#888888;
            --gold:#b8956b;
            --gold-soft:rgba(184,149,107,.18);
            --line:#eeeeee;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            background:#ffffff;
            font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
            padding:32px 20px;color:var(--text);
        }

        .auth-shell{width:400px;max-width:100%}
        .auth-head{padding:0 0 20px;text-align:center}
        .auth-head img{
            max-height:52px;max-width:200px;object-fit:contain;
            display:block;margin:0 auto 8px;
        }
        .logo-icon{
            width:40px;height:40px;color:var(--gold);
            display:inline-flex;align-items:center;justify-content:center;margin-bottom:6px;
        }
        .logo-icon i{font-size:20px}

        .auth-body{color:var(--text)}
        .auth-body button{color:inherit}

        .auth-title{font-size:1.35rem;font-weight:600;color:var(--text);text-align:center;margin:0 0 4px}
        .auth-sub{color:var(--muted);font-size:.88rem;text-align:center;margin-bottom:20px;line-height:1.5}

        .form-label{
            display:block;font-size:.78rem;font-weight:500;color:var(--muted);
            margin-bottom:6px;
        }
        .form-control{
            background:#fff!important;border:1px solid var(--line)!important;
            border-radius:8px!important;padding:11px 14px!important;
            color:var(--text)!important;font-size:.92rem!important;
        }
        .form-control:focus{
            border-color:var(--gold)!important;
            box-shadow:0 0 0 2px var(--gold-soft)!important;
        }

        .btn-auth{
            width:100%;padding:12px;font-weight:600;font-size:.92rem;
            background:#fff!important;border:1px solid var(--gold)!important;
            border-radius:8px!important;color:var(--gold)!important;
        }
        .btn-auth:hover{background:var(--gold-soft)!important}

        .auth-alert{border-radius:8px;padding:10px 14px;font-size:.88rem;margin-bottom:14px}
        .auth-alert.err{background:#fff5f5;color:#991b1b;border:1px solid #fecaca}
        .auth-alert.ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
        .otp-input{letter-spacing:.5em;text-align:center;font-size:1.4rem;font-weight:600}

        a{color:var(--gold);text-decoration:none}
        a:hover{text-decoration:underline}

        @media(max-width:480px){
            body{padding:24px 16px}
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-head">
            <?php if ($__authLogo): ?>
            <img src="<?php echo htmlspecialchars($__authLogo); ?>" alt="<?php echo htmlspecialchars($__authName); ?>"
                 onerror="this.style.display='none';document.getElementById('authLogoFallback')?.classList.remove('d-none');">
            <?php endif; ?>
            <div id="authLogoFallback" class="logo-icon <?php echo $__authLogo ? 'd-none' : ''; ?>">
                <i class="fas fa-layer-group"></i>
            </div>
        </div>
        <div class="auth-body">
            <?php echo $content ?? ''; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
