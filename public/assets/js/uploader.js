// public/assets/js/uploader.js — upload em pedaços com retentativa e retomada (vanilla JS)
(function () {
  'use strict';

  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var CONCURRENCY = 2;
  var RETRIES = 4;

  function fmtBytes(n) {
    var u = ['B', 'KB', 'MB', 'GB'], i = 0;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return (i === 0 ? n : n.toFixed(1)).toString().replace('.', ',') + ' ' + u[i];
  }

  function api(method, url, body, isForm) {
    var opt = { method: method, headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF }, credentials: 'same-origin' };
    if (body && !isForm) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
    if (body && isForm) { opt.body = body; }
    return fetch(url, opt).then(function (r) {
      return r.json().catch(function () { return { ok: false, mensagem: 'Resposta inválida do servidor (' + r.status + ').' }; })
        .then(function (j) { j._status = r.status; return j; });
    });
  }

  function sleep(ms) { return new Promise(function (res) { setTimeout(res, ms); }); }

  // Miniatura e duração de vídeo capturadas no navegador
  function captureVideo(file) {
    return new Promise(function (resolve) {
      var video = document.createElement('video');
      var url = URL.createObjectURL(file);
      var done = false;
      var finish = function (data) { if (done) return; done = true; URL.revokeObjectURL(url); resolve(data); };
      var timer = setTimeout(function () { finish(null); }, 10000);
      video.preload = 'metadata'; video.muted = true; video.playsInline = true; video.src = url;
      video.addEventListener('loadedmetadata', function () {
        try { video.currentTime = Math.min(1, (video.duration || 2) / 2); } catch (e) { finish({ duration: video.duration, width: video.videoWidth, height: video.videoHeight, thumbnail: null }); }
      });
      video.addEventListener('seeked', function () {
        try {
          var scale = Math.min(1, 480 / (video.videoWidth || 480));
          var c = document.createElement('canvas');
          c.width = Math.round((video.videoWidth || 480) * scale); c.height = Math.round((video.videoHeight || 270) * scale);
          c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
          clearTimeout(timer);
          finish({ duration: video.duration, width: video.videoWidth, height: video.videoHeight, thumbnail: c.toDataURL('image/jpeg', 0.8) });
        } catch (e) { clearTimeout(timer); finish({ duration: video.duration, width: video.videoWidth, height: video.videoHeight, thumbnail: null }); }
      });
      video.addEventListener('error', function () { clearTimeout(timer); finish(null); });
    });
  }

  function imageDims(file) {
    return new Promise(function (resolve) {
      var img = new Image(); var url = URL.createObjectURL(file);
      img.onload = function () { URL.revokeObjectURL(url); resolve({ width: img.naturalWidth, height: img.naturalHeight }); };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(null); };
      img.src = url;
    });
  }

  function Uploader(root) {
    this.root = root;
    this.endpoint = root.dataset.endpoint;
    this.chunkSize = parseInt(root.dataset.chunk, 10) || 5 * 1024 * 1024;
    this.maxBytes = parseInt(root.dataset.max, 10) || 0;
    this.allowed = (root.dataset.allowed || '').split(',').filter(Boolean);
    this.metaForm = root.dataset.metaForm ? document.getElementById(root.dataset.metaForm) : null;
    this.items = [];
    this.active = 0;
    this.build();
  }

  Uploader.prototype.build = function () {
    var self = this;
    this.zone = this.root.querySelector('[data-dropzone]');
    this.input = this.root.querySelector('input[type="file"]');
    this.list = this.root.querySelector('[data-list]');
    this.summary = this.root.querySelector('[data-summary]');
    this.input.addEventListener('change', function () { self.addFiles(self.input.files); self.input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) { self.zone.addEventListener(ev, function (e) { e.preventDefault(); self.zone.classList.add('dragover'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { self.zone.addEventListener(ev, function (e) { e.preventDefault(); self.zone.classList.remove('dragover'); }); });
    this.zone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) self.addFiles(e.dataTransfer.files); });
    this.zone.addEventListener('click', function (e) { if (e.target.tagName !== 'LABEL' && e.target.tagName !== 'INPUT') self.input.click(); });
    window.addEventListener('beforeunload', function (e) { if (self.active > 0) { e.preventDefault(); e.returnValue = ''; } });
  };

  Uploader.prototype.meta = function () {
    var m = {};
    if (!this.metaForm) return m;
    ['title', 'description', 'tags', 'event', 'category', 'visibility', 'folder_id'].forEach(function (k) {
      var el = this.metaForm.querySelector('[name="' + k + '"]');
      if (el) m[k] = el.value;
    }, this);
    return m;
  };

  Uploader.prototype.addFiles = function (files) {
    var self = this;
    Array.prototype.forEach.call(files, function (file) {
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      var item = { file: file, state: 'fila', progress: 0, uploadId: null, received: {}, error: null, meta: self.meta() };
      item.el = self.render(item);
      self.list.appendChild(item.el);
      self.items.push(item);
      if (self.allowed.length && self.allowed.indexOf(ext) === -1) { self.fail(item, 'Tipo de arquivo não permitido (.' + ext + ').'); return; }
      if (self.maxBytes && file.size > self.maxBytes) { self.fail(item, 'Arquivo maior que o máximo permitido (' + fmtBytes(self.maxBytes) + ').'); return; }
      if (file.size === 0) { self.fail(item, 'Arquivo vazio.'); return; }
    });
    this.updateSummary();
    this.pump();
  };

  Uploader.prototype.render = function (item) {
    var el = document.createElement('div');
    el.className = 'upload-item';
    el.innerHTML =
      '<div class="d-flex align-items-center gap-2">' +
      '  <div class="upload-thumb"><i class="bi bi-file-earmark"></i></div>' +
      '  <div class="flex-grow-1 min-w-0">' +
      '    <div class="text-truncate fw-semibold" data-name></div>' +
      '    <div class="small text-muted" data-status></div>' +
      '    <div class="progress mt-1" role="progressbar"><div class="progress-bar" data-bar></div></div>' +
      '  </div>' +
      '  <div class="text-nowrap" data-actions></div>' +
      '</div>';
    el.querySelector('[data-name]').textContent = item.file.name;
    el.querySelector('[data-status]').textContent = fmtBytes(item.file.size) + ' · na fila';
    if (item.file.type && item.file.type.indexOf('image/') === 0 && item.file.size < 15 * 1024 * 1024) {
      var img = document.createElement('img'); img.src = URL.createObjectURL(item.file); img.onload = function () { URL.revokeObjectURL(img.src); };
      el.querySelector('.upload-thumb').innerHTML = ''; el.querySelector('.upload-thumb').appendChild(img);
    }
    return el;
  };

  Uploader.prototype.setStatus = function (item, text, pct, cls) {
    item.el.querySelector('[data-status]').textContent = text;
    var bar = item.el.querySelector('[data-bar]');
    if (pct !== undefined) bar.style.width = pct + '%';
    bar.className = 'progress-bar' + (cls ? ' ' + cls : '');
    this.updateSummary();
  };

  Uploader.prototype.fail = function (item, msg, retryable) {
    var self = this;
    item.state = 'erro'; item.error = msg;
    this.setStatus(item, 'Erro: ' + msg, undefined, 'bg-danger');
    var actions = item.el.querySelector('[data-actions]');
    actions.innerHTML = '';
    if (retryable) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-primary'; b.innerHTML = '<i class="bi bi-arrow-repeat"></i> Tentar de novo';
      b.addEventListener('click', function () { actions.innerHTML = ''; item.state = 'fila'; self.pump(); });
      actions.appendChild(b);
    }
  };

  Uploader.prototype.updateSummary = function () {
    if (!this.summary) return;
    var done = 0, err = 0, total = this.items.length;
    this.items.forEach(function (i) { if (i.state === 'ok') done++; if (i.state === 'erro') err++; });
    this.summary.textContent = total ? (done + ' de ' + total + ' concluído(s)' + (err ? ', ' + err + ' com erro' : '')) : '';
    this.root.dispatchEvent(new CustomEvent('uploader:change', { detail: { total: total, done: done, err: err } }));
  };

  Uploader.prototype.pump = function () {
    var self = this;
    this.items.forEach(function (item) {
      if (self.active >= CONCURRENCY) return;
      if (item.state !== 'fila') return;
      item.state = 'enviando'; self.active++;
      self.process(item).catch(function (e) { self.fail(item, (e && e.message) || 'Falha inesperada.', true); })
        .then(function () { self.active--; self.pump(); });
    });
  };

  Uploader.prototype.process = function (item) {
    var self = this;
    var file = item.file;
    var key = 'midia_up:' + file.name + ':' + file.size + ':' + file.lastModified;
    var media = null;

    return Promise.resolve().then(function () {
      // Retomada: sessão anterior gravada no navegador?
      var stored = null;
      try { stored = localStorage.getItem(key); } catch (e) { /* ignorar */ }
      if (!stored) return null;
      return api('GET', self.endpoint + '/' + stored + '/status').then(function (r) {
        if (r.ok && r.data.status === 'aberto') { item.uploadId = r.data.upload_id; item.chunkSize = r.data.chunk_size; item.total = r.data.chunks_total; r.data.received.forEach(function (i) { item.received[i] = true; }); return true; }
        if (r.ok && r.data.status === 'concluido') { return 'done'; }
        try { localStorage.removeItem(key); } catch (e) { /* ignorar */ }
        return null;
      }).catch(function () { return null; });
    }).then(function (resumed) {
      if (resumed === 'done') { self.success(item, { id: null, status_label: 'já enviado anteriormente' }); return 'skip'; }
      self.setStatus(item, 'Preparando…', 2);
      var prep = file.type.indexOf('video/') === 0 ? captureVideo(file) : (file.type.indexOf('image/') === 0 ? imageDims(file) : Promise.resolve(null));
      return prep.then(function (m) { media = m; return resumed; });
    }).then(function (resumed) {
      if (resumed === 'skip') return 'skip';
      if (resumed) return true;
      var body = Object.assign({ name: file.name, size: file.size }, item.meta);
      return api('POST', self.endpoint + '/iniciar', body).then(function (r) {
        if (!r.ok) throw new Error(r.mensagem || 'Não foi possível iniciar o envio.');
        item.uploadId = r.data.upload_id; item.chunkSize = r.data.chunk_size; item.total = r.data.chunks_total;
        try { localStorage.setItem(key, item.uploadId); } catch (e) { /* ignorar */ }
        return true;
      });
    }).then(function (go) {
      if (go === 'skip') return 'skip';
      return self.sendChunks(item);
    }).then(function (go) {
      if (go === 'skip') return;
      self.setStatus(item, 'Finalizando…', 99);
      var body = Object.assign({ upload_id: item.uploadId }, item.meta, media ? { thumbnail: media.thumbnail || '', duration: media.duration || null, width: media.width || null, height: media.height || null } : {});
      return self.finish(item, body, key);
    });
  };

  Uploader.prototype.sendChunks = function (item) {
    var self = this;
    var i = 0;
    function next() {
      if (i >= item.total) return Promise.resolve(true);
      var idx = i++;
      if (item.received[idx]) { return next(); }
      var start = idx * item.chunkSize, end = Math.min(item.file.size, start + item.chunkSize);
      var blob = item.file.slice(start, end);
      return sendWithRetry(idx, blob, 0).then(function () {
        item.received[idx] = true;
        var pct = Math.round(((idx + 1) / item.total) * 96) + 2;
        self.setStatus(item, 'Enviando… ' + fmtBytes(end) + ' de ' + fmtBytes(item.file.size), pct);
        return next();
      });
    }
    function sendWithRetry(idx, blob, attempt) {
      var fd = new FormData();
      fd.append('_csrf', CSRF); fd.append('upload_id', item.uploadId); fd.append('index', idx); fd.append('chunk', blob, 'pedaco');
      return api('POST', self.endpoint + '/pedaco', fd, true).then(function (r) {
        if (r.ok) return true;
        if (r._status === 401 || r._status === 403 || r._status === 404 || r._status === 419) throw new Error(r.mensagem || 'Sessão inválida.');
        throw new Error(r.mensagem || 'Falha ao enviar o pedaço.');
      }).catch(function (e) {
        if (attempt >= RETRIES || /Sessão|permiss|não pode|inválid/i.test(e.message)) throw e;
        self.setStatus(item, 'Conexão instável, tentando de novo (' + (attempt + 1) + ')…');
        return sleep(1000 * Math.pow(2, attempt)).then(function () { return sendWithRetry(idx, blob, attempt + 1); });
      });
    }
    return next();
  };

  Uploader.prototype.finish = function (item, body, key) {
    var self = this;
    return api('POST', this.endpoint + '/concluir', body).then(function (r) {
      if (!r.ok) throw new Error(r.mensagem || 'Não foi possível concluir o envio.');
      if (r.data && r.data.duplicate) {
        var d = r.data.duplicate;
        var keep = window.confirm('Este arquivo já existe no repositório:\n\n' + d.name + '\nPasta: ' + d.folder + ' · ' + d.status + ' · ' + d.created_at + '\n\nDeseja gravar uma cópia mesmo assim?');
        if (keep) { body.keep_duplicate = true; return self.finish(item, body, key); }
        return api('POST', self.endpoint + '/cancelar', { upload_id: item.uploadId }).then(function () {
          try { localStorage.removeItem(key); } catch (e) { /* ignorar */ }
          item.state = 'ok';
          self.setStatus(item, 'Ignorado: já existia' + (d.can_view ? '' : '') + '.', 100, 'bg-secondary');
          if (d.can_view && d.id) { var a = document.createElement('a'); a.href = self.root.dataset.fileBase + d.id; a.className = 'btn btn-sm btn-outline-secondary'; a.textContent = 'Ver existente'; item.el.querySelector('[data-actions]').appendChild(a); }
        });
      }
      try { localStorage.removeItem(key); } catch (e) { /* ignorar */ }
      self.success(item, r.data.file);
    });
  };

  Uploader.prototype.success = function (item, file) {
    item.state = 'ok';
    this.setStatus(item, 'Concluído · ' + (file.status_label || ''), 100, 'bg-success');
    var actions = item.el.querySelector('[data-actions]');
    actions.innerHTML = '';
    if (file.url) { var a = document.createElement('a'); a.href = file.url; a.className = 'btn btn-sm btn-outline-primary'; a.innerHTML = '<i class="bi bi-box-arrow-up-right"></i>'; a.title = 'Abrir'; actions.appendChild(a); }
    if (file.thumb) { var t = item.el.querySelector('.upload-thumb'); t.innerHTML = ''; var img = document.createElement('img'); img.src = file.thumb; t.appendChild(img); }
  };

  document.querySelectorAll('[data-uploader]').forEach(function (root) { root._uploader = new Uploader(root); });
})();
