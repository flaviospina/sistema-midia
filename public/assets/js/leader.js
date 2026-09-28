// public/assets/js/leader.js — gráficos do painel do líder (Chart.js via CDN; dados vêm do bloco JSON da página)
(function () {
  'use strict';
  var el = document.getElementById('chartData');
  if (!el || typeof Chart === 'undefined') return;
  var data;
  try { data = JSON.parse(el.textContent); } catch (e) { return; }
  var colors = { azul: '#1f3a5f', verde: '#198754', amarelo: '#ffc107', vermelho: '#dc3545', cinza: '#adb5bd', ciano: '#0dcaf0' };
  var palette = ['#1f3a5f', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#0dcaf0', '#fd7e14', '#20c997'];
  var base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } };

  var build = {
    frequencia: function (d) {
      return { type: 'bar', data: { labels: d.labels, datasets: [
        { label: 'Confirmados', data: d.confirmados, backgroundColor: colors.verde, stack: 's' },
        { label: 'Pendentes', data: d.pendentes, backgroundColor: colors.amarelo, stack: 's' },
        { label: 'Recusados', data: d.recusados, backgroundColor: colors.vermelho, stack: 's' }
      ] }, options: Object.assign({}, base, { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }) };
    },
    audiencia: function (d) {
      return { type: 'line', data: { labels: d.labels, datasets: [
        { label: 'Pico ao vivo', data: d.pico, borderColor: colors.azul, backgroundColor: colors.azul, tension: .3, spanGaps: true },
        { label: 'Presencial (est.)', data: d.presencial, borderColor: colors.verde, backgroundColor: colors.verde, tension: .3, spanGaps: true }
      ] }, options: Object.assign({}, base, { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }) };
    },
    ministerios: function (d) {
      return { type: 'doughnut', data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: palette }] }, options: base };
    },
    uploads: function (d) {
      return { type: 'bar', data: { labels: d.labels, datasets: [
        { label: 'Arquivos', data: d.qty, backgroundColor: colors.azul, yAxisID: 'y' },
        { label: 'MB', data: d.mb, type: 'line', borderColor: colors.amarelo, backgroundColor: colors.amarelo, yAxisID: 'y2', tension: .3 }
      ] }, options: Object.assign({}, base, { scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } } }) };
    },
    ocorrencias: function (d) {
      return { type: 'pie', data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: palette }] }, options: base };
    }
  };

  document.querySelectorAll('canvas[data-chart]').forEach(function (c) {
    var key = c.dataset.chart;
    if (!build[key] || !data[key]) return;
    new Chart(c, build[key](data[key]));
  });
})();
