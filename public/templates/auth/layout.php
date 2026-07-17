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
            --lux-black:#0a0a0a;
            --lux-black-soft:#141414;
            --lux-white:#ffffff;
            --lux-off:#f5f5f5;
            --lux-gold:#c9a227;
            --lux-gold-light:#e8c547;
            --lux-gold-dark:#9a7b1a;
            --lux-muted:#666666;
            --lux-border:#e5e5e5;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            background:var(--lux-black);
            font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
            padding:40px 20px;color:var(--lux-white);
        }
        body::before{
            content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(201,162,39,.12), transparent 55%),
                linear-gradient(180deg, var(--lux-black-soft) 0%, var(--lux-black) 100%);
        }

        .auth-shell{position:relative;z-index:1;width:420px;max-width:100%}
        .auth-card{
            background:var(--lux-white);color:var(--lux-black);
            border:1px solid var(--lux-border);border-radius:16px;
            overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.45);
            position:relative;
        }
        .auth-card::before{
            content:'';position:absolute;top:0;left:0;right:0;height:4px;
            background:linear-gradient(90deg, var(--lux-gold-dark), var(--lux-gold), var(--lux-gold-light));
        }

        .auth-head{padding:32px 32px 0;text-align:center}
        .auth-head img{
            max-height:52px;max-width:200px;object-fit:contain;
            display:block;margin:0 auto 10px;
        }
        .logo-icon{
            width:44px;height:44px;background:var(--lux-black);
            border:2px solid var(--lux-gold);border-radius:10px;
            display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px;
        }
        .logo-icon i{color:var(--lux-gold);font-size:18px}

        .auth-body{padding:8px 32px 32px;color:var(--lux-black)}
        .auth-body button{color:inherit}
        .badge-wrap{text-align:center;margin:18px 0 6px}
        .badge-secure{
            display:inline-flex;align-items:center;gap:6px;
            background:var(--lux-off);border:1px solid var(--lux-gold);
            color:var(--lux-gold-dark);font-size:.72rem;font-weight:700;
            padding:4px 12px;border-radius:999px;letter-spacing:.06em;text-transform:uppercase;
        }
        .badge-dot{width:6px;height:6px;border-radius:50%;background:var(--lux-gold);display:inline-block}

        .auth-title{font-size:1.55rem;font-weight:700;color:var(--lux-black);text-align:center;margin:0 0 6px}
        .auth-sub{color:var(--lux-muted);font-size:.88rem;text-align:center;margin-bottom:24px;line-height:1.5}

        .form-label{
            display:block;font-size:.78rem;font-weight:600;color:var(--lux-black);
            margin-bottom:6px;letter-spacing:.04em;text-transform:uppercase;
        }
        .field-wrap{position:relative}
        .field-icon{
            position:absolute;left:14px;top:50%;transform:translateY(-50%);
            color:var(--lux-muted);pointer-events:none;
        }
        .form-control{
            background:var(--lux-off)!important;border:1px solid var(--lux-border)!important;
            border-radius:10px!important;padding:12px 14px 12px 42px!important;
            color:var(--lux-black)!important;font-size:.92rem!important;transition:all .2s!important;
        }
        .form-control::placeholder{color:#999}
        .form-control:focus{
            background:var(--lux-white)!important;border-color:var(--lux-gold)!important;
            box-shadow:0 0 0 3px rgba(201,162,39,.18)!important;color:var(--lux-black)!important;
        }

        .btn-auth{
            width:100%;padding:13px;font-weight:700;font-size:.95rem;
            background:var(--lux-gold)!important;border:1px solid var(--lux-gold-dark)!important;
            border-radius:10px!important;color:var(--lux-black)!important;
            letter-spacing:.02em;transition:all .2s!important;
        }
        .btn-auth:hover{
            background:var(--lux-gold-light)!important;
            box-shadow:0 8px 24px rgba(201,162,39,.35)!important;
        }

        .auth-alert{border-radius:10px;padding:10px 14px;font-size:.88rem;margin-bottom:16px}
        .auth-alert.err{background:#fff5f5;color:#991b1b;border:1px solid #fecaca}
        .auth-alert.ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
        .otp-input{letter-spacing:.5em;text-align:center;font-size:1.4rem;font-weight:700}

        a{color:var(--lux-gold-dark);text-decoration:none}
        a:hover{color:var(--lux-gold);text-decoration:underline}

        @media(max-width:480px){
            .auth-head{padding:24px 24px 0}
            .auth-body{padding:8px 24px 24px}
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
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
                <div class="badge-wrap">
                    <span class="badge-secure"><span class="badge-dot"></span>Secure Portal</span>
                </div>
                <?php echo $content ?? ''; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
