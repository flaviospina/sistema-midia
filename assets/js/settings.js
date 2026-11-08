// assets/js/settings.js — tela de configurações: aplicar a marca d'água nas fotos antigas em lotes; remover chave do Google
(function () {
  'use strict';
  var keep = document.querySelector('input[name="google_api_key_keep"]'), rm = document.querySelector('[data-toggle-keep]');
  if (keep && rm) rm.addEventListener('change', function () { keep.value = rm.checked ? '0' : '1'; });
  var btn = document.getElementById('stampBtn');
  if (!btn) return;
  var csrf = document.querySelector('meta[name="csrf-token"]').content, count = document.getElementById('unstampedCount'), prog = document.getElementById('stampProgress'), st = document.getElementById('stampStatus');
  btn.addEventListener('click', function () {
    window.appDialog.confirm('Aplicar a logo em todas as fotos antigas? A marca é gravada na própria foto e não pode ser desfeita.', { title: 'Marca d\'água', okLabel: 'Aplicar' }).then(function (ok) {
      if (!ok) return;
      btn.disabled = true; prog.classList.remove('d-none'); var total = parseInt(count.textContent, 10) || 0, done = 0;
      function step() {
        fetch(btn.dataset.url, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: '{}' })
          .then(function (r) { return r.json(); })
          .then(function (r) {
            if (!r.ok) throw new Error(r.mensagem || 'Falha.');
            done += r.data.done; count.textContent = r.data.remaining; st.textContent = 'Aplicada em ' + done + ' de ' + total + '…';
            if (r.data.remaining > 0 && (r.data.done > 0)) return step();
            prog.classList.add('d-none'); st.textContent = 'Concluído: ' + done + ' foto(s) marcadas' + (r.data.remaining ? ' (' + r.data.remaining + ' não puderam ser marcadas).' : '.');
          }).catch(function (e) { prog.classList.add('d-none'); st.textContent = 'Erro: ' + e.message; btn.disabled = false; });
      }
      step();
    });
  });
})();
