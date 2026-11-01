// public/assets/js/gallery.js — galeria: seleção para ZIP, visualizador (lightbox) com teclado/toque e compartilhamento nativo do aparelho
(function () {
  'use strict';
  var gallery = document.getElementById('gallery');
  if (!gallery) return;
  var cards = Array.prototype.slice.call(document.querySelectorAll('.photo-card[data-id]'));
  var canDelete = gallery.dataset.canDelete === '1', canShare = gallery.dataset.canShare === '1', canDownload = gallery.dataset.canDownload === '1';

  // Seleção
  var selectAll = document.getElementById('selectAll'), selCount = document.getElementById('selCount'), zipBtn = document.getElementById('zipBtn');
  function syncSel() {
    var boxes = gallery.querySelectorAll('.photo-select'), n = 0;
    boxes.forEach(function (b) { b.closest('.photo-card').classList.toggle('selecionada', b.checked); if (b.checked) n++; });
    if (selCount) selCount.textContent = n ? n + ' selecionada(s)' : '';
    if (zipBtn) zipBtn.innerHTML = '<i class="bi bi-file-earmark-zip"></i> ' + (n ? 'Baixar ' + n + ' (ZIP)' : 'Baixar tudo (ZIP)');
  }
  gallery.addEventListener('change', function (e) { if (e.target.classList.contains('photo-select')) syncSel(); });
  if (selectAll) selectAll.addEventListener('change', function () { gallery.querySelectorAll('.photo-select').forEach(function (b) { b.checked = selectAll.checked; }); syncSel(); });

  // Lightbox
  var lb = document.getElementById('lightbox'), img = document.getElementById('lbImg'), cur = -1;
  var elName = document.getElementById('lbName'), elMeta = document.getElementById('lbMeta'), elCap = document.getElementById('lbCaption');
  var aEdit = document.getElementById('lbEdit'), aDl = document.getElementById('lbDownload'), aShare = document.getElementById('lbShare'), bNative = document.getElementById('lbShareNative');
  var fDel = document.getElementById('lbDeleteForm'), fCover = document.getElementById('lbCoverForm');
  function show(i) {
    if (i < 0 || i >= cards.length) return;
    cur = i; var c = cards[i], d = c.dataset;
    img.src = d.src; img.alt = d.caption || d.name;
    elName.textContent = d.name; elMeta.textContent = d.meta || ''; elCap.textContent = d.caption || '';
    aEdit.href = d.edit;
    aDl.classList.toggle('d-none', !canDownload); aDl.href = d.download;
    aShare.classList.toggle('d-none', !canShare); aShare.href = d.share;
    bNative.classList.toggle('d-none', !(navigator.share && canDownload));
    if (fDel) { var may = canDelete || d.mine === '1'; fDel.classList.toggle('d-none', !may); fDel.action = d.delete; }
    if (fCover) { fCover.action = d.delete.replace(/\/excluir$/, '/capa'); }
    lb.classList.remove('d-none'); document.body.style.overflow = 'hidden';
    history.replaceState(null, '', location.pathname + '?foto=' + d.id);
    // Pré-carrega a próxima
    if (cards[i + 1]) { var pre = new Image(); pre.src = cards[i + 1].dataset.src; }
  }
  function close() { lb.classList.add('d-none'); document.body.style.overflow = ''; img.src = ''; history.replaceState(null, '', location.pathname); }
  cards.forEach(function (c, i) {
    c.addEventListener('click', function (e) {
      if (e.target.classList.contains('photo-select')) return;
      e.preventDefault(); show(i);
    });
  });
  lb.querySelector('[data-lb-close]').addEventListener('click', close);
  lb.querySelector('[data-lb-prev]').addEventListener('click', function () { show(cur - 1); });
  lb.querySelector('[data-lb-next]').addEventListener('click', function () { show(cur + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb || e.target.classList.contains('lb-stage')) close(); });
  document.addEventListener('keydown', function (e) {
    if (lb.classList.contains('d-none')) return;
    if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') show(cur - 1); else if (e.key === 'ArrowRight') show(cur + 1);
  });
  var tx = null;
  lb.addEventListener('touchstart', function (e) { tx = e.changedTouches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', function (e) { if (tx === null) return; var dx = e.changedTouches[0].clientX - tx; tx = null; if (Math.abs(dx) > 60) show(dx < 0 ? cur + 1 : cur - 1); }, { passive: true });

  // Compartilhamento nativo (Instagram, WhatsApp, Facebook… do próprio celular)
  bNative.addEventListener('click', function () {
    var c = cards[cur]; if (!c) return;
    bNative.disabled = true;
    fetch(c.dataset.src, { credentials: 'same-origin' }).then(function (r) { return r.blob(); }).then(function (blob) {
      var file = new File([blob], (c.dataset.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
      var data = { files: [file], title: 'Fotos ADMoema', text: c.dataset.caption || '' };
      if (navigator.canShare && !navigator.canShare(data)) throw new Error('Este aparelho não compartilha imagens por aqui. Baixe a foto e publique pelo aplicativo.');
      return navigator.share(data);
    }).catch(function (e) { if (e && e.name !== 'AbortError') window.appDialog.alert(e.message || 'Não foi possível compartilhar.', { type: 'warning' }); })
      .then(function () { bNative.disabled = false; });
  });

  // Abre direto uma foto indicada na URL (?foto=ID)
  var open = parseInt(gallery.dataset.open || '0', 10);
  if (open) { var idx = cards.findIndex(function (c) { return parseInt(c.dataset.id, 10) === open; }); if (idx >= 0) show(idx); }
})();
