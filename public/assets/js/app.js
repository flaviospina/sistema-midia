// public/assets/js/app.js — comportamentos comuns (vanilla)
(function () {
  'use strict';

  // Barras de progresso estáticas (largura vem de data-width; CSP não permite style inline)
  document.querySelectorAll('[data-width]').forEach(function (el) { el.style.width = el.dataset.width + '%'; });

  // Indisponibilidade: campos conforme o tipo
  var kinds = document.querySelectorAll('input[name="kind"]');
  if (kinds.length) {
    var syncKind = function () {
      var k = (document.querySelector('input[name="kind"]:checked') || {}).value;
      document.querySelectorAll('[data-kind]').forEach(function (el) { el.classList.toggle('d-none', el.dataset.kind !== k); });
    };
    kinds.forEach(function (r) { r.addEventListener('change', syncKind); }); syncKind();
  }
  // Recorrência: "semana do mês" só para frequência mensal
  var freq = document.getElementById('frequency');
  var wom = document.getElementById('weekOfMonthWrap');
  if (freq && wom) { var syncF = function () { wom.classList.toggle('d-none', freq.value !== 'mensal'); }; freq.addEventListener('change', syncF); syncF(); }

  // Selects que enviam o formulário ao mudar (CSP não permite onchange inline)
  document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) { sel.addEventListener('change', function () { sel.form.submit(); }); });

  // Diálogos com modal do Bootstrap (substituem window.confirm/alert nativos)
  var dialog = (function () {
    var modalEl = null, instance = null, resolver = null;
    function build() {
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade app-dialog';
      modalEl.tabIndex = -1;
      modalEl.setAttribute('aria-modal', 'true');
      modalEl.setAttribute('role', 'dialog');
      modalEl.innerHTML =
        '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
        '<div class="modal-body"><div class="d-flex gap-3 align-items-start">' +
        '<div class="app-dialog__icon" data-icon></div>' +
        '<div class="flex-grow-1"><h2 class="h6 mb-1" data-title></h2><div class="app-dialog__text" data-text></div></div></div></div>' +
        '<div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary btn-sm" data-cancel>Cancelar</button>' +
        '<button type="button" class="btn btn-primary btn-sm" data-ok>OK</button></div></div></div>';
      document.body.appendChild(modalEl);
      instance = new bootstrap.Modal(modalEl, { backdrop: 'static' });
      modalEl.querySelector('[data-ok]').addEventListener('click', function () { finish(true); });
      modalEl.querySelector('[data-cancel]').addEventListener('click', function () { finish(false); });
      modalEl.addEventListener('hidden.bs.modal', function () { finish(false); });
      modalEl.addEventListener('shown.bs.modal', function () { modalEl.querySelector('[data-ok]').focus(); });
    }
    function finish(value) {
      if (!resolver) return;
      var r = resolver; resolver = null;
      instance.hide();
      r(value);
    }
    function open(opts) {
      if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return Promise.resolve(opts.confirm ? window.confirm(opts.text) : (window.alert(opts.text), true));
      }
      if (!modalEl) build();
      var icons = { question: 'bi-question-circle', warning: 'bi-exclamation-triangle', danger: 'bi-exclamation-octagon', info: 'bi-info-circle', success: 'bi-check-circle' };
      var type = opts.type || (opts.confirm ? 'question' : 'info');
      modalEl.querySelector('[data-icon]').className = 'app-dialog__icon app-dialog__icon--' + type;
      modalEl.querySelector('[data-icon]').innerHTML = '<i class="bi ' + (icons[type] || icons.info) + '"></i>';
      modalEl.querySelector('[data-title]').textContent = opts.title || (opts.confirm ? 'Confirmar' : 'Aviso');
      modalEl.querySelector('[data-text]').textContent = opts.text;
      modalEl.querySelector('[data-cancel]').classList.toggle('d-none', !opts.confirm);
      var ok = modalEl.querySelector('[data-ok]');
      ok.textContent = opts.okLabel || (opts.confirm ? 'Sim, continuar' : 'OK');
      ok.className = 'btn btn-sm ' + (type === 'danger' ? 'btn-danger' : 'btn-primary');
      return new Promise(function (resolve) { resolver = resolve; instance.show(); });
    }
    return {
      confirm: function (text, opts) { return open(Object.assign({ confirm: true, text: text }, opts || {})); },
      alert: function (text, opts) { return open(Object.assign({ confirm: false, text: text }, opts || {})); }
    };
  })();
  window.appDialog = dialog;

  // Confirmação em formulários com data-confirm (data-confirm-type="danger" deixa o botão vermelho; data-confirm-ok muda o rótulo)
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.dataset.confirm || form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
    ev.preventDefault();
    var submitter = ev.submitter || null;
    dialog.confirm(form.dataset.confirm, { type: form.dataset.confirmType || 'warning', okLabel: form.dataset.confirmOk }).then(function (ok) {
      if (!ok) return;
      form.dataset.confirmed = '1';
      if (typeof form.requestSubmit === 'function') { form.requestSubmit(submitter || undefined); } else { form.submit(); }
    });
  });

  // Botão "Copiar"
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-copy]');
    if (!btn || !navigator.clipboard) return;
    navigator.clipboard.writeText(btn.dataset.copy).then(function () {
      var old = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check"></i> Copiado';
      setTimeout(function () { btn.innerHTML = old; }, 1500);
    });
  });

  // Máscara leve de WhatsApp: (11) 98888-7777
  document.querySelectorAll('input[data-mask="phone"]').forEach(function (input) {
    input.addEventListener('input', function () {
      var d = input.value.replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '').slice(0, 11);
      var out = d;
      if (d.length > 2) out = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length > 7) out = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length - 4) + '-' + d.slice(-4);
      input.value = out;
    });
  });

  // Mostrar/ocultar senha
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.togglePassword);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.querySelector('i').className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  });

  // Pré-visualização da foto
  var photo = document.getElementById('photo');
  var preview = document.getElementById('photoPreview');
  if (photo && preview) {
    photo.addEventListener('change', function () {
      var f = photo.files && photo.files[0];
      if (!f) return;
      if (f.size > (parseInt(photo.dataset.maxMb || '5', 10) * 1024 * 1024)) {
        dialog.alert('A foto deve ter no máximo ' + (photo.dataset.maxMb || '5') + ' MB.', { type: 'warning', title: 'Foto muito grande' });
        photo.value = '';
        return;
      }
      var url = URL.createObjectURL(f);
      preview.innerHTML = '<img src="' + url + '" class="avatar avatar-96 rounded-circle" alt="">';
    });
  }

  // Campos de perfil: mostra bloco "equipe de mídia" só para perfis da equipe
  var role = document.getElementById('role');
  var mediaBlock = document.getElementById('mediaFields');
  if (role && mediaBlock) {
    var toggle = function () {
      var isMedia = ['admin', 'coordenador', 'membro_midia'].indexOf(role.value) !== -1;
      mediaBlock.classList.toggle('d-none', !isMedia);
    };
    role.addEventListener('change', toggle);
    toggle();
  }

  // Linhas de função: habilita campos só quando a função está marcada
  document.querySelectorAll('[data-func-row]').forEach(function (row) {
    var check = row.querySelector('input[type="checkbox"][name$="[on]"]');
    var fields = row.querySelectorAll('select, input[type="date"], input[type="checkbox"]:not([name$="[on]"])');
    var sync = function () { fields.forEach(function (f) { f.disabled = !check.checked; }); };
    check.addEventListener('change', sync);
    sync();
  });

  // Liderança de ministério: só permite marcar "líder" se o ministério estiver marcado
  document.querySelectorAll('[data-ministry-row]').forEach(function (row) {
    var member = row.querySelector('input[name="ministries[]"]');
    var leader = row.querySelector('input[name="leader_of[]"]');
    if (!member || !leader) return;
    var sync = function () { leader.disabled = !member.checked; if (!member.checked) leader.checked = false; };
    member.addEventListener('change', sync);
    sync();
  });
})();

// Fase 6: "selecionar todos" numa tabela + botão que depende de seleção
(function () {
  'use strict';
  document.querySelectorAll('[data-check-all]').forEach(function (master) {
    var form = document.getElementById(master.dataset.checkAll);
    if (!form) return;
    var boxes = function () { return form.querySelectorAll('input[type="checkbox"][name$="[]"]'); };
    var btn = document.getElementById('labelsBtn');
    var sync = function () { if (btn) btn.disabled = !form.querySelector('input[type="checkbox"][name$="[]"]:checked'); };
    master.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = master.checked; }); sync(); });
    form.addEventListener('change', sync);
    sync();
  });

  // Linhas repetíveis (checklist pré-culto): clona o <template> com índice novo
  document.querySelectorAll('form[data-repeat]').forEach(function (form) {
    var rows = form.querySelector('[data-rows]');
    var tpl = form.querySelector('[data-row-template]');
    var add = form.querySelector('[data-add-row]');
    if (!rows || !tpl || !add) return;
    add.addEventListener('click', function () {
      var n = rows.querySelectorAll('[data-row]').length + 1000; // evita colidir com índices existentes
      var html = tpl.innerHTML.replace(/__N__/g, String(n));
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      var row = wrap.firstElementChild;
      rows.insertBefore(row, tpl);
      var input = row.querySelector('input[type="text"]');
      if (input) input.focus();
    });
  });
})();
