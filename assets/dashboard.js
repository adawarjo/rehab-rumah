// Grafik dashboard (Chart.js). Data dikirim dari index.php lewat window.DASH
(function () {
  const D = window.DASH;
  if (!D || !window.Chart) return;

  const INK = '#334155', MUTED = '#64748b', GRID = '#eef2f7', SURFACE = '#ffffff';
  const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
  // Singkatan sumbu: 1.250.000 -> 1,3 jt
  const singkat = v => {
    const a = Math.abs(v);
    if (a >= 1e9) return (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
    if (a >= 1e6) return (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    if (a >= 1e3) return (v / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
    return v;
  };

  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 11;
  Chart.defaults.color = MUTED;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.animation.duration = 400;

  const tooltip = {
    backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0', padding: 10, cornerRadius: 8,
    boxWidth: 10, boxHeight: 10, boxPadding: 4, usePointStyle: true,
  };
  const xAxis = { grid: { display: false }, border: { color: GRID }, ticks: { maxRotation: 0, autoSkipPadding: 12 } };
  const yAxis = { beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { callback: singkat, maxTicksLimit: 5 } };

  // 1) Pengeluaran per waktu — batang bertumpuk per kategori
  const elP = document.getElementById('ch-pengeluaran');
  if (elP) new Chart(elP, {
    type: 'bar',
    data: {
      labels: D.labels,
      datasets: D.series.map(s => ({
        label: s.name, data: s.data, backgroundColor: s.color,
        borderColor: SURFACE, borderWidth: { top: 2 }, borderSkipped: 'bottom',
        borderRadius: 4, maxBarThickness: 28,
      })),
    },
    options: {
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false }, // legenda HTML di atas grafik
        tooltip: {
          ...tooltip,
          filter: i => i.raw > 0,
          callbacks: {
            label: c => ' ' + c.dataset.label + ': ' + rp(c.raw),
            footer: items => 'Total: ' + rp(items.reduce((a, i) => a + i.raw, 0)),
          },
        },
      },
      scales: { x: { ...xAxis, stacked: true }, y: { ...yAxis, stacked: true } },
    },
  });

  // 2) Biaya kumulatif vs anggaran — satu sumbu (Rupiah)
  const elK = document.getElementById('ch-kumulatif');
  if (elK) {
    const ds = [{
      label: 'Biaya berjalan', data: D.kumulatif, borderColor: '#2a78d6', backgroundColor: 'rgba(42,120,214,.08)',
      fill: true, borderWidth: 2, tension: .25, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderWidth: 2,
      pointHoverBorderColor: SURFACE,
    }];
    if (D.anggaran > 0) ds.push({
      label: 'Anggaran', data: D.labels.map(() => D.anggaran), borderColor: '#64748b', borderWidth: 1.5,
      borderDash: [6, 4], pointRadius: 0, pointHoverRadius: 0, fill: false,
    });
    new Chart(elK, {
      type: 'line',
      data: { labels: D.labels, datasets: ds },
      options: {
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: ds.length > 1, position: 'top', align: 'start',
            labels: { color: INK, boxWidth: 14, boxHeight: 2, padding: 12 },
          },
          tooltip: {
            ...tooltip, usePointStyle: false,
            callbacks: {
              label: c => ' ' + c.dataset.label + ': ' + rp(c.raw),
              afterBody: items => {
                if (!(D.anggaran > 0)) return '';
                const b = items.find(i => i.datasetIndex === 0);
                return b ? 'Sisa: ' + rp(D.anggaran - b.raw) : '';
              },
            },
          },
        },
        scales: { x: xAxis, y: yAxis },
      },
    });
  }

  // 3) Kehadiran — hari-orang per hari
  const elA = document.getElementById('ch-absen');
  if (elA) new Chart(elA, {
    type: 'bar',
    data: {
      labels: D.absen.labels,
      datasets: [{
        label: 'Hari-orang', data: D.absen.hari, backgroundColor: '#1baf7a',
        borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 22,
      }],
    },
    options: {
      plugins: {
        legend: { display: false },
        tooltip: {
          ...tooltip, usePointStyle: false, displayColors: false,
          callbacks: {
            label: c => D.absen.orang[c.dataIndex] + ' orang masuk',
            afterLabel: c => c.raw.toLocaleString('id-ID') + ' hari-orang',
          },
        },
      },
      scales: { x: xAxis, y: { ...yAxis, ticks: { precision: 0, maxTicksLimit: 4 } } },
    },
  });
})();
