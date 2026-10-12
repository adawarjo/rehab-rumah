<?php
require 'inc/init.php';
$title = 'Toko & Bon Bahan';
$active = 'toko';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int)post('id');
    if ($aksi === 'simpan') {
        if (post('nama') === '') { flash('Nama toko wajib diisi.', 'danger'); redirect('toko.php?form=1'); }
        if ($id) q('UPDATE toko SET nama=?, alamat=?, no_hp=? WHERE id=?', [post('nama'), post('alamat'), post('no_hp'), $id]);
        else { q('INSERT INTO toko (nama, alamat, no_hp) VALUES (?,?,?)', [post('nama'), post('alamat'), post('no_hp')]); $id = (int)db()->lastInsertId(); }
        flash('Data toko disimpan.');
        redirect('toko.php?id=' . $id);
    }
    if ($aksi === 'hapus') {
        q('DELETE FROM toko WHERE id = ?', [$id]);
        flash('Toko dihapus. Riwayat belanjanya tetap ada tanpa nama toko.');
        redirect('toko.php');
    }
    if ($aksi === 'bayar') {
        $jml = angka(post('jumlah'));
        if ($jml > 0) {
            q('INSERT INTO bayar_toko (toko_id, tanggal, jumlah, keterangan) VALUES (?,?,?,?)',
              [$id, valid_date(post('tanggal')) ? post('tanggal') : date('Y-m-d'), $jml, post('keterangan') ?: null]);
            flash('Pembayaran bon ' . rp($jml) . ' dicatat.');
        }
        redirect('toko.php?id=' . $id);
    }
    if ($aksi === 'hapus_bayar') {
        $tid = (int)val('SELECT toko_id FROM bayar_toko WHERE id = ?', [$id]);
        q('DELETE FROM bayar_toko WHERE id = ?', [$id]);
        flash('Pembayaran dihapus.');
        redirect('toko.php?id=' . $tid);
    }
}

$sql_saldo = 'SELECT t.*,
   COALESCE((SELECT SUM(total) FROM bahan b WHERE b.toko_id = t.id), 0) belanja,
   COALESCE((SELECT SUM(total) FROM bahan b WHERE b.toko_id = t.id AND b.status = \'bon\'), 0) bon,
   COALESCE((SELECT SUM(jumlah) FROM bayar_toko y WHERE y.toko_id = t.id), 0) dibayar
   FROM toko t';

$detail = isset($_GET['id']) ? row($sql_saldo . ' WHERE t.id = ?', [(int)$_GET['id']]) : null;
$edit = isset($_GET['edit']) ? row('SELECT * FROM toko WHERE id = ?', [(int)$_GET['edit']]) : null;
$show_form = $edit || isset($_GET['form']);

if (!$detail && !$show_form) {
    $header_action = '<a href="toko.php?form=1" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Toko</a>';
}
require 'inc/header.php';

// ---------- Form toko ----------
if ($show_form): ?>
<div class="card mb-3"><div class="card-body">
  <h2 class="h6 fw-bold"><?= $edit ? 'Ubah toko' : 'Tambah toko' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
    <input name="nama" class="form-control mb-2" placeholder="Nama toko" value="<?= e($edit['nama'] ?? '') ?>" required>
    <input name="alamat" class="form-control mb-2" placeholder="Alamat" value="<?= e($edit['alamat'] ?? '') ?>">
    <input name="no_hp" class="form-control mb-3" placeholder="No. HP / WA" inputmode="tel" value="<?= e($edit['no_hp'] ?? '') ?>">
    <div class="d-flex gap-2"><button class="btn btn-primary flex-fill">Simpan</button><a href="toko.php" class="btn btn-light">Batal</a></div>
  </form>
</div></div>

<?php
// ---------- Detail satu toko ----------
elseif ($detail):
    $sisa = $detail['bon'] - $detail['dibayar'];
    $bon = rows("SELECT * FROM bahan WHERE toko_id = ? AND status = 'bon' ORDER BY tanggal DESC, id DESC", [$detail['id']]);
    $bayar = rows('SELECT * FROM bayar_toko WHERE toko_id = ? ORDER BY tanggal DESC, id DESC', [$detail['id']]);
?>
<a href="toko.php" class="small d-inline-block mb-2"><i class="bi bi-arrow-left"></i> Semua toko</a>
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between">
      <div>
        <div class="fw-bold fs-5"><?= e($detail['nama']) ?></div>
        <div class="small-muted"><?= e($detail['alamat']) ?> <?= $detail['no_hp'] ? '· <a href="tel:' . e($detail['no_hp']) . '">' . e($detail['no_hp']) . '</a>' : '' ?></div>
      </div>
      <div><a href="?edit=<?= $detail['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
        <?= tombol_hapus('toko.php', $detail['id'], 'Hapus toko ' . $detail['nama'] . '? Catatan pembayaran bon ke toko ini ikut terhapus.') ?></div>
    </div>
    <div class="row text-center g-2 mt-2">
      <div class="col-4"><div class="small-muted">Total bon</div><div class="fw-semibold money small"><?= rp($detail['bon']) ?></div></div>
      <div class="col-4"><div class="small-muted">Dibayar</div><div class="fw-semibold money small text-success"><?= rp($detail['dibayar']) ?></div></div>
      <div class="col-4"><div class="small-muted">Sisa hutang</div><div class="fw-bold money small <?= $sisa > 0 ? 'text-danger' : 'text-success' ?>"><?= rp($sisa) ?></div></div>
    </div>
  </div>
</div>

<form method="post" class="card mb-3">
  <div class="card-body">
    <div class="fw-semibold mb-2">Bayar / cicil bon</div>
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="bayar"><input type="hidden" name="id" value="<?= $detail['id'] ?>">
    <div class="row g-2">
      <div class="col-6"><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="form-control"></div>
      <div class="col-6"><input name="jumlah" class="form-control uang" inputmode="numeric" placeholder="Jumlah" value="<?= $sisa > 0 ? (int)$sisa : '' ?>" required></div>
      <div class="col-12"><input name="keterangan" class="form-control" placeholder="Keterangan (opsional)"></div>
      <div class="col-12"><button class="btn btn-primary w-100">Catat pembayaran</button></div>
    </div>
  </div>
</form>

<div class="card mb-3">
  <div class="card-header bg-white fw-semibold">Pembayaran bon</div>
  <?php if (!$bayar): ?><div class="p-3 small-muted">Belum ada pembayaran.</div><?php endif; ?>
  <?php foreach ($bayar as $y): ?>
    <div class="tx">
      <i class="bi bi-check-circle text-success fs-5"></i>
      <div class="tx-main"><div class="tx-title"><?= tgl($y['tanggal']) ?></div><div class="small-muted"><?= e($y['keterangan']) ?></div></div>
      <div class="money fw-semibold text-success"><?= rp($y['jumlah']) ?></div>
      <form method="post" data-confirm="Hapus pembayaran ini?" data-confirm-type="danger" data-confirm-ok="Ya, hapus"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus_bayar"><input type="hidden" name="id" value="<?= $y['id'] ?>">
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Barang yang dibon</span><a href="bahan.php?form=1&toko=<?= $detail['id'] ?>&status=bon" class="small">+ catat bon</a></div>
  <?php if (!$bon): ?><div class="p-3 small-muted">Tidak ada bon.</div><?php endif; ?>
  <?php foreach ($bon as $b): ?>
    <div class="tx">
      <div class="tx-main">
        <div class="tx-title"><?= e($b['nama_barang']) ?></div>
        <div class="small-muted"><?= tgl($b['tanggal']) ?> · <?= num($b['qty']) ?> <?= e($b['satuan']) ?><?= $b['no_nota'] ? ' · #' . e($b['no_nota']) : '' ?></div>
      </div>
      <div class="money"><?= rp($b['total']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php
// ---------- Daftar toko ----------
else:
    $list = rows($sql_saldo . ' ORDER BY t.nama');
    usort($list, fn($a, $b) => ($b['bon'] - $b['dibayar']) <=> ($a['bon'] - $a['dibayar']));
    $tot_sisa = array_sum(array_map(fn($t) => $t['bon'] - $t['dibayar'], $list));
?>
<div class="card mb-3 text-white" style="background:#991b1b">
  <div class="card-body"><div class="small text-white-50 text-uppercase">Total hutang ke toko</div><div class="fs-3 fw-bold money"><?= rp($tot_sisa) ?></div></div>
</div>
<div class="card">
  <?php if (!$list): ?><div class="p-3 small-muted">Belum ada toko. <a href="toko.php?form=1">Tambah toko</a>.</div><?php endif; ?>
  <?php foreach ($list as $t): $sisa = $t['bon'] - $t['dibayar']; ?>
    <a href="?id=<?= $t['id'] ?>" class="tx text-decoration-none text-body">
      <i class="bi bi-shop fs-4 text-secondary"></i>
      <div class="tx-main">
        <div class="tx-title"><?= e($t['nama']) ?></div>
        <div class="small-muted">Belanja <?= rp($t['belanja']) ?> · bon <?= rp($t['bon']) ?></div>
      </div>
      <div class="text-end">
        <div class="money fw-bold <?= $sisa > 0 ? 'text-danger' : 'text-success' ?>"><?= $sisa > 0 ? rp($sisa) : 'Lunas' ?></div>
      </div>
      <i class="bi bi-chevron-right text-muted"></i>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>
