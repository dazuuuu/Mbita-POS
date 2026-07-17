<?php
// public/templates/auth/layout.php
// Centered card used by register / login / otp-verify / activate.
$__authBrand = Branding::portalBranding();
$__authName  = $__authBrand['name'];
$__authLogo  = $__authBrand['logo_url'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? $__authName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;
             background:radial-gradient(ellipse at 30% 20%,#0d1b3e,#0a0f1e 60%,#000510);
             font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;padding:40px 20px;
             overflow-x:hidden;perspective:1200px;}

        .stars{position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:0}
        .star{position:absolute;border-radius:50%;background:#fff;animation:twinkle var(--d,3s) infinite alternate}
        @keyframes twinkle{0%{opacity:.1;transform:scale(.8)}100%{opacity:.9;transform:scale(1.2)}}

        .grid-plane{position:fixed;bottom:-80px;left:50%;transform:translateX(-50%) rotateX(75deg);
                    width:900px;height:600px;z-index:0;
                    background:linear-gradient(rgba(37,99,235,.15) 1px,transparent 1px),
                                linear-gradient(90deg,rgba(37,99,235,.15) 1px,transparent 1px);
                    background-size:40px 40px;}

        .float-orb{position:fixed;border-radius:50%;filter:blur(60px);pointer-events:none;z-index:0;
                   animation:drift var(--dt,8s) ease-in-out infinite alternate}
        @keyframes drift{0%{transform:translate(0,0)}100%{transform:translate(var(--tx,20px),var(--ty,-20px))}}

        .floating-chip{position:fixed;background:rgba(13,20,40,.9);border:1px solid rgba(255,255,255,.12);
                       border-radius:12px;padding:8px 14px;display:flex;align-items:center;gap:8px;
                       backdrop-filter:blur(20px);pointer-events:none;z-index:1;
                       animation:chipfloat var(--cf,5s) ease-in-out infinite alternate}
        @keyframes chipfloat{0%{transform:translateY(0) rotate(var(--cr,-2deg))}100%{transform:translateY(-10px) rotate(var(--cr2,2deg))}}
        .chip-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px}
        .chip-text{font-size:.7rem;font-weight:600;color:#94a3b8;line-height:1.3}
        .chip-val{color:#e2e8f0;font-size:.78rem;font-weight:700}

        .stage{transform-style:preserve-3d;animation:float 6s ease-in-out infinite;z-index:2;position:relative}
        @keyframes float{0%,100%{transform:translateY(0) rotateX(0) rotateY(0)}
                         33%{transform:translateY(-8px) rotateX(2deg) rotateY(-1deg)}
                         66%{transform:translateY(-4px) rotateX(-1deg) rotateY(2deg)}}
        .card-3d{width:420px;max-width:100%;transform-style:preserve-3d;position:relative;transition:transform .1s ease}
        .card-shadow{position:absolute;inset:0;transform:translateZ(-60px) translateY(60px) scale(.88);
                     background:rgba(37,99,235,.35);filter:blur(40px);border-radius:24px;pointer-events:none}
        .card-back{position:absolute;inset:-2px;background:linear-gradient(135deg,rgba(37,99,235,.6),rgba(124,58,237,.6),rgba(6,182,212,.4));
                   border-radius:26px;transform:translateZ(-4px);filter:blur(1px)}
        .card-mid{position:absolute;inset:-1px;background:rgba(255,255,255,.08);border-radius:25px;
                  transform:translateZ(-2px);border:1px solid rgba(255,255,255,.15)}
        .auth-card{background:rgba(13,20,40,.92);border:1px solid rgba(255,255,255,.12);
                   border-radius:24px;overflow:hidden;backdrop-filter:blur(40px);position:relative}
        .auth-card::before{content:'';position:absolute;inset:0;
                           background:linear-gradient(135deg,rgba(255,255,255,.06) 0%,transparent 50%,rgba(37,99,235,.04) 100%);
                           pointer-events:none;z-index:1}
        .card-sheen{position:absolute;inset:0;
                    background:radial-gradient(circle at 50% 20%,rgba(255,255,255,.06) 0%,transparent 60%);
                    pointer-events:none;z-index:3;transition:background .05s}
        .card-inner{position:relative;z-index:2}

        .auth-head{padding:32px 32px 0;text-align:center}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,#2563eb,#7c3aed);
                   border-radius:10px;display:inline-flex;align-items:center;justify-content:center;
                   box-shadow:0 0 20px rgba(37,99,235,.5),inset 0 1px 0 rgba(255,255,255,.2);margin-bottom:6px}
        .logo-icon i{color:#fff;font-size:18px}
        .logo-name{font-size:1.1rem;font-weight:700;color:#fff;letter-spacing:-.02em;display:block}
        .logo-name span{color:#60a5fa}
        .auth-body{padding:8px 32px 32px}
        .badge-wrap{text-align:center;margin:20px 0 6px}
        .badge-secure{display:inline-flex;align-items:center;gap:6px;background:rgba(37,99,235,.15);
                      border:1px solid rgba(37,99,235,.3);color:#60a5fa;font-size:.72rem;font-weight:600;
                      padding:4px 12px;border-radius:999px;letter-spacing:.06em;text-transform:uppercase}
        .badge-dot{width:6px;height:6px;border-radius:50%;background:#3b82f6;display:inline-block;
                   animation:pulse 2s infinite}
        @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.7)}}
        .auth-title{font-size:1.55rem;font-weight:700;color:#fff;text-align:center;margin:0 0 6px;letter-spacing:-.02em}
        .auth-sub{color:#64748b;font-size:.88rem;text-align:center;margin-bottom:24px;line-height:1.5}

        .form-label{display:block;font-size:.78rem;font-weight:600;color:#94a3b8;margin-bottom:6px;
                    letter-spacing:.04em;text-transform:uppercase}
        .field-wrap{position:relative}
        .field-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#475569;pointer-events:none}
        .form-control{background:rgba(255,255,255,.04)!important;border:1px solid rgba(255,255,255,.1)!important;
                      border-radius:12px!important;padding:12px 14px 12px 42px!important;color:#e2e8f0!important;
                      font-size:.92rem!important;transition:all .2s!important}
        .form-control::placeholder{color:#475569}
        .form-control:focus{background:rgba(37,99,235,.08)!important;border-color:rgba(37,99,235,.6)!important;
                            box-shadow:0 0 0 3px rgba(37,99,235,.15),inset 0 1px 0 rgba(255,255,255,.05)!important;color:#e2e8f0!important}
        .field-focus-line{position:absolute;bottom:0;left:12px;right:12px;height:2px;
                          background:linear-gradient(90deg,#2563eb,#7c3aed);border-radius:1px;
                          transform:scaleX(0);transition:transform .3s;transform-origin:left;pointer-events:none}
        .form-control:focus~.field-focus-line{transform:scaleX(1)}

        .btn-auth{width:100%;padding:13px;font-weight:700;font-size:.95rem;
                  background:linear-gradient(135deg,#2563eb 0%,#7c3aed 100%)!important;
                  border:none!important;border-radius:12px!important;color:#fff!important;
                  letter-spacing:.02em;position:relative;overflow:hidden;transition:all .2s!important}
        .btn-auth:hover{transform:translateY(-1px);box-shadow:0 8px 32px rgba(37,99,235,.4),0 2px 8px rgba(0,0,0,.3)!important}
        .btn-auth:active{transform:translateY(1px)}

        .auth-alert{border-radius:10px;padding:10px 14px;font-size:.88rem;margin-bottom:16px}
        .auth-alert.err{background:rgba(153,27,27,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3)}
        .auth-alert.ok{background:rgba(22,101,52,.2);color:#86efac;border:1px solid rgba(34,197,94,.3)}
        .otp-input{letter-spacing:.5em;text-align:center;font-size:1.4rem;font-weight:700}

        @media(max-width:480px){
          .card-3d{width:calc(100vw - 32px)}
          .auth-head{padding:24px 24px 0}
          .auth-body{padding:8px 24px 24px}
          .floating-chip{display:none}
        }
    </style>
</head>
<body>
    <div class="stars" id="stars"></div>
    <div class="grid-plane"></div>
    <div class="float-orb" style="width:300px;height:300px;background:rgba(37,99,235,.2);top:5%;left:-5%;--dt:9s;--tx:30px;--ty:20px"></div>
    <div class="float-orb" style="width:250px;height:250px;background:rgba(124,58,237,.15);bottom:10%;right:-8%;--dt:11s;--tx:-20px;--ty:-30px"></div>

    <div class="floating-chip" style="top:18%;left:4%;--cf:6s;--cr:-3deg;--cr2:1deg">
        <div class="chip-icon" style="background:rgba(37,99,235,.2)"><i class="fas fa-lock" style="color:#60a5fa;font-size:12px"></i></div>
        <div class="chip-text"><div class="chip-val">256-bit</div>Encrypted</div>
    </div>
    <div class="floating-chip" style="bottom:22%;right:4%;--cf:7.5s;--cr:2deg;--cr2:-2deg">
        <div class="chip-icon" style="background:rgba(16,185,129,.2)"><i class="fas fa-shield-halved" style="color:#10b981;font-size:12px"></i></div>
        <div class="chip-text"><div class="chip-val">Verified</div>SSL Secure</div>
    </div>
    <div class="floating-chip" style="top:45%;right:3%;--cf:5s;--cr:-1deg;--cr2:3deg">
        <div class="chip-icon" style="background:rgba(124,58,237,.2)"><i class="fas fa-bolt" style="color:#a78bfa;font-size:12px"></i></div>
        <div class="chip-text"><div class="chip-val">Instant</div>Access</div>
    </div>

    <div class="stage" id="stage">
        <div class="card-3d" id="card3d">
            <div class="card-shadow"></div>
            <div class="card-back"></div>
            <div class="card-mid"></div>
            <div class="auth-card">
                <div class="card-sheen" id="sheen"></div>
                <div class="card-inner">
                    <div class="auth-head">
                        <img src="<?php echo htmlspecialchars($__authLogo); ?>" alt="<?php echo htmlspecialchars($__authName); ?>"
                             style="max-height:52px;max-width:180px;object-fit:contain;display:block;margin:0 auto 6px;"
                             onerror="this.style.display='none';this.nextElementSibling&&this.nextElementSibling.classList.remove('d-none');">
                        <span class="logo-name d-none"><?php echo htmlspecialchars($__authName); ?></span>
                    </div>
                    <div class="auth-body">
                        <div class="badge-wrap">
                            <span class="badge-secure"><span class="badge-dot"></span>Secure Portal</span>
                        </div>
                        <?php echo $content ?? ''; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function(){
        var stars = document.getElementById('stars');
        for(var i = 0; i < 120; i++){
            var s = document.createElement('div');
            s.className = 'star';
            var sz = Math.random() * 2.5 + 0.5;
            s.style.cssText = 'width:'+sz+'px;height:'+sz+'px;top:'+Math.random()*100+'%;left:'+Math.random()*100+'%;--d:'+(Math.random()*4+2)+'s;animation-delay:'+(Math.random()*4)+'s';
            stars.appendChild(s);
        }

        var card  = document.getElementById('card3d');
        var sheen = document.getElementById('sheen');
        var stage = document.getElementById('stage');

        document.addEventListener('mousemove', function(e){
            if(!card) return;
            var rect = card.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top  + rect.height / 2;
            var dx = (e.clientX - cx) / rect.width;
            var dy = (e.clientY - cy) / rect.height;
            card.style.transform = 'rotateX('+(dy*16)+'deg) rotateY('+(-dx*16)+'deg)';
            stage.style.animation = 'none';
            var mx = ((e.clientX - rect.left) / rect.width  * 100).toFixed(1);
            var my = ((e.clientY - rect.top)  / rect.height * 100).toFixed(1);
            sheen.style.background = 'radial-gradient(circle at '+mx+'% '+my+'%,rgba(255,255,255,.1) 0%,transparent 55%)';
        });

        document.addEventListener('mouseleave', function(){
            if(card)  card.style.transform  = '';
            if(stage) stage.style.animation = 'float 6s ease-in-out infinite';
            if(sheen) sheen.style.background = '';
        });

        document.querySelectorAll('.form-control').forEach(function(inp){
            inp.addEventListener('focus', function(){
                var line = this.parentNode.querySelector('.field-focus-line');
                if(line) line.style.transform = 'scaleX(1)';
            });
            inp.addEventListener('blur', function(){
                var line = this.parentNode.querySelector('.field-focus-line');
                if(line) line.style.transform = 'scaleX(0)';
            });
        });
    })();
    </script>
</body>
</html>