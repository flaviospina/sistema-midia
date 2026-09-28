// public/assets/js/app.js — comportamentos comuns (vanilla)
(function () {
  'use strict';

  // Confirmação em formulários com data-confirm
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      ev.preventDefault();
    }
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
        alert('A foto deve ter no máximo ' + (photo.dataset.maxMb || '5') + ' MB.');
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
