<?php
require 'inc/init.php';
$title = 'Pengeluaran Lain';
$active = 'lain';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    if (post('aksi') === 'simpan') {
        $jml = angka(post('jumlah'));
        if (post('kategori') === '' || $jml <= 0) { flash('Kategori dan jumlah wajib diisi.', 'danger'); redirect('lain.php'); }
        $data = [valid_date(post('tanggal')) ? post('tanggal') : date('Y-m-d'), post('kategori'), post('keterangan') ?: null, $jml];
        if ($id) q('UPDATE pengeluaran_lain SET tanggal=?, kategori=?, keterangan=?, jumlah=? WHERE id=?', [...$data, $id]);
        else q('INSERT INTO pengeluaran_lain (tanggal, kategori, keterangan, jumlah) VALUES (?,?,?,?)', $data);
        flash('Pengeluaran disimpan.');
    } elseif (post('aksi') === 'hapus') {
        q('DELETE FROM pengeluaran_lain WHERE id = ?', [$id]);
        flash('Pengeluaran dihapus.');
    }
    redirect('lain.php');
}

$edit = isset($_GET['edit']) ? row('SELECT * FROM pengeluaran_lain WHERE id = ?', [(int)$_GET['edit']]) : null;
$kategori = array_unique(array_merge(['Konsumsi tukang', 'Transport / ongkir', 'Sewa alat', 'Listrik & air', 'Buang puing', 'Perizinan', 'Lainnya'],
            q('SELECT DISTINCT kategori FROM pengeluaran_lain')->fetchAll(PDO::FETCH_COLUMN)));
$list = rows('SELECT * FROM pengeluaran_lain ORDER BY tanggal DESC, id DESC LIMIT 300');
$per_kat = rows('SELECT kategori, SUM(jumlah) jml FROM pengeluaran_lain GROUP BY kategori ORDER BY jml DESC');
$total = array_sum(array_column($per_kat, 'jml'));

require 'inc/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
    <?php if ($edit): ?><div class="fw-semibold mb-2">Ubah pengeluaran</div><?php endif; ?>
    <div class="row g-2 mb-2">
      <div class="col-6"><input type="date" name="tanggal" value="<?= e($edit['tanggal'] ?? date('Y-m-d')) ?>" class="form-control"></div>
      <div class="col-6"><input name="jumlah" class="form-control uang" inputmode="numeric" placeholder="Jumlah (Rp)" value="<?= isset($edit) ? (int)$edit['jumlah'] : '' ?>" required></div>
    </div>
    <input name="kategori" class="form-control mb-2" list="dl-kat" placeholder="Kategori (mis. Konsumsi tukang)" value="<?= e($edit['kategori'] ?? '') ?>" required>
    <datalist id="dl-kat"><?php foreach ($kategori as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?></datalist>
    <input name="keterangan" class="form-control mb-2" placeholder="Keterangan" value="<?= e($edit['keterangan'] ?? '') ?>">
    <div class="d-flex gap-2"><button class="btn btn-primary flex-fill">Simpan</button><?php if ($edit): ?><a href="lain.php" class="btn btn-light">Batal</a><?php endif; ?></div>
  </form>
</div></div>

<?php if ($per_kat): ?>
<div class="card mb-3"><div class="card-body py-2">
  <?php foreach ($per_kat as $k): ?>
    <div class="d-flex justify-content-between small py-1"><span><?= e($k['kategori']) ?></span><span class="money"><?= rp($k['jml']) ?></span></div>
  <?php endforeach; ?>
  <div class="d-flex justify-content-between fw-bold border-top pt-1 mt-1"><span>Total</span><span class="money"><?= rp($total) ?></span></div>
</div></div>
<?php endif; ?>

<div class="card">
  <?php if (!$list): ?><div class="p-3 small-muted">Belum ada pengeluaran lain.</div><?php endif; ?>
  <?php foreach ($list as $l): ?>
    <div class="tx">
      <div class="tx-main">
        <div class="tx-title"><?= e($l['keterangan'] ?: $l['kategori']) ?></div>
        <div class="small-muted"><?= tgl($l['tanggal']) ?> · <?= e($l['kategori']) ?></div>
      </div>
      <div class="text-end">
        <div class="money fw-semibold"><?= rp($l['jumlah']) ?></div>
        <a href="?edit=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
        <?= tombol_hapus('lain.php', $l['id']) ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require 'inc/footer.php'; ?>
