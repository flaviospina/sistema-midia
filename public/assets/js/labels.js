// public/assets/js/labels.js — etiquetas de patrimônio: desenha o QR Code (biblioteca qrcode-generator via CDN) e imprime
(function () {
  'use strict';
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    if (typeof qrcode !== 'function') { el.textContent = 'QR indisponível (sem acesso ao CDN)'; return; }
    try {
      var qr = qrcode(0, 'M');
      qr.addData(el.dataset.qr);
      qr.make();
      var size = parseInt(el.dataset.size || '4', 10);
      el.innerHTML = qr.createSvgTag({ cellSize: size, margin: 0, scalable: true });
    } catch (e) {
      el.textContent = 'Falha ao gerar o QR Code.';
    }
  });
  var btn = document.querySelector('[data-print]');
  if (btn) { btn.addEventListener('click', function () { window.print(); }); }
})();
