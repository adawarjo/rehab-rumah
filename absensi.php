<?php
require 'inc/init.php';
$title = 'Absensi';
$active = 'absensi';

$tanggal = get_date('tanggal', date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = valid_date(post('tanggal')) ? post('tanggal') : date('Y-m-d');
    $status = $_POST['status'] ?? [];
    $lembur = $_POST['lembur'] ?? [];
    $ket    = $_POST['ket'] ?? [];
    $peran  = $_POST['peran'] ?? [];
    $n = 0;
    db()->beginTransaction();
    foreach ((array)($_POST['pid'] ?? []) as $pid) {
        $pid = (int)$pid;
        $lama = row('SELECT id, gaji_id FROM absensi WHERE pekerja_id = ? AND tanggal = ?', [$pid, $tanggal]);
        if ($lama && $lama['gaji_id']) continue; // sudah dibayar, terkunci
        $st = $status[$pid] ?? '';
        if (!in_array($st, ['H', 'S', 'A'], true)) {
            if ($lama) q('DELETE FROM absensi WHERE id = ?', [$lama['id']]);
            continue;
        }
        $jam = max(0, desimal($lembur[$pid] ?? 0));
        $pr = ($peran[$pid] ?? '') === 'pelayan' ? 'pelayan' : 'tukang';
        $tf = tarif($pr); // tarif dicatat per hari, sesuai peran hari itu
        q('INSERT INTO absensi (pekerja_id, tanggal, status, peran, upah_harian, upah_lembur, lembur_jam, keterangan) VALUES (?,?,?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE status = VALUES(status), peran = VALUES(peran), upah_harian = VALUES(upah_harian),
             upah_lembur = VALUES(upah_lembur), lembur_jam = VALUES(lembur_jam), keterangan = VALUES(keterangan)',
          [$pid, $tanggal, $st, $pr, $tf['harian'], $tf['lembur'], $st === 'A' ? 0 : $jam, trim($ket[$pid] ?? '')]);
        $n++;
    }
    db()->commit();
    flash("Absensi " . tgl($tanggal) . " tersimpan ($n pekerja).");
    redirect('absensi.php?tanggal=' . $tanggal);
}

// Peran awal: yang tercatat hari itu → peran terakhir orang tsb → peran utamanya
$list = rows('SELECT p.id, p.nama, p.jenis, a.status, a.peran, a.upah_harian, a.lembur_jam, a.keterangan, a.gaji_id,
                (SELECT b.peran FROM absensi b WHERE b.pekerja_id = p.id AND b.tanggal < ? AND b.status <> \'A\'
                 ORDER BY b.tanggal DESC LIMIT 1) peran_terakhir
              FROM pekerja p LEFT JOIN absensi a ON a.pekerja_id = p.id AND a.tanggal = ?
              WHERE p.aktif = 1 OR a.id IS NOT NULL
              ORDER BY p.jenis, p.nama', [$tanggal, $tanggal]);
$tf = ['tukang' => tarif('tukang'), 'pelayan' => tarif('pelayan')];
$rb = fn($v) => number_format($v / 1000, 0, ',', '.') . 'rb';

$prev = date('Y-m-d', strtotime("$tanggal -1 day"));
$next = date('Y-m-d', strtotime("$tanggal +1 day"));
$header_action = '<a href="rekap_absensi.php" class="btn btn-outline-primary btn-sm bg-white"><i class="bi bi-table"></i> Rekap</a>';
require 'inc/header.php';
?>

<form method="get" class="card mb-3">
  <div class="card-body py-2 d-flex align-items-center gap-2">
    <a href="?tanggal=<?= $prev ?>" class="btn btn-light"><i class="bi bi-chevron-left"></i></a>
    <input type="date" name="tanggal" value="<?= $tanggal ?>" class="form-control text-center" onchange="this.form.submit()">
    <a href="?tanggal=<?= $next ?>" class="btn btn-light"><i class="bi bi-chevron-right"></i></a>
  </div>
  <div class="text-center small-muted pb-2"><?= tgl($tanggal, true) ?><?= $tanggal === date('Y-m-d') ? ' · Hari ini' : '' ?></div>
</form>

<?php if (!$list): ?>
  <div class="card p-3 small-muted">Belum ada pekerja aktif. <a href="pekerja.php?form=1">Tambah pekerja</a>.</div>
<?php else: ?>
<form method="post" class="card mb-3">
  <?= csrf_field() ?>
  <input type="hidden" name="tanggal" value="<?= $tanggal ?>">
  <div class="d-flex gap-2 p-2 border-bottom bg-light rounded-top">
    <button type="button" class="btn btn-sm btn-outline-success flex-fill" onclick="document.querySelectorAll('.r-H:not(:disabled)').forEach(r=>r.checked=true)">
      <i class="bi bi-check2-all"></i> Semua hadir</button>
    <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" onclick="document.querySelectorAll('.r-K:not(:disabled)').forEach(r=>r.checked=true)">
      Kosongkan</button>
  </div>
  <?php foreach ($list as $p): $lock = (bool)$p['gaji_id']; $id = $p['id'];
        $pr = $p['peran'] ?? $p['peran_terakhir'] ?? $p['jenis']; ?>
    <div class="absen-row">
      <input type="hidden" name="pid[]" value="<?= $id ?>">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <span class="fw-semibold"><?= e($p['nama']) ?></span>
          <?php if ($lock): ?><span class="badge text-bg-success fw-normal"><i class="bi bi-lock"></i> Dibayar</span><?php endif; ?>
        </div>
        <div class="btn-group btn-group-sm peran-toggle" role="group" aria-label="Peran hari ini">
          <?php foreach (['tukang' => 'Tukang', 'pelayan' => 'Pelayan'] as $v => $l): ?>
            <input type="radio" class="btn-check" name="peran[<?= $id ?>]" id="p<?= $id . $v ?>" value="<?= $v ?>" <?= $pr === $v ? 'checked' : '' ?> <?= $lock ? 'disabled' : '' ?>>
            <label class="btn btn-outline-<?= $v === 'tukang' ? 'tukang' : 'pelayan' ?>" for="p<?= $id . $v ?>"><?= $l ?>
              <span class="opacity-75"><?= $lock && $p['peran'] === $v ? $rb($p['upah_harian']) : $rb($tf[$v]['harian']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="d-flex gap-1 flex-wrap align-items-center">
        <?php foreach (['H' => ['Hadir', 'success'], 'S' => ['½', 'warning'], 'A' => ['Absen', 'danger']] as $v => [$l, $c]): ?>
          <input type="radio" class="btn-check r-<?= $v ?>" name="status[<?= $id ?>]" id="s<?= $id . $v ?>" value="<?= $v ?>"
                 <?= $p['status'] === $v ? 'checked' : '' ?> <?= $lock ? 'disabled' : '' ?>>
          <label class="btn btn-sm btn-outline-<?= $c ?>" for="s<?= $id . $v ?>"><?= $l ?></label>
        <?php endforeach; ?>
        <input type="radio" class="btn-check r-K" name="status[<?= $id ?>]" id="s<?= $id ?>K" value="" <?= $p['status'] === null ? 'checked' : '' ?> <?= $lock ? 'disabled' : '' ?>>
        <label class="btn btn-sm btn-outline-secondary" for="s<?= $id ?>K" title="Belum diisi">–</label>
        <div class="input-group input-group-sm ms-auto" style="width:110px">
          <span class="input-group-text" title="Jam lembur"><i class="bi bi-moon-stars"></i></span>
          <input type="text" inputmode="decimal" name="lembur[<?= $id ?>]" class="form-control" placeholder="jam"
                 value="<?= $p['lembur_jam'] > 0 ? num($p['lembur_jam']) : '' ?>" <?= $lock ? 'disabled' : '' ?>>
        </div>
      </div>
      <input type="text" name="ket[<?= $id ?>]" class="form-control form-control-sm mt-2" placeholder="Keterangan (opsional)"
             value="<?= e($p['keterangan'] ?? '') ?>" <?= $lock ? 'disabled' : '' ?>>
    </div>
  <?php endforeach; ?>
  <div class="p-3">
    <button class="btn btn-primary w-100 btn-lg"><i class="bi bi-save"></i> Simpan Absensi</button>
  </div>
</form>
<p class="small-muted">H = 1 hari upah, ½ = setengah hari, Absen = tidak dibayar. Upah mengikuti <b>peran hari itu</b>: tukang <?= rp($tf['tukang']['harian']) ?>, pelayan <?= rp($tf['pelayan']['harian']) ?> per hari.
  Lembur per jam: tukang <?= rp($tf['tukang']['lembur']) ?>, pelayan <?= rp($tf['pelayan']['lembur']) ?>. <a href="pengaturan.php#tarif">Ubah tarif</a></p>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>
