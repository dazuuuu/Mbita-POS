<?php
// Phone-style PIN keypad. Expects: $pinFieldName (default pin), optional $pinMaxLength (5).
$pinFieldName = $pinFieldName ?? 'pin';
$pinMaxLength = (int) ($pinMaxLength ?? 5);
$pinMinLength = 4;
$pinPadId = 'pinPad_' . bin2hex(random_bytes(3));
?>
<div class="phone-lock" id="<?php echo htmlspecialchars($pinPadId); ?>">
  <div class="phone-lock-dots" aria-hidden="true">
    <?php for ($i = 0; $i < $pinMaxLength; $i++): ?>
    <span class="phone-lock-dot"></span>
    <?php endfor; ?>
  </div>
  <input type="hidden" name="<?php echo htmlspecialchars($pinFieldName); ?>" class="phone-lock-input" value="" autocomplete="off">
  <div class="phone-lock-grid">
    <?php foreach (['1','2','3','4','5','6','7','8','9'] as $d): ?>
    <button type="button" class="phone-lock-key" data-digit="<?php echo $d; ?>"><?php echo $d; ?></button>
    <?php endforeach; ?>
    <span class="phone-lock-key phone-lock-key-spacer"></span>
    <button type="button" class="phone-lock-key" data-digit="0">0</button>
    <button type="button" class="phone-lock-key phone-lock-key-del" data-action="del" aria-label="Delete">
      <i class="fas fa-delete-left"></i>
    </button>
  </div>
</div>
<style>
  .phone-lock { max-width: 280px; margin: 0 auto 8px; user-select: none; -webkit-user-select: none; }
  .phone-lock-dots { display: flex; justify-content: center; gap: 14px; margin: 8px 0 28px; min-height: 16px; }
  .phone-lock-dot {
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid #c9a227; background: transparent;
    transition: all .15s ease;
  }
  .phone-lock-dot.filled { background: #c9a227; border-color: #c9a227; transform: scale(1.05); box-shadow: 0 0 8px rgba(201,162,39,.45); }
  .phone-lock-dot.error { border-color: #f87171; background: rgba(248,113,113,.3); animation: pinShake .35s ease; }
  @keyframes pinShake {
    0%,100%{ transform: translateX(0); }
    25%{ transform: translateX(-6px); }
    75%{ transform: translateX(6px); }
  }
  .phone-lock-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
  }
  .phone-lock-key {
    aspect-ratio: 1; border-radius: 50%;
    background: #0a0a0a !important;
    color: #c9a227 !important;
    border: 2px solid #c9a227 !important;
    font-size: 1.55rem; font-weight: 700; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,.25);
    transition: background .12s, color .12s, border-color .12s, transform .08s, box-shadow .12s;
    -webkit-tap-highlight-color: transparent;
  }
  .phone-lock-key:hover {
    background: #141414 !important;
    color: #e8c547 !important;
    border-color: #e8c547 !important;
  }
  .phone-lock-key:active {
    background: #c9a227 !important;
    color: #0a0a0a !important;
    border-color: #9a7b1a !important;
    transform: scale(.94);
    box-shadow: 0 2px 6px rgba(0,0,0,.2);
  }
  .phone-lock-key-spacer { visibility: hidden; pointer-events: none; border: none !important; background: none !important; box-shadow: none !important; }
  .phone-lock-key-del {
    font-size: 1.1rem;
    background: #fff !important;
    color: #0a0a0a !important;
    border: 2px solid #0a0a0a !important;
  }
  .phone-lock-key-del:hover {
    background: #f5f5f5 !important;
    color: #c9a227 !important;
    border-color: #c9a227 !important;
  }
  .phone-lock-key-del:active {
    background: #0a0a0a !important;
    color: #c9a227 !important;
    border-color: #c9a227 !important;
  }
</style>
<script>
(function(){
  var root = document.getElementById(<?php echo json_encode($pinPadId); ?>);
  if (!root) return;
  var input = root.querySelector('.phone-lock-input');
  var dots = root.querySelectorAll('.phone-lock-dot');
  var form = root.closest('form');
  var minLen = <?php echo (int) $pinMinLength; ?>;
  var maxLen = <?php echo (int) $pinMaxLength; ?>;
  var submitTimer = null;

  function render() {
    var len = input.value.length;
    dots.forEach(function(dot, i) {
      dot.classList.toggle('filled', i < len);
      dot.classList.remove('error');
    });
  }

  function append(d) {
    if (input.value.length >= maxLen) return;
    input.value += d;
    render();
    clearTimeout(submitTimer);
    if (input.value.length >= minLen) {
      submitTimer = setTimeout(maybeSubmit, input.value.length >= maxLen ? 80 : 450);
    }
  }

  function del() {
    input.value = input.value.slice(0, -1);
    render();
    clearTimeout(submitTimer);
  }

  function maybeSubmit() {
    if (!form || input.value.length < minLen) return;
    form.requestSubmit ? form.requestSubmit() : form.submit();
  }

  function shake() {
    dots.forEach(function(d){ d.classList.add('error'); });
    input.value = '';
    setTimeout(render, 400);
  }

  root.querySelectorAll('.phone-lock-key[data-digit]').forEach(function(btn){
    btn.addEventListener('click', function(){ append(btn.getAttribute('data-digit')); });
  });
  var delBtn = root.querySelector('[data-action="del"]');
  if (delBtn) delBtn.addEventListener('click', del);

  root.shakePin = shake;
  render();
})();
</script>
