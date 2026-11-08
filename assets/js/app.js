// assets/js/app.js — comportamentos comuns do Envio de Mídias ADMoema (vanilla, sem script inline por causa da CSP)
(function () {
  'use strict';
  document.querySelectorAll('[data-width]').forEach(function (el) { el.style.width = el.dataset.width + '%'; });
  document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) { sel.addEventListener('change', function () { sel.form.submit(); }); });
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.togglePassword);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.querySelector('i').className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  });

  // Diálogos com modal do Bootstrap (substituem window.confirm/alert)
  var dialog = (function () {
    var modalEl = null, instance = null, resolver = null;
    function build() {
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade app-dialog'; modalEl.tabIndex = -1; modalEl.setAttribute('role', 'dialog'); modalEl.setAttribute('aria-modal', 'true');
      modalEl.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body"><div class="d-flex gap-3 align-items-start"><div class="app-dialog__icon" data-icon></div><div class="flex-grow-1"><h2 class="h6 mb-1" data-title></h2><div class="app-dialog__text" data-text></div></div></div></div><div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary btn-sm" data-cancel>Cancelar</button><button type="button" class="btn btn-primary btn-sm" data-ok>OK</button></div></div></div>';
      document.body.appendChild(modalEl);
      instance = new bootstrap.Modal(modalEl, { backdrop: 'static' });
      modalEl.querySelector('[data-ok]').addEventListener('click', function () { finish(true); });
      modalEl.querySelector('[data-cancel]').addEventListener('click', function () { finish(false); });
      modalEl.addEventListener('hidden.bs.modal', function () { finish(false); });
      modalEl.addEventListener('shown.bs.modal', function () { modalEl.querySelector('[data-ok]').focus(); });
    }
    function finish(v) { if (!resolver) return; var r = resolver; resolver = null; instance.hide(); r(v); }
    function open(o) {
      if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return Promise.resolve(o.confirm ? window.confirm(o.text) : (window.alert(o.text), true));
      if (!modalEl) build();
      var icons = { question: 'bi-question-circle', warning: 'bi-exclamation-triangle', danger: 'bi-exclamation-octagon', info: 'bi-info-circle', success: 'bi-check-circle' };
      var type = o.type || (o.confirm ? 'question' : 'info');
      modalEl.querySelector('[data-icon]').className = 'app-dialog__icon app-dialog__icon--' + type;
      modalEl.querySelector('[data-icon]').innerHTML = '<i class="bi ' + (icons[type] || icons.info) + '"></i>';
      modalEl.querySelector('[data-title]').textContent = o.title || (o.confirm ? 'Confirmar' : 'Aviso');
      modalEl.querySelector('[data-text]').textContent = o.text;
      modalEl.querySelector('[data-cancel]').classList.toggle('d-none', !o.confirm);
      var ok = modalEl.querySelector('[data-ok]'); ok.textContent = o.okLabel || (o.confirm ? 'Sim, continuar' : 'OK'); ok.className = 'btn btn-sm ' + (type === 'danger' ? 'btn-danger' : 'btn-primary');
      return new Promise(function (resolve) { resolver = resolve; instance.show(); });
    }
    return { confirm: function (t, o) { return open(Object.assign({ confirm: true, text: t }, o || {})); }, alert: function (t, o) { return open(Object.assign({ confirm: false, text: t }, o || {})); } };
  })();
  window.appDialog = dialog;

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.dataset.confirm || form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
    ev.preventDefault();
    var submitter = ev.submitter || null;
    dialog.confirm(form.dataset.confirm, { type: form.dataset.confirmType || 'warning', okLabel: form.dataset.confirmOk }).then(function (ok) {
      if (!ok) return;
      form.dataset.confirmed = '1';
      if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter || undefined); else form.submit();
    });
  });

  // Medidor de força da senha
  document.querySelectorAll('input[data-strength]').forEach(function (input) {
    var bar = document.getElementById(input.dataset.strength);
    if (!bar) return;
    input.addEventListener('input', function () {
      var v = input.value, score = 0;
      if (v.length >= 10) score += 30; if (v.length >= 14) score += 15;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score += 20; if (/\d/.test(v)) score += 15; if (/[^A-Za-z0-9]/.test(v)) score += 20;
      if (/admoema|12345|senha|password|qwert/i.test(v)) score = Math.min(score, 20);
      bar.style.width = Math.min(100, score) + '%';
      bar.className = 'progress-bar ' + (score < 45 ? 'bg-danger' : score < 70 ? 'bg-warning' : 'bg-success');
    });
  });

  // Nova pasta: sugere o nome pelo dia da semana e mostra a pasta que será criada
  var albumForm = document.getElementById('albumForm');
  if (albumForm && albumForm.dataset.suggestUrl) {
    var dateEl = document.getElementById('event_date'), nameEl = document.getElementById('name'), prev = document.getElementById('folderPreview');
    var hint = document.getElementById('weekdayHint'), sHint = document.getElementById('suggestHint'), exists = document.getElementById('existsHint'), existsLink = document.getElementById('existsLink');
    var weekdays = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    var userTyped = false, lastSuggested = nameEl ? nameEl.value : '';
    function folderOf(date, name) {
      var ascii = name.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9 _-]+/g, '').replace(/\s+/g, ' ').trim();
      return date + ' ' + (ascii || 'Evento');
    }
    function refreshPreview() { if (prev && dateEl) prev.textContent = folderOf(dateEl.value || '????-??-??', nameEl.value); }
    function suggest() {
      if (!dateEl || !dateEl.value) return;
      fetch(albumForm.dataset.suggestUrl + '?data=' + encodeURIComponent(dateEl.value), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r.ok) return;
          var d = r.data;
          if (hint) hint.textContent = weekdays[d.weekday].charAt(0).toUpperCase() + weekdays[d.weekday].slice(1);
          if (d.name) {
            if (!userTyped || nameEl.value === lastSuggested || nameEl.value === '') { nameEl.value = d.name; lastSuggested = d.name; }
            if (sHint) sHint.textContent = 'Sugestão para ' + weekdays[d.weekday] + ': "' + d.name + '". Pode manter ou escrever outro nome.';
          } else {
            if (nameEl.value === lastSuggested) { nameEl.value = ''; lastSuggested = ''; }
            if (sHint) sHint.textContent = 'Não há culto fixo em ' + weekdays[d.weekday] + ': escreva o nome do evento.';
          }
          if (exists) { exists.classList.toggle('d-none', !d.exists); }
          refreshPreview();
        }).catch(function () { refreshPreview(); });
    }
    if (dateEl) { dateEl.addEventListener('change', suggest); }
    if (nameEl) { nameEl.addEventListener('input', function () { userTyped = true; refreshPreview(); if (exists) exists.classList.add('d-none'); }); }
    if (existsLink) { existsLink.addEventListener('click', function (ev) { ev.preventDefault(); window.location.href = albumForm.dataset.suggestUrl.replace('/albuns/sugestao', '/') + '?q=' + encodeURIComponent(nameEl.value); }); }
    refreshPreview();
    if (dateEl && !nameEl.value) suggest(); else if (dateEl) suggest();
  }
})();
