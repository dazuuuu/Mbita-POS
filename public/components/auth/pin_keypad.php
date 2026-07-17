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
  .phone-lock { max-width: 260px; margin: 0 auto 8px; user-select: none; -webkit-user-select: none; }
  .phone-lock-dots { display: flex; justify-content: center; gap: 12px; margin: 6px 0 24px; min-height: 12px; }
  .phone-lock-dot {
    width: 10px; height: 10px; border-radius: 50%;
    border: 1.5px solid #ddd; background: transparent;
    transition: all .15s ease;
  }
  .phone-lock-dot.filled { background: #b8956b; border-color: #b8956b; }
  .phone-lock-dot.error { border-color: #e57373; animation: pinShake .35s ease; }
  @keyframes pinShake {
    0%,100%{ transform: translateX(0); }
    25%{ transform: translateX(-6px); }
    75%{ transform: translateX(6px); }
  }
  .phone-lock-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
  }
  .phone-lock-key {
    aspect-ratio: 1; border-radius: 50%;
    background: #fff !important;
    color: #333 !important;
    border: 1px solid #eee !important;
    font-size: 1.45rem; font-weight: 500; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: color .12s, border-color .12s, background .12s, transform .08s;
    -webkit-tap-highlight-color: transparent;
  }
  .phone-lock-key:hover {
    color: #b8956b !important;
    border-color: #d4c4a8 !important;
  }
  .phone-lock-key:active {
    color: #b8956b !important;
    background: rgba(184,149,107,.08) !important;
    border-color: #b8956b !important;
    transform: scale(.96);
  }
  .phone-lock-key-spacer { visibility: hidden; pointer-events: none; border: none !important; }
  .phone-lock-key-del {
    font-size: 1rem;
    color: #999 !important;
    border-color: transparent !important;
    background: transparent !important;
  }
  .phone-lock-key-del:hover { color: #b8956b !important; }
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
