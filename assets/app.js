// Format input uang otomatis: 1500000 -> 1.500.000
function fmtUang(v) {
  v = String(v).replace(/[^\d]/g, '');
  return v ? Number(v).toLocaleString('id-ID') : '';
}
function parseUang(v) { return Number(String(v).replace(/[^\d]/g, '')) || 0; }
function parseDes(v) { return Number(String(v).replace(',', '.')) || 0; }

document.addEventListener('input', function (e) {
  if (e.target.classList.contains('uang')) {
    const el = e.target, pos = el.value.length - el.selectionStart;
    el.value = fmtUang(el.value);
    const p = Math.max(0, el.value.length - pos);
    el.setSelectionRange(p, p);
  }
});
document.querySelectorAll('input.uang').forEach(el => el.value = fmtUang(el.value));

// ================= SweetAlert2 =================
const BRAND = '#b45309';
const Toast = Swal.mixin({
  toast: true, position: 'top', showConfirmButton: false, timer: 2500, timerProgressBar: true,
  didOpen: t => { t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer); }
});

// Pesan dari server (flash): sukses = toast singkat, gagal/peringatan = dialog
document.addEventListener('DOMContentLoaded', async function () {
  for (const f of (window.FLASH || [])) {
    const icon = { success: 'success', danger: 'error', warning: 'warning', info: 'info' }[f.type] || 'info';
    if (icon === 'success') await Toast.fire({ icon, title: f.msg });
    else await Swal.fire({ icon, title: icon === 'error' ? 'Gagal' : 'Perhatian', text: f.msg, confirmButtonColor: BRAND });
  }
});

/** Dialog konfirmasi. el boleh punya: data-confirm (pesan), data-confirm-type="danger",
 *  data-confirm-ok (teks tombol), data-confirm-detail (selector elemen yang isinya ditampilkan tebal) */
function konfirmasi(el) {
  const danger = el.dataset.confirmType === 'danger';
  const det = el.dataset.confirmDetail && document.querySelector(el.dataset.confirmDetail);
  const div = document.createElement('div');
  div.textContent = el.dataset.confirm;
  if (det) { const b = document.createElement('div'); b.className = 'fs-3 fw-bold mt-2 text-success'; b.textContent = det.textContent; div.appendChild(b); }
  return Swal.fire({
    html: div, icon: danger ? 'warning' : 'question', showCancelButton: true, reverseButtons: true, focusCancel: danger,
    confirmButtonText: el.dataset.confirmOk || 'Ya', cancelButtonText: 'Batal',
    confirmButtonColor: danger ? '#dc2626' : BRAND, cancelButtonColor: '#78716c'
  }).then(r => r.isConfirmed);
}

// Form dengan data-confirm
document.addEventListener('submit', function (e) {
  const f = e.target;
  if (!f.dataset.confirm || f.dataset.confirmed) return;
  e.preventDefault();
  konfirmasi(f).then(ok => {
    if (!ok) return;
    f.dataset.confirmed = '1';
    Swal.fire({ title: 'Memproses…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    f.submit();
  });
});

// Link dengan data-confirm (mis. keluar)
document.addEventListener('click', function (e) {
  const a = e.target.closest('a[data-confirm]');
  if (!a) return;
  e.preventDefault();
  konfirmasi(a).then(ok => { if (ok) location.href = a.href; });
});

// Hitung total bahan = qty x harga
(function () {
  const qty = document.getElementById('qty'), harga = document.getElementById('harga_satuan'), total = document.getElementById('total');
  if (!qty || !harga || !total) return;
  const hitung = () => {
    const t = Math.round(parseDes(qty.value) * parseUang(harga.value));
    if (t > 0) total.value = fmtUang(t);
  };
  qty.addEventListener('input', hitung);
  harga.addEventListener('input', hitung);
})();

// Hitung gaji bersih secara langsung
(function () {
  const f = document.getElementById('form-gaji');
  if (!f) return;
  const kotor = Number(f.dataset.kotor) || 0;
  const bonus = f.querySelector('[name=bonus]'), pot = f.querySelector('[name=potongan]'), out = document.getElementById('bersih');
  const hitung = () => { out.textContent = 'Rp ' + (kotor + parseUang(bonus.value) - parseUang(pot.value)).toLocaleString('id-ID'); };
  bonus.addEventListener('input', hitung); pot.addEventListener('input', hitung); hitung();
})();

// Status bahan: bon wajib pilih toko
(function () {
  const st = document.querySelectorAll('input[name=status]'), toko = document.getElementById('toko_id');
  if (!st.length || !toko) return;
  const cek = () => { toko.required = document.querySelector('input[name=status]:checked')?.value === 'bon'; };
  st.forEach(r => r.addEventListener('change', cek)); cek();
})();

// ================= PWA =================
// Daftarkan service worker (butuh HTTPS atau localhost)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));
}

// Status online / offline
(function () {
  const bar = document.getElementById('offline-bar');
  const update = () => { if (bar) bar.classList.toggle('d-none', navigator.onLine); };
  window.addEventListener('online', () => { update(); Toast.fire({ icon: 'success', title: 'Kembali online' }); });
  window.addEventListener('offline', update);
  update();
})();

// Cegah kirim form saat offline supaya isian tidak hilang (halaman tetap terbuka)
document.addEventListener('submit', function (e) {
  if ((e.target.method || '').toLowerCase() === 'post' && !navigator.onLine) {
    e.preventDefault(); e.stopImmediatePropagation();
    Swal.fire({ icon: 'warning', title: 'Tidak ada sinyal', text: 'Data belum tersimpan. Isian tetap di layar — tekan Simpan lagi setelah sinyal kembali.', confirmButtonColor: BRAND });
  }
}, true);

// Tombol "Pasang aplikasi"
(function () {
  const btn = document.getElementById('btn-install');
  if (!btn) return;
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  if (standalone) return;
  let promptEvt = null;
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); promptEvt = e; btn.classList.remove('d-none'); });
  window.addEventListener('appinstalled', () => { btn.classList.add('d-none'); Toast.fire({ icon: 'success', title: 'Aplikasi terpasang' }); });
  const iOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  if (iOS) btn.classList.remove('d-none');
  btn.addEventListener('click', async e => {
    e.preventDefault();
    if (promptEvt) { promptEvt.prompt(); await promptEvt.userChoice; promptEvt = null; btn.classList.add('d-none'); }
    else if (iOS) Swal.fire({ title: 'Pasang di iPhone', confirmButtonColor: BRAND,
      html: 'Buka di <b>Safari</b>, tekan tombol <b>Bagikan</b> <i class="bi bi-box-arrow-up"></i>, lalu pilih <b>Tambah ke Layar Utama</b>.' });
  });
})();

// Captcha login: tombol ganti kode. Saat halaman dibuka lagi lewat tombol Kembali,
// kodenya sudah tidak berlaku, jadi gambar juga diganti.
(function () {
  const img = document.getElementById('captcha-img'), input = document.getElementById('captcha');
  if (!img || !input) return;
  const baru = () => { img.src = 'captcha.php?t=' + Date.now(); input.value = ''; };
  document.getElementById('captcha-baru').addEventListener('click', () => { baru(); input.focus(); });
  window.addEventListener('pageshow', e => { if (e.persisted) baru(); });
})();
