// assets/js/editor.js — melhoria de fotos no navegador (canvas, sem bibliotecas): ajustes, estilos, auto-níveis, nitidez.
// A pré-visualização trabalha numa cópia reduzida; ao baixar/salvar, os mesmos ajustes são aplicados na resolução completa.
(function () {
  'use strict';
  var root = document.getElementById('editor');
  if (!root) return;
  var canvas = document.getElementById('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
  var loading = document.getElementById('loading'), dims = document.getElementById('dims');
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var full = null, preview = null, previewScale = 1, previewData = null, renderPending = false, showingOriginal = false;
  var DEFAULTS = { brightness: 0, contrast: 0, exposure: 0, highlights: 0, shadows: 0, saturation: 0, vibrance: 0, temperature: 0, tint: 0, clarity: 0, sharpen: 0, denoise: 0, vignette: 0, grain: 0, rotate: 0 };
  var adj = Object.assign({}, DEFAULTS), autoLevels = null;
  var PRESETS = {
    natural:   { brightness: 2, contrast: 6, saturation: 4, vibrance: 10, sharpen: 15 },
    vivido:    { contrast: 14, saturation: 22, vibrance: 25, clarity: 15, sharpen: 20 },
    culto:     { brightness: 3, contrast: 8, temperature: 22, vibrance: 15, shadows: 12, vignette: 18, sharpen: 12 },
    frio:      { contrast: 8, temperature: -22, tint: -5, saturation: -5, clarity: 10 },
    dramatico: { contrast: 28, shadows: -10, highlights: -15, clarity: 35, saturation: -8, vignette: 35, sharpen: 20 },
    suave:     { brightness: 6, contrast: -6, highlights: -10, shadows: 15, saturation: -6, denoise: 30, temperature: 8 },
    pb:        { saturation: -100, contrast: 16, clarity: 20, sharpen: 15, grain: 10 },
    sepia:     { saturation: -70, temperature: 45, tint: 10, contrast: 6, vignette: 25 },
    cinema:    { contrast: 12, highlights: -12, shadows: 10, temperature: 10, tint: -8, saturation: -10, vignette: 30, grain: 12 }
  };

  // ---------- carregamento ----------
  var im = new Image();
  im.onload = function () {
    full = document.createElement('canvas'); full.width = im.naturalWidth; full.height = im.naturalHeight;
    full.getContext('2d').drawImage(im, 0, 0);
    var maxSide = 1400; previewScale = Math.min(1, maxSide / Math.max(full.width, full.height));
    preview = document.createElement('canvas'); preview.width = Math.max(1, Math.round(full.width * previewScale)); preview.height = Math.max(1, Math.round(full.height * previewScale));
    preview.getContext('2d').drawImage(full, 0, 0, preview.width, preview.height);
    previewData = preview.getContext('2d').getImageData(0, 0, preview.width, preview.height);
    dims.textContent = full.width + ' × ' + full.height + ' px';
    loading.classList.add('d-none'); render();
  };
  im.onerror = function () { loading.innerHTML = '<div class="text-danger small">Não foi possível abrir esta foto no navegador (formato não suportado, ex.: HEIC). Converta para JPG antes.</div>'; };
  im.src = root.dataset.src;

  // ---------- processamento ----------
  function clamp(v) { return v < 0 ? 0 : v > 255 ? 255 : v; }
  function buildLut(a, levels) {
    // Curva por canal (brilho, contraste, exposição, realces, sombras + níveis automáticos) em tabela de 256 entradas
    var lut = new Uint8ClampedArray(256), lo = levels ? levels.lo : 0, hi = levels ? levels.hi : 255;
    var c = (a.contrast + 100) / 100, cFactor = c * c; var exp = Math.pow(2, a.exposure / 100);
    for (var i = 0; i < 256; i++) {
      var v = (i - lo) / Math.max(1, hi - lo); v = v < 0 ? 0 : v > 1 ? 1 : v;   // níveis
      v = v * exp;                                                                 // exposição
      v = (v - .5) * cFactor + .5;                                                 // contraste
      v = v + a.brightness / 255;                                                  // brilho
      if (a.shadows) { var sw = Math.max(0, 1 - v * 2); v += a.shadows / 100 * .35 * sw * sw; }       // sombras
      if (a.highlights) { var hw = Math.max(0, v * 2 - 1); v += a.highlights / 100 * .35 * hw * hw; } // realces
      lut[i] = clamp(Math.round(v * 255));
    }
    return lut;
  }
  function apply(src, a, levels) {
    var w = src.width, h = src.height, d = new Uint8ClampedArray(src.data), n = d.length;
    var lut = buildLut(a, levels);
    var sat = 1 + a.saturation / 100, vib = a.vibrance / 100, tR = a.temperature / 100 * 30, tB = -a.temperature / 100 * 30, tG = -a.tint / 100 * 20;
    for (var i = 0; i < n; i += 4) {
      var r = lut[d[i]], g = lut[d[i + 1]], b = lut[d[i + 2]];
      r += tR; g += tG; b += tB;                                     // temperatura e matiz
      var l = .299 * r + .587 * g + .114 * b;
      if (vib) { var mx = Math.max(r, g, b), mn = Math.min(r, g, b), s0 = mx ? (mx - mn) / mx : 0; var k = 1 + vib * (1 - s0) * 1.2; r = l + (r - l) * k; g = l + (g - l) * k; b = l + (b - l) * k; }
      if (sat !== 1) { r = l + (r - l) * sat; g = l + (g - l) * sat; b = l + (b - l) * sat; }
      d[i] = clamp(r); d[i + 1] = clamp(g); d[i + 2] = clamp(b);
    }
    var out = new ImageData(d, w, h);
    if (a.denoise) out = blurMix(out, a.denoise / 100 * .6, 1);
    if (a.clarity) out = unsharp(out, a.clarity / 100 * .5, 3);
    if (a.sharpen) out = unsharp(out, a.sharpen / 100 * 1.2, 1);
    if (a.vignette) vignette(out, a.vignette / 100);
    if (a.grain) grain(out, a.grain / 100 * 22);
    return out;
  }
  // Desfoque em caixa separável (rápido) usado por suavizar/nitidez/clareza
  function boxBlur(src, radius) {
    var w = src.width, h = src.height, s = src.data, tmp = new Float32Array(s.length), out = new Uint8ClampedArray(s.length), r = radius, x, y, c, i, sum;
    for (y = 0; y < h; y++) for (c = 0; c < 3; c++) { sum = 0; for (x = -r; x <= r; x++) sum += s[(y * w + Math.min(w - 1, Math.max(0, x))) * 4 + c];
      for (x = 0; x < w; x++) { tmp[(y * w + x) * 4 + c] = sum / (2 * r + 1); var xo = x - r, xn = x + r + 1; sum += s[(y * w + Math.min(w - 1, xn)) * 4 + c] - s[(y * w + Math.max(0, xo)) * 4 + c]; } }
    for (x = 0; x < w; x++) for (c = 0; c < 3; c++) { sum = 0; for (y = -r; y <= r; y++) sum += tmp[(Math.min(h - 1, Math.max(0, y)) * w + x) * 4 + c];
      for (y = 0; y < h; y++) { i = (y * w + x) * 4 + c; out[i] = sum / (2 * r + 1); var yo = y - r, yn = y + r + 1; sum += tmp[(Math.min(h - 1, yn) * w + x) * 4 + c] - tmp[(Math.max(0, yo) * w + x) * 4 + c]; } }
    for (i = 3; i < s.length; i += 4) out[i] = 255;
    return new ImageData(out, w, h);
  }
  function unsharp(src, amount, radius) {
    var bl = boxBlur(src, radius).data, d = src.data;
    for (var i = 0; i < d.length; i += 4) for (var c = 0; c < 3; c++) d[i + c] = clamp(d[i + c] + (d[i + c] - bl[i + c]) * amount);
    return src;
  }
  function blurMix(src, amount, radius) {
    var bl = boxBlur(src, radius).data, d = src.data;
    for (var i = 0; i < d.length; i += 4) for (var c = 0; c < 3; c++) d[i + c] = d[i + c] * (1 - amount) + bl[i + c] * amount;
    return src;
  }
  function vignette(img, amount) {
    var w = img.width, h = img.height, d = img.data, cx = w / 2, cy = h / 2, maxd = Math.sqrt(cx * cx + cy * cy);
    for (var y = 0; y < h; y++) for (var x = 0; x < w; x++) { var dx = x - cx, dy = y - cy, f = Math.sqrt(dx * dx + dy * dy) / maxd; var k = 1 - amount * Math.pow(Math.max(0, f - .35) / .65, 1.6); var i = (y * w + x) * 4; d[i] *= k; d[i + 1] *= k; d[i + 2] *= k; }
  }
  function grain(img, amount) {
    var d = img.data, seed = 12345;
    for (var i = 0; i < d.length; i += 4) { seed = (seed * 1103515245 + 12345) & 0x7fffffff; var n = ((seed / 0x7fffffff) - .5) * amount; d[i] = clamp(d[i] + n); d[i + 1] = clamp(d[i + 1] + n); d[i + 2] = clamp(d[i + 2] + n); }
  }
  // Níveis automáticos: corta 0,5% nas pontas do histograma de luminância
  function computeLevels(img) {
    var d = img.data, hist = new Uint32Array(256), total = img.width * img.height;
    for (var i = 0; i < d.length; i += 4) hist[Math.round(.299 * d[i] + .587 * d[i + 1] + .114 * d[i + 2])]++;
    var cut = total * .005, acc = 0, lo = 0, hi = 255;
    for (lo = 0; lo < 255 && acc < cut; lo++) acc += hist[lo];
    acc = 0; for (hi = 255; hi > 0 && acc < cut; hi--) acc += hist[hi];
    if (hi - lo < 40) return null;
    return { lo: Math.max(0, lo - 2), hi: Math.min(255, hi + 2) };
  }
  function rotated(srcCanvas, deg) {
    if (!deg) return srcCanvas;
    var c = document.createElement('canvas'), swap = Math.abs(deg) % 180 === 90;
    c.width = swap ? srcCanvas.height : srcCanvas.width; c.height = swap ? srcCanvas.width : srcCanvas.height;
    var g = c.getContext('2d'); g.translate(c.width / 2, c.height / 2); g.rotate(deg * Math.PI / 180); g.drawImage(srcCanvas, -srcCanvas.width / 2, -srcCanvas.height / 2);
    return c;
  }

  // ---------- render ----------
  function render() {
    if (!previewData || renderPending) return;
    renderPending = true;
    requestAnimationFrame(function () {
      renderPending = false;
      var out = showingOriginal ? previewData : apply(previewData, adj, autoLevels);
      var tmp = document.createElement('canvas'); tmp.width = out.width; tmp.height = out.height; tmp.getContext('2d').putImageData(out, 0, 0);
      var fin = rotated(tmp, adj.rotate);
      canvas.width = fin.width; canvas.height = fin.height; ctx.drawImage(fin, 0, 0);
    });
  }
  function setAdj(k, v) { adj[k] = v; var inp = root.querySelector('[data-adjust="' + k + '"]'); if (inp) inp.value = v; var o = root.querySelector('[data-out="' + k + '"]'); if (o) o.textContent = (v > 0 && k !== 'rotate' ? '+' : '') + v + (k === 'rotate' ? '°' : ''); }
  root.querySelectorAll('[data-adjust]').forEach(function (inp) {
    inp.addEventListener('input', function () { setAdj(inp.dataset.adjust, parseInt(inp.value, 10)); markPreset(null); render(); });
  });
  function markPreset(name) { root.querySelectorAll('[data-preset]').forEach(function (b) { b.classList.toggle('active', b.dataset.preset === name); }); }
  root.querySelectorAll('[data-preset]').forEach(function (b) {
    b.addEventListener('click', function () {
      var keep = adj.rotate; Object.keys(DEFAULTS).forEach(function (k) { setAdj(k, DEFAULTS[k]); }); setAdj('rotate', keep);
      var p = PRESETS[b.dataset.preset] || {}; Object.keys(p).forEach(function (k) { setAdj(k, p[k]); });
      markPreset(b.dataset.preset); render();
    });
  });
  root.querySelector('[data-auto]').addEventListener('click', function () {
    autoLevels = computeLevels(previewData);
    setAdj('contrast', Math.max(adj.contrast, 6)); setAdj('vibrance', Math.max(adj.vibrance, 12)); setAdj('sharpen', Math.max(adj.sharpen, 18)); setAdj('shadows', Math.max(adj.shadows, 8));
    markPreset(null); render();
    window.appDialog.alert(autoLevels ? 'Exposição e contraste corrigidos pela análise da foto, com cor e nitidez realçadas. Ajuste os controles finos se quiser.' : 'A foto já tem bom alcance de luz; aplicamos apenas cor e nitidez.', { type: 'success', title: 'Melhoria automática' });
  });
  root.querySelector('[data-reset]').addEventListener('click', function () { autoLevels = null; Object.keys(DEFAULTS).forEach(function (k) { setAdj(k, DEFAULTS[k]); }); markPreset(null); render(); });
  var cmp = document.getElementById('compareBtn');
  ['mousedown', 'touchstart'].forEach(function (ev) { cmp.addEventListener(ev, function (e) { e.preventDefault(); showingOriginal = true; render(); }, { passive: false }); });
  ['mouseup', 'mouseleave', 'touchend', 'touchcancel'].forEach(function (ev) { cmp.addEventListener(ev, function () { showingOriginal = false; render(); }); });

  // ---------- exportação em resolução completa ----------
  function exportBlob() {
    return new Promise(function (resolve, reject) {
      try {
        var g = full.getContext('2d', { willReadFrequently: true }), data = g.getImageData(0, 0, full.width, full.height);
        var out = apply(data, adj, autoLevels);
        var tmp = document.createElement('canvas'); tmp.width = out.width; tmp.height = out.height; tmp.getContext('2d').putImageData(out, 0, 0);
        rotated(tmp, adj.rotate).toBlob(function (b) { b ? resolve(b) : reject(new Error('Falha ao gerar a imagem.')); }, 'image/jpeg', .93);
      } catch (e) { reject(e); }
    });
  }
  function busy(on, text) { loading.classList.toggle('d-none', !on); if (on) loading.querySelector('.small').textContent = text || 'Processando em alta resolução…'; }
  function noteText() { var parts = []; Object.keys(adj).forEach(function (k) { if (adj[k]) parts.push(k + (adj[k] > 0 ? '+' : '') + adj[k]); }); if (autoLevels) parts.unshift('auto'); return parts.join(' ') || 'sem ajustes'; }
  root.querySelector('[data-download]').addEventListener('click', function () {
    busy(true);
    setTimeout(function () { exportBlob().then(function (blob) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = root.dataset.name + '_melhorada.jpg'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 2000);
    }).catch(function (e) { window.appDialog.alert(e.message, { type: 'danger' }); }).then(function () { busy(false); }); }, 30);
  });
  var saveBtn = root.querySelector('[data-save]');
  if (saveBtn) saveBtn.addEventListener('click', function () {
    busy(true, 'Gerando e enviando a versão editada…');
    setTimeout(function () { exportBlob().then(function (blob) {
      var fd = new FormData(); fd.append('_csrf', csrf); fd.append('image', blob, root.dataset.name + '_editada.jpg');
      var note = document.getElementById('editNote'); fd.append('note', (note && note.value) || noteText());
      return fetch(root.dataset.saveUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf } }).then(function (r) { return r.json(); });
    }).then(function (r) {
      if (!r.ok) throw new Error(r.mensagem || 'Não foi possível salvar.');
      return window.appDialog.alert('Versão editada salva no álbum ao lado do original.', { type: 'success', title: 'Salvo' }).then(function () { window.location.href = r.data.url; });
    }).catch(function (e) { window.appDialog.alert(e.message, { type: 'danger' }); }).then(function () { busy(false); }); }, 30);
  });
})();
