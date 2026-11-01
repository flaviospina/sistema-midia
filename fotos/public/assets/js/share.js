// public/assets/js/share.js — páginas públicas de compartilhamento: botão nativo do aparelho, copiar link e galeria do álbum
(function () {
  'use strict';
  document.querySelectorAll('[data-native-share]').forEach(function (btn) {
    if (!navigator.share) return;
    btn.classList.remove('d-none');
    btn.addEventListener('click', function () { navigator.share({ title: btn.dataset.title, text: btn.dataset.title, url: btn.dataset.url }).catch(function () {}); });
  });
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-copy]');
    if (!b || !navigator.clipboard) return;
    navigator.clipboard.writeText(b.dataset.copy).then(function () { var old = b.innerHTML; b.innerHTML = '<i class="bi bi-check"></i> Copiado'; setTimeout(function () { b.innerHTML = old; }, 1500); });
  });
  var g = document.getElementById('publicGallery'), lb = document.getElementById('lightbox');
  if (!g || !lb) return;
  var cards = Array.prototype.slice.call(g.querySelectorAll('.photo-card')), cur = -1, img = document.getElementById('lbImg'), cap = document.getElementById('lbCaption'), dl = document.getElementById('lbDownload');
  function show(i) { if (i < 0 || i >= cards.length) return; cur = i; img.src = cards[i].href; cap.textContent = cards[i].dataset.caption || ''; dl.href = cards[i].href; lb.classList.remove('d-none'); document.body.style.overflow = 'hidden'; }
  function close() { lb.classList.add('d-none'); document.body.style.overflow = ''; img.src = ''; }
  cards.forEach(function (c, i) { c.addEventListener('click', function (e) { e.preventDefault(); show(i); }); });
  lb.querySelector('[data-lb-close]').addEventListener('click', close);
  lb.querySelector('[data-lb-prev]').addEventListener('click', function () { show(cur - 1); });
  lb.querySelector('[data-lb-next]').addEventListener('click', function () { show(cur + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb || e.target.classList.contains('lb-stage')) close(); });
  document.addEventListener('keydown', function (e) { if (lb.classList.contains('d-none')) return; if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') show(cur - 1); else if (e.key === 'ArrowRight') show(cur + 1); });
  var tx = null;
  lb.addEventListener('touchstart', function (e) { tx = e.changedTouches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', function (e) { if (tx === null) return; var dx = e.changedTouches[0].clientX - tx; tx = null; if (Math.abs(dx) > 60) show(dx < 0 ? cur + 1 : cur - 1); }, { passive: true });
})();
