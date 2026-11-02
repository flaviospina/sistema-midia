// assets/js/upload.js — envio de fotos em pedaços com retomada, retentativa e pré-visualização
(function () {
  'use strict';
  var root = document.getElementById('uploader');
  if (!root) return;
  var endpoint = root.dataset.endpoint, albumId = parseInt(root.dataset.album, 10), CHUNK = parseInt(root.dataset.chunk, 10), MAX = parseInt(root.dataset.max, 10);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var queue = [], running = false, PARALLEL = 2, RETRIES = 4;
  var dz = document.getElementById('dropzone'), input = document.getElementById('fileInput'), cam = document.getElementById('cameraInput');
  var list = document.getElementById('queue'), startBtn = document.getElementById('startBtn'), pending = document.getElementById('pendingCount'), overall = document.getElementById('overall');

  function api(path, body, json) {
    var opts = { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf } };
    if (json) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); } else { opts.body = body; }
    return fetch(endpoint + path, opts).then(function (r) { return r.json().catch(function () { return { ok: false, mensagem: 'Resposta inválida do servidor (HTTP ' + r.status + ').' }; }); });
  }
  function fmt(b) { var u = ['B', 'KB', 'MB', 'GB'], i = 0; while (b >= 1024 && i < 3) { b /= 1024; i++; } return b.toFixed(i ? 1 : 0).replace('.', ',') + ' ' + u[i]; }
  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  function add(files) {
    Array.prototype.forEach.call(files, function (f) {
      if (!/\.(jpe?g|png|webp|heic|heif)$/i.test(f.name)) { note(f.name + ': só fotos (JPG, PNG, WebP, HEIC).'); return; }
      if (f.size > MAX) { note(f.name + ': maior que o limite (' + fmt(MAX) + ').'); return; }
      var item = { file: f, state: 'fila', el: null };
      item.el = render(item); queue.push(item);
    });
    sync();
  }
  function note(msg) { var d = document.createElement('div'); d.className = 'alert alert-warning py-1 small mt-2'; d.textContent = msg; list.prepend(d); }
  function render(item) {
    var el = document.createElement('div'); el.className = 'upload-item';
    el.innerHTML = '<div class="upload-thumb"><i class="bi bi-image"></i></div><div class="flex-grow-1 min-w-0"><div class="d-flex justify-content-between gap-2 small"><span class="text-truncate fw-semibold" data-name></span><span class="text-muted text-nowrap" data-size></span></div><div class="progress mt-1"><div class="progress-bar" data-bar></div></div><div class="small text-muted mt-1" data-status>Na fila</div></div><div data-actions class="d-flex gap-1"></div>';
    el.querySelector('[data-name]').textContent = item.file.name; el.querySelector('[data-size]').textContent = fmt(item.file.size);
    list.appendChild(el);
    if (/\.(jpe?g|png|webp)$/i.test(item.file.name) && window.createImageBitmap) {
      createImageBitmap(item.file, { resizeWidth: 112, resizeQuality: 'low' }).then(function (bmp) {
        var c = document.createElement('canvas'); c.width = bmp.width; c.height = bmp.height; c.getContext('2d').drawImage(bmp, 0, 0);
        var t = el.querySelector('.upload-thumb'); t.innerHTML = ''; var img = document.createElement('img'); img.src = c.toDataURL('image/jpeg', .7); t.appendChild(img);
      }).catch(function () {});
    }
    return el;
  }
  function status(item, text, pct, cls) {
    item.el.querySelector('[data-status]').textContent = text;
    if (pct !== undefined) { var bar = item.el.querySelector('[data-bar]'); bar.style.width = pct + '%'; bar.className = 'progress-bar ' + (cls || ''); }
  }
  function sync() {
    var waiting = queue.filter(function (i) { return i.state === 'fila'; }).length;
    startBtn.classList.toggle('d-none', waiting === 0 || running); pending.textContent = waiting ? waiting + ' foto(s)' : '';
    var done = queue.filter(function (i) { return i.state === 'ok'; }).length, err = queue.filter(function (i) { return i.state === 'erro'; }).length;
    overall.textContent = queue.length ? done + '/' + queue.length + ' enviada(s)' + (err ? ' · ' + err + ' com erro' : '') : '';
    if (queue.length && !running && waiting === 0 && err === 0) {
      overall.innerHTML = done + ' foto(s) enviada(s). <a href="' + root.dataset.albumUrl + '">Ver o álbum</a>';
    }
  }
  function startAll() {
    if (running) return; running = true; sync();
    var items = queue.filter(function (i) { return i.state === 'fila'; });
    var idx = 0;
    function next() {
      if (idx >= items.length) return Promise.resolve();
      var item = items[idx++];
      return send(item).catch(function () {}).then(next);
    }
    var workers = []; for (var k = 0; k < PARALLEL; k++) workers.push(next());
    Promise.all(workers).then(function () { running = false; sync(); });
  }
  function send(item) {
    item.state = 'enviando'; status(item, 'Preparando…', 2);
    return api('/iniciar', { album_id: albumId, name: item.file.name, size: item.file.size }, true).then(function (r) {
      if (!r.ok) throw new Error(r.mensagem || 'Não foi possível iniciar.');
      item.uploadId = r.data.upload_id; var total = r.data.chunks_total, chunk = r.data.chunk_bytes, got = r.data.received || [];
      var i = 0;
      function sendChunk() {
        if (i >= total) return Promise.resolve();
        var n = i++;
        if (got.indexOf(n) !== -1) { status(item, 'Retomando… ' + Math.round(n * 100 / total) + '%', Math.round(n * 100 / total)); return sendChunk(); }
        var blob = item.file.slice(n * chunk, Math.min(item.file.size, (n + 1) * chunk));
        return withRetry(function () {
          var fd = new FormData(); fd.append('_csrf', csrf); fd.append('upload_id', item.uploadId); fd.append('index', String(n)); fd.append('chunk', blob, 'parte');
          return api('/pedaco', fd, false).then(function (r2) { if (!r2.ok) throw new Error(r2.mensagem || 'Falha no pedaço.'); });
        }, item).then(function () { status(item, 'Enviando… ' + Math.round((n + 1) * 100 / total) + '%', Math.round((n + 1) * 95 / total)); return sendChunk(); });
      }
      return sendChunk();
    }).then(function () { return finish(item, { upload_id: item.uploadId }); })
      .catch(function (e) {
        item.state = 'erro'; status(item, e.message || 'Erro no envio.', 100, 'bg-danger');
        var a = item.el.querySelector('[data-actions]'); a.innerHTML = '';
        var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-primary'; b.innerHTML = '<i class="bi bi-arrow-repeat"></i>'; b.title = 'Tentar de novo';
        b.addEventListener('click', function () { a.innerHTML = ''; item.state = 'fila'; sync(); startAll(); }); a.appendChild(b);
        throw e;
      });
  }
  function withRetry(fn, item, attempt) {
    attempt = attempt || 0;
    return fn().catch(function (e) {
      if (attempt >= RETRIES || /sessão|permiss|não encontrado|limite/i.test(e.message)) throw e;
      status(item, 'Conexão instável, tentando de novo (' + (attempt + 1) + ')…');
      return sleep(1000 * Math.pow(2, attempt)).then(function () { return withRetry(fn, item, attempt + 1); });
    });
  }
  function finish(item, body) {
    status(item, 'Processando no servidor…', 97);
    return api('/concluir', body, true).then(function (r) {
      if (!r.ok) throw new Error(r.mensagem || 'Não foi possível concluir.');
      if (r.data && r.data.duplicate) {
        var d = r.data.duplicate;
        return window.appDialog.confirm('A foto "' + item.file.name + '" já existe nesta pasta como "' + d.name + '". Gravar uma cópia mesmo assim?', { title: 'Foto repetida', okLabel: 'Gravar cópia' }).then(function (keep) {
          if (keep) { body.keep_duplicate = true; return finish(item, body); }
          return api('/cancelar', { upload_id: item.uploadId }, true).then(function () { item.state = 'ok'; status(item, 'Ignorada: já estava na pasta.', 100, 'bg-secondary'); });
        });
      }
      item.state = 'ok'; status(item, 'Concluído', 100, 'bg-success');
      if (r.data.photo && r.data.photo.thumb) { var t = item.el.querySelector('.upload-thumb'); t.innerHTML = ''; var img = document.createElement('img'); img.src = r.data.photo.thumb; t.appendChild(img); }
      var a = item.el.querySelector('[data-actions]'); a.innerHTML = '<a class="btn btn-sm btn-outline-secondary" href="' + r.data.photo.url + '" title="Ver"><i class="bi bi-box-arrow-up-right"></i></a>';
    });
  }

  dz.addEventListener('click', function () { input.click(); });
  dz.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('dragover'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('dragover'); }); });
  dz.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files); });
  input.addEventListener('change', function () { add(input.files); input.value = ''; });
  document.getElementById('pickCamera').addEventListener('click', function () { cam.click(); });
  cam.addEventListener('change', function () { add(cam.files); cam.value = ''; });
  startBtn.addEventListener('click', startAll);
  window.addEventListener('beforeunload', function (e) { if (running) { e.preventDefault(); e.returnValue = ''; } });
})();
