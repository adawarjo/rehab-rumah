<?php
require 'inc/init.php';
$title = 'Data Pekerja';
$active = 'pekerja';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int)post('id');
    if ($aksi === 'simpan') {
        $data = [post('nama'), post('jenis') === 'pelayan' ? 'pelayan' : 'tukang', post('no_hp'), post('aktif') ? 1 : 0];
        if ($data[0] === '') { flash('Nama wajib diisi.', 'danger'); redirect('pekerja.php?form=1'); }
        if ($id) {
            q('UPDATE pekerja SET nama=?, jenis=?, no_hp=?, aktif=? WHERE id=?', [...$data, $id]);
            flash('Data pekerja diperbarui.');
        } else {
            q('INSERT INTO pekerja (nama, jenis, no_hp, aktif) VALUES (?,?,?,?)', $data);
            flash('Pekerja ditambahkan.');
        }
    } elseif ($aksi === 'hapus') {
        try {
            q('DELETE FROM pekerja WHERE id = ?', [$id]);
            flash('Pekerja dihapus.');
        } catch (PDOException $e) {
            flash('Pekerja ini sudah punya riwayat gaji, jadi tidak bisa dihapus. Nonaktifkan saja.', 'warning');
        }
    }
    redirect('pekerja.php');
}

$edit = null;
if (isset($_GET['edit'])) $edit = row('SELECT * FROM pekerja WHERE id = ?', [(int)$_GET['edit']]);
$show_form = $edit || isset($_GET['form']);

$list = rows('SELECT p.*,
   (SELECT COALESCE(SUM(' . SQL_HARI . '),0) FROM absensi a WHERE a.pekerja_id = p.id AND a.peran = \'tukang\') hari_tukang,
   (SELECT COALESCE(SUM(' . SQL_HARI . '),0) FROM absensi a WHERE a.pekerja_id = p.id AND a.peran = \'pelayan\') hari_pelayan,
   (SELECT COALESCE(SUM(' . SQL_KASBON . '),0) FROM kasbon k WHERE k.pekerja_id = p.id) kasbon
   FROM pekerja p ORDER BY p.aktif DESC, p.jenis, p.nama');

$header_action = $show_form ? '' : '<a href="pekerja.php?form=1" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>';
require 'inc/header.php';
?>

<?php if ($show_form): ?>
<div class="card mb-3">
  <div class="card-body">
    <h2 class="h6 fw-bold"><?= $edit ? 'Ubah pekerja' : 'Tambah pekerja' ?></h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
      <div class="mb-2">
        <label class="form-label">Nama</label>
        <input name="nama" class="form-control" value="<?= e($edit['nama'] ?? '') ?>" required>
      </div>
      <div class="mb-2">
        <label class="form-label d-block mb-0">Peran utama</label>
        <div class="small-muted mb-1">Hanya pilihan awal. Peran & tarif dipilih per hari di Absensi.</div>
        <div class="btn-group w-100">
          <?php foreach (['tukang' => 'Tukang', 'pelayan' => 'Pelayan / Kenek'] as $v => $l): ?>
            <input type="radio" class="btn-check" name="jenis" id="j-<?= $v ?>" value="<?= $v ?>" <?= ($edit['jenis'] ?? 'tukang') === $v ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="j-<?= $v ?>"><?= $l ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mb-2">
        <label class="form-label">No. HP</label>
        <input name="no_hp" class="form-control" inputmode="tel" value="<?= e($edit['no_hp'] ?? '') ?>">
      </div>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1" <?= ($edit['aktif'] ?? 1) ? 'checked' : '' ?>>
        <label class="form-check-label" for="aktif">Masih bekerja (muncul di absensi)</label>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary flex-fill">Simpan</button>
        <a href="pekerja.php" class="btn btn-light">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <?php if (!$list): ?><div class="p-3 small-muted">Belum ada pekerja. Tambahkan tukang dan pelayan terlebih dahulu.</div><?php endif; ?>
  <?php foreach ($list as $p): ?>
    <div class="tx <?= $p['aktif'] ? '' : 'opacity-50' ?>">
      <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0"
           style="width:40px;height:40px;background:<?= $p['jenis'] === 'tukang' ? 'var(--brand)' : '#0891b2' ?>">
        <?= e(mb_strtoupper(mb_substr($p['nama'], 0, 1))) ?>
      </div>
      <div class="tx-main">
        <div class="tx-title"><?= e($p['nama']) ?>
          <span class="badge <?= $p['jenis'] === 'tukang' ? 'badge-tukang' : 'badge-pelayan' ?> fw-normal"><?= ucfirst($p['jenis']) ?></span>
          <?php if (!$p['aktif']): ?><span class="badge text-bg-secondary fw-normal">Nonaktif</span><?php endif; ?>
        </div>
        <div class="small-muted"><?= hari_peran($p['hari_tukang'], $p['hari_pelayan']) ?><?= $p['kasbon'] > 0 ? ' · kasbon <span class="text-danger">' . rp($p['kasbon']) . '</span>' : '' ?>
          <?= $p['no_hp'] ? ' · <a href="tel:' . e($p['no_hp']) . '">' . e($p['no_hp']) . '</a>' : '' ?></div>
      </div>
      <div class="d-flex flex-column gap-1">
        <a href="pekerja.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
        <?= tombol_hapus('pekerja.php', $p['id'], 'Hapus ' . $p['nama'] . '? Absensi & kasbonnya ikut terhapus.') ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require 'inc/footer.php'; ?>
