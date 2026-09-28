// public/assets/js/files.js — seleção para ZIP, cópia de link e ações da listagem
(function () {
  'use strict';

  // Seleção múltipla para download em ZIP
  var bar = document.getElementById('selectionBar');
  var form = document.getElementById('zipForm');
  if (bar && form) {
    var boxes = document.querySelectorAll('input[data-select]');
    var count = bar.querySelector('[data-count]');
    var update = function () {
      var ids = [];
      boxes.forEach(function (b) { if (b.checked) ids.push(b.value); });
      form.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
      ids.forEach(function (id) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; form.appendChild(i); });
      count.textContent = ids.length;
      bar.classList.toggle('d-none', ids.length === 0);
    };
    boxes.forEach(function (b) { b.addEventListener('change', update); });
    var all = document.getElementById('selectAll');
    if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); update(); });
    var clear = bar.querySelector('[data-clear]');
    if (clear) clear.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); if (all) all.checked = false; update(); });
  }

  // Copiar URL de compartilhamento
  document.querySelectorAll('[data-copy-input]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.copyInput);
      if (!input) return;
      input.select();
      var done = function () { var old = btn.innerHTML; btn.innerHTML = '<i class="bi bi-check"></i> Copiado'; setTimeout(function () { btn.innerHTML = old; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(input.value).then(done); else { document.execCommand('copy'); done(); }
    });
  });

  // Filtro de pasta: "outro evento" no formulário público
  var evSel = document.getElementById('event');
  var evOther = document.getElementById('event_other_wrap');
  if (evSel && evOther) {
    var sync = function () { evOther.classList.toggle('d-none', evSel.value !== '__outro'); };
    evSel.addEventListener('change', sync); sync();
  }
  var minSel = document.getElementById('ministry_id');
  var minOther = document.getElementById('ministry_other_wrap');
  if (minSel && minOther) {
    var syncM = function () { minOther.classList.toggle('d-none', minSel.value !== '__outro'); };
    minSel.addEventListener('change', syncM); syncM();
  }

  // Botão "concluir envio" só habilita quando não há upload em andamento
  var up = document.querySelector('[data-uploader]');
  var finishBtn = document.getElementById('finishBtn');
  if (up && finishBtn) {
    up.addEventListener('uploader:change', function (e) {
      var d = e.detail, busy = d.total > 0 && (d.done + d.err) < d.total;
      finishBtn.classList.toggle('disabled', busy);
      finishBtn.setAttribute('aria-disabled', busy ? 'true' : 'false');
    });
  }
})();
