// assets/js/drive.js — importação automática de uma pasta do Google Drive: lista, depois importa arquivo a arquivo com progresso
(function () {
  'use strict';
  var root = document.getElementById('drive');
  if (!root) return;
  var csrf = document.querySelector('meta[name="csrf-token"]').content, albumId = parseInt(root.dataset.album, 10);
  var link = document.getElementById('driveLink'), listBtn = document.getElementById('listBtn'), err = document.getElementById('driveError');
  var card = document.getElementById('resultCard'), rows = document.getElementById('fileRows'), importBtn = document.getElementById('importBtn'), skip = document.getElementById('skipImported');
  var files = [], running = false;
  function api(url, body) { return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) }).then(function (r) { return r.json().catch(function () { return { ok: false, mensagem: 'Resposta inválida do servidor (HTTP ' + r.status + ').' }; }); }); }
  function showErr(m) { err.textContent = m; err.classList.toggle('d-none', !m); }
  function status(f, text, cls) { var td = f.row.querySelector('[data-status]'); td.innerHTML = '<span class="badge ' + (cls || 'text-bg-light border') + '">' + text + '</span>'; }
  function render() {
    rows.innerHTML = '';
    files.forEach(function (f) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td class="text-truncate" style="max-width:320px"></td><td class="text-nowrap"></td><td class="text-nowrap"></td><td data-status></td>';
      tr.children[0].textContent = f.name; tr.children[0].title = f.name; tr.children[1].textContent = f.size_label || ''; tr.children[2].textContent = f.width ? f.width + '×' + f.height : '';
      f.row = tr; rows.appendChild(tr);
      status(f, f.imported ? 'já importada' : 'pendente', f.imported ? 'text-bg-secondary' : '');
    });
    syncCount();
  }
  function pending() { return files.filter(function (f) { return !f.done && !(skip.checked && f.imported); }); }
  function syncCount() { var n = pending().length; document.getElementById('importCount').textContent = n ? n + ' foto(s)' : ''; importBtn.disabled = n === 0 || running; }
  listBtn.addEventListener('click', function () {
    showErr(''); listBtn.disabled = true; listBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Consultando o Drive…';
    api(root.dataset.list, { album_id: albumId, link: link.value }).then(function (r) {
      if (!r.ok) throw new Error(r.mensagem || 'Não foi possível listar a pasta.');
      files = r.data.files; document.getElementById('folderName').textContent = r.data.folder.name || 'Pasta'; document.getElementById('fileCount').textContent = files.length + ' imagem(ns)';
      card.classList.remove('d-none'); document.getElementById('doneFooter').classList.add('d-none'); render();
      if (!files.length) showErr('A pasta não tem imagens (JPG, PNG, WebP ou HEIC) no primeiro nível.');
    }).catch(function (e) { showErr(e.message); }).then(function () { listBtn.disabled = false; listBtn.innerHTML = '<i class="bi bi-search"></i> Listar fotos'; });
  });
  skip.addEventListener('change', syncCount);
  importBtn.addEventListener('click', function () {
    var list = pending(); if (!list.length || running) return;
    running = true; syncCount(); listBtn.disabled = true;
    var wrap = document.getElementById('progressWrap'), bar = document.getElementById('progressBar'), txt = document.getElementById('progressText'), pct = document.getElementById('progressPct');
    wrap.classList.remove('d-none'); var i = 0, ok = 0, fail = 0, skipped = 0;
    function next() {
      if (i >= list.length) {
        running = false; listBtn.disabled = false; syncCount();
        txt.textContent = 'Concluído'; bar.className = 'progress-bar bg-success'; bar.style.width = '100%'; pct.textContent = '100%';
        var foot = document.getElementById('doneFooter'); foot.classList.remove('d-none');
        foot.innerHTML = ok + ' importada(s)' + (skipped ? ', ' + skipped + ' já existiam' : '') + (fail ? ', <span class="text-danger">' + fail + ' com erro</span>' : '') + '. <a href="' + root.dataset.albumUrl + '">Ver o álbum</a>';
        return;
      }
      var f = list[i++]; status(f, 'baixando…', 'text-bg-warning'); txt.textContent = 'Importando ' + f.name; pct.textContent = Math.round((i - 1) * 100 / list.length) + '%'; bar.style.width = Math.round((i - 1) * 100 / list.length) + '%';
      api(root.dataset.import, { album_id: albumId, file_id: f.id, name: f.name }).then(function (r) {
        if (!r.ok) throw new Error(r.mensagem || 'Falha.');
        f.done = true;
        if (r.data.skipped) { skipped++; status(f, 'já importada', 'text-bg-secondary'); }
        else if (r.data.duplicate) { skipped++; status(f, 'repetida (igual a ' + r.data.duplicate.name + ')', 'text-bg-secondary'); }
        else { ok++; status(f, 'importada', 'text-bg-success'); f.row.children[0].innerHTML = '<a href="' + r.data.photo.url + '"></a>'; f.row.children[0].firstChild.textContent = f.name; }
      }).catch(function (e) { fail++; status(f, 'erro: ' + e.message, 'text-bg-danger'); }).then(next);
    }
    next();
  });
  window.addEventListener('beforeunload', function (e) { if (running) { e.preventDefault(); e.returnValue = ''; } });
})();
