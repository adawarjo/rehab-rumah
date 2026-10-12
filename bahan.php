<?php
require 'inc/init.php';
$title = 'Bahan Baku';
$active = 'bahan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    if (post('aksi') === 'simpan') {
        $status = post('status') === 'bon' ? 'bon' : 'lunas';
        $toko = (int)post('toko_id') ?: null;
        $qty = desimal(post('qty')) ?: 1;
        $harga = angka(post('harga_satuan'));
        $total = angka(post('total')) ?: round($qty * $harga);
        if (!$harga && $total) $harga = round($total / $qty);
        $tgl = valid_date(post('tanggal')) ? post('tanggal') : date('Y-m-d');
        if (post('nama_barang') === '' || $total <= 0) {
            flash('Nama barang dan harga wajib diisi.', 'danger');
            redirect('bahan.php?form=1');
        }
        if ($status === 'bon' && !$toko) {
            flash('Belanja bon harus memilih toko.', 'danger');
            redirect('bahan.php?form=1');
        }
        $data = [$tgl, $toko, post('no_nota') ?: null, post('nama_barang'), $qty, post('satuan') ?: null, $harga, $total, $status, post('keterangan') ?: null];
        if ($id) {
            q('UPDATE bahan SET tanggal=?, toko_id=?, no_nota=?, nama_barang=?, qty=?, satuan=?, harga_satuan=?, total=?, status=?, keterangan=? WHERE id=?', [...$data, $id]);
            flash('Data belanja diperbarui.');
        } else {
            q('INSERT INTO bahan (tanggal, toko_id, no_nota, nama_barang, qty, satuan, harga_satuan, total, status, keterangan) VALUES (?,?,?,?,?,?,?,?,?,?)', $data);
            flash('Belanja ' . post('nama_barang') . ' dicatat' . ($status === 'bon' ? ' sebagai BON.' : '.'));
            // tetap di form agar bisa input barang berikutnya dari nota yang sama
            if (post('lagi')) redirect('bahan.php?form=1&tanggal=' . $tgl . '&toko=' . $toko . '&nota=' . rawurlencode(post('no_nota')) . '&status=' . $status);
        }
    } elseif (post('aksi') === 'hapus') {
        q('DELETE FROM bahan WHERE id = ?', [$id]);
        flash('Data belanja dihapus.');
    }
    redirect('bahan.php');
}

$edit = isset($_GET['edit']) ? row('SELECT * FROM bahan WHERE id = ?', [(int)$_GET['edit']]) : null;
$show_form = $edit || isset($_GET['form']);

// Filter
$bulan  = preg_match('/^\d{4}-\d{2}$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';
$f_toko = (int)($_GET['toko'] ?? 0);
$f_st   = in_array($_GET['status'] ?? '', ['lunas', 'bon'], true) ? $_GET['status'] : '';
$cari   = trim($_GET['cari'] ?? '');
$where = ['1=1']; $par = [];
if ($bulan)  { $where[] = "DATE_FORMAT(b.tanggal, '%Y-%m') = ?"; $par[] = $bulan; }
if ($f_toko && !$show_form) { $where[] = 'b.toko_id = ?'; $par[] = $f_toko; }
if ($f_st && !$show_form)   { $where[] = 'b.status = ?'; $par[] = $f_st; }
if ($cari)   { $where[] = 'b.nama_barang LIKE ?'; $par[] = "%$cari%"; }
$list = rows('SELECT b.*, t.nama toko FROM bahan b LEFT JOIN toko t ON t.id = b.toko_id WHERE ' . implode(' AND ', $where) . ' ORDER BY b.tanggal DESC, b.id DESC LIMIT 300', $par);
$total = array_sum(array_column($list, 'total'));
$total_bon = array_sum(array_map(fn($b) => $b['status'] === 'bon' ? $b['total'] : 0, $list));

$barang = q('SELECT DISTINCT nama_barang FROM bahan ORDER BY nama_barang LIMIT 300')->fetchAll(PDO::FETCH_COLUMN);
$satuan = array_unique(array_merge(['sak', 'batang', 'lembar', 'buah', 'kg', 'm', 'm²', 'm³', 'rit', 'colt', 'dus', 'liter', 'kaleng', 'roll', 'set'],
          q('SELECT DISTINCT satuan FROM bahan WHERE satuan IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN)));
$bulan_list = q("SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') FROM bahan ORDER BY 1 DESC")->fetchAll(PDO::FETCH_COLUMN);

require 'inc/header.php';
?>

<?php if ($show_form):
    $d = $edit ?? ['tanggal' => get_date('tanggal', date('Y-m-d')), 'toko_id' => (int)($_GET['toko'] ?? 0), 'no_nota' => $_GET['nota'] ?? '',
                   'status' => ($_GET['status'] ?? '') === 'bon' ? 'bon' : 'lunas', 'qty' => '', 'nama_barang' => '', 'satuan' => '', 'harga_satuan' => '', 'total' => '', 'keterangan' => ''];
?>
<div class="card mb-3">
  <div class="card-body">
    <h2 class="h6 fw-bold"><?= $edit ? 'Ubah belanja' : 'Catat belanja bahan' ?></h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
      <div class="btn-group w-100 mb-2">
        <input type="radio" class="btn-check" name="status" id="st-l" value="lunas" <?= $d['status'] === 'lunas' ? 'checked' : '' ?>>
        <label class="btn btn-outline-success" for="st-l"><i class="bi bi-cash"></i> Bayar tunai</label>
        <input type="radio" class="btn-check" name="status" id="st-b" value="bon" <?= $d['status'] === 'bon' ? 'checked' : '' ?>>
        <label class="btn btn-outline-danger" for="st-b"><i class="bi bi-journal-text"></i> Bon (hutang)</label>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small mb-1">Tanggal</label><input type="date" name="tanggal" value="<?= e($d['tanggal']) ?>" class="form-control"></div>
        <div class="col-6"><label class="form-label small mb-1">No. nota</label><input name="no_nota" value="<?= e($d['no_nota']) ?>" class="form-control" placeholder="opsional"></div>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-1">Toko <a href="toko.php?form=1" class="ms-1">+ toko baru</a></label>
        <select name="toko_id" id="toko_id" class="form-select"><option value="">— Tanpa toko / eceran —</option><?= opsi_toko($d['toko_id']) ?></select>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-1">Nama barang</label>
        <input name="nama_barang" value="<?= e($d['nama_barang']) ?>" class="form-control" list="dl-barang" placeholder="mis. Semen Tiga Roda 50kg" required>
        <datalist id="dl-barang"><?php foreach ($barang as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-4"><label class="form-label small mb-1">Jumlah</label><input name="qty" id="qty" value="<?= $d['qty'] !== '' ? num($d['qty']) : '' ?>" class="form-control" inputmode="decimal" placeholder="1"></div>
        <div class="col-3"><label class="form-label small mb-1">Satuan</label>
          <input name="satuan" value="<?= e($d['satuan']) ?>" class="form-control px-2" list="dl-satuan" placeholder="sak">
          <datalist id="dl-satuan"><?php foreach ($satuan as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-5"><label class="form-label small mb-1">Harga satuan</label><input name="harga_satuan" id="harga_satuan" value="<?= $d['harga_satuan'] !== '' ? (int)$d['harga_satuan'] : '' ?>" class="form-control uang" inputmode="numeric"></div>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-1">Total (Rp)</label>
        <input name="total" id="total" value="<?= $d['total'] !== '' ? (int)$d['total'] : '' ?>" class="form-control form-control-lg fw-bold uang" inputmode="numeric" required>
      </div>
      <input name="keterangan" value="<?= e($d['keterangan']) ?>" class="form-control mb-3" placeholder="Keterangan (opsional)">
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary flex-fill">Simpan</button>
        <?php if (!$edit): ?><button name="lagi" value="1" class="btn btn-outline-primary flex-fill">Simpan &amp; tambah lagi</button><?php endif; ?>
        <a href="bahan.php" class="btn btn-light">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php else: ?>

<form method="get" class="card mb-3">
  <div class="card-body row g-2">
    <div class="col-6 col-md-3">
      <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Semua bulan</option>
        <?php foreach ($bulan_list as $b): ?><option value="<?= $b ?>" <?= $bulan === $b ? 'selected' : '' ?>><?= substr(tgl($b . '-01'), 2) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Semua status</option>
        <option value="lunas" <?= $f_st === 'lunas' ? 'selected' : '' ?>>Tunai</option>
        <option value="bon" <?= $f_st === 'bon' ? 'selected' : '' ?>>Bon</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select name="toko" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Semua toko</option><?= opsi_toko($f_toko) ?></select>
    </div>
    <div class="col-6 col-md-3">
      <input name="cari" value="<?= e($cari) ?>" class="form-control form-control-sm" placeholder="Cari barang…">
    </div>
  </div>
</form>

<div class="card mb-3">
  <div class="card-header bg-white d-flex justify-content-between">
    <span class="fw-semibold"><?= count($list) ?> transaksi</span>
    <span class="text-end"><b class="money"><?= rp($total) ?></b><?php if ($total_bon): ?><div class="small text-danger">bon <?= rp($total_bon) ?></div><?php endif; ?></span>
  </div>
  <?php if (!$list): ?><div class="p-3 small-muted">Belum ada belanja bahan.</div><?php endif; ?>
  <?php foreach ($list as $b): ?>
    <div class="tx">
      <div class="tx-main">
        <div class="tx-title"><?= e($b['nama_barang']) ?></div>
        <div class="small-muted"><?= num($b['qty']) ?> <?= e($b['satuan']) ?> × <?= rp($b['harga_satuan']) ?></div>
        <div class="small-muted"><?= tgl($b['tanggal']) ?><?= $b['toko'] ? ' · ' . e($b['toko']) : '' ?><?= $b['no_nota'] ? ' · #' . e($b['no_nota']) : '' ?><?= $b['keterangan'] ? ' · ' . e($b['keterangan']) : '' ?></div>
      </div>
      <div class="text-end">
        <div class="money fw-semibold"><?= rp($b['total']) ?></div>
        <span class="badge <?= $b['status'] === 'bon' ? 'text-bg-danger' : 'text-bg-success' ?> fw-normal"><?= $b['status'] === 'bon' ? 'Bon' : 'Tunai' ?></span>
        <div class="mt-1">
          <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
          <?= tombol_hapus('bahan.php', $b['id'], 'Hapus ' . $b['nama_barang'] . '?') ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<a href="bahan.php?form=1" class="btn btn-primary fab d-print-none" title="Catat belanja"><i class="bi bi-plus-lg"></i></a>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>
