<?php
require 'inc/init.php';
$title = 'Kasbon Tukang';
$active = 'kasbon';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('aksi') === 'simpan') {
        $pid = (int)post('pekerja_id');
        $jumlah = angka(post('jumlah'));
        $jenis = post('jenis') === 'potong' ? 'potong' : 'pinjam';
        $tgl = valid_date(post('tanggal')) ? post('tanggal') : date('Y-m-d');
        if (!$pid || $jumlah <= 0) {
            flash('Pilih pekerja dan isi jumlah.', 'danger');
        } else {
            q('INSERT INTO kasbon (pekerja_id, tanggal, jenis, jumlah, keterangan) VALUES (?,?,?,?,?)',
              [$pid, $tgl, $jenis, $jumlah, post('keterangan') ?: ($jenis === 'potong' ? 'Pelunasan tunai' : null)]);
            flash($jenis === 'pinjam' ? 'Kasbon dicatat.' : 'Pelunasan kasbon dicatat.');
        }
        redirect('kasbon.php' . ($pid ? '?pekerja=' . $pid : ''));
    }
    if (post('aksi') === 'hapus') {
        $k = row('SELECT * FROM kasbon WHERE id = ?', [(int)post('id')]);
        if ($k && $k['gaji_id']) flash('Potongan dari gaji hanya bisa dihapus dengan membatalkan pembayaran gajinya.', 'warning');
        elseif ($k) { q('DELETE FROM kasbon WHERE id = ?', [$k['id']]); flash('Data kasbon dihapus.'); }
        redirect('kasbon.php' . ($k ? '?pekerja=' . $k['pekerja_id'] : ''));
    }
}

$filter = (int)($_GET['pekerja'] ?? 0);
$saldo = rows('SELECT p.id, p.nama, p.jenis,
                 SUM(CASE WHEN k.jenis = \'pinjam\' THEN k.jumlah ELSE 0 END) pinjam,
                 SUM(CASE WHEN k.jenis = \'potong\' THEN k.jumlah ELSE 0 END) potong,
                 SUM(' . SQL_KASBON . ') saldo
               FROM pekerja p JOIN kasbon k ON k.pekerja_id = p.id
               GROUP BY p.id, p.nama, p.jenis ORDER BY saldo DESC, p.nama');
$total_saldo = array_sum(array_column($saldo, 'saldo'));

$riwayat = rows('SELECT k.*, p.nama FROM kasbon k JOIN pekerja p ON p.id = k.pekerja_id'
              . ($filter ? ' WHERE k.pekerja_id = ' . $filter : '')
              . ' ORDER BY k.tanggal DESC, k.id DESC LIMIT 200');

require 'inc/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <div class="btn-group w-100 mb-2">
        <input type="radio" class="btn-check" name="jenis" id="jp" value="pinjam" checked>
        <label class="btn btn-outline-primary" for="jp"><i class="bi bi-box-arrow-up-right"></i> Ambil kasbon</label>
        <input type="radio" class="btn-check" name="jenis" id="jb" value="potong">
        <label class="btn btn-outline-primary" for="jb"><i class="bi bi-box-arrow-in-down-left"></i> Bayar / lunasi</label>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-12 col-md-5">
          <select name="pekerja_id" class="form-select" required>
            <option value="">— Pilih pekerja —</option><?= opsi_pekerja($filter ?: null) ?>
          </select>
        </div>
        <div class="col-6 col-md-3"><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="form-control"></div>
        <div class="col-6 col-md-4"><input name="jumlah" class="form-control uang" inputmode="numeric" placeholder="Jumlah (Rp)" required></div>
      </div>
      <input name="keterangan" class="form-control mb-2" placeholder="Keterangan (opsional)">
      <button class="btn btn-primary w-100">Simpan</button>
    </form>
    <div class="small-muted mt-2">Kasbon otomatis ditawarkan sebagai potongan saat membayar gaji.</div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header bg-white d-flex justify-content-between">
    <span class="fw-semibold">Saldo kasbon</span><span class="fw-bold text-danger money"><?= rp($total_saldo) ?></span>
  </div>
  <?php if (!$saldo): ?><div class="p-3 small-muted">Belum ada kasbon.</div><?php endif; ?>
  <?php foreach ($saldo as $s): ?>
    <a href="?pekerja=<?= $s['id'] ?>" class="tx text-decoration-none text-body <?= $filter === (int)$s['id'] ? 'bg-warning-subtle' : '' ?>">
      <div class="tx-main">
        <div class="tx-title"><?= e($s['nama']) ?></div>
        <div class="small-muted">Pinjam <?= rp($s['pinjam']) ?> · Lunas <?= rp($s['potong']) ?></div>
      </div>
      <div class="money fw-bold <?= $s['saldo'] > 0 ? 'text-danger' : 'text-success' ?>"><?= $s['saldo'] > 0 ? rp($s['saldo']) : 'Lunas' ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Riwayat<?= $filter && $riwayat ? ' · ' . e($riwayat[0]['nama']) : '' ?></span>
    <?php if ($filter): ?><a href="kasbon.php" class="small">Tampilkan semua</a><?php endif; ?>
  </div>
  <?php if (!$riwayat): ?><div class="p-3 small-muted">Tidak ada data.</div><?php endif; ?>
  <?php foreach ($riwayat as $k): ?>
    <div class="tx">
      <i class="bi <?= $k['jenis'] === 'pinjam' ? 'bi-arrow-up-right-circle text-danger' : 'bi-arrow-down-left-circle text-success' ?> fs-4"></i>
      <div class="tx-main">
        <div class="tx-title"><?= e($k['nama']) ?></div>
        <div class="small-muted"><?= tgl($k['tanggal']) ?> · <?= $k['jenis'] === 'pinjam' ? 'Kasbon' : 'Pelunasan' ?><?= $k['keterangan'] ? ' · ' . e($k['keterangan']) : '' ?></div>
      </div>
      <div class="text-end">
        <div class="money fw-semibold <?= $k['jenis'] === 'pinjam' ? 'text-danger' : 'text-success' ?>"><?= $k['jenis'] === 'pinjam' ? '' : '−' ?><?= rp($k['jumlah']) ?></div>
        <?= $k['gaji_id'] ? '<a href="slip.php?id=' . $k['gaji_id'] . '" class="small">slip gaji</a>' : tombol_hapus('kasbon.php', $k['id']) ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require 'inc/footer.php'; ?>
