<?php
require 'inc/init.php';
$title = 'Gaji / Honor';
$active = 'gaji';

/** Hitung upah yang belum dibayar seorang pekerja s/d tanggal tertentu */
function hitung_gaji(int $pid, string $sampai): ?array {
    $p = row('SELECT * FROM pekerja WHERE id = ?', [$pid]);
    if (!$p) return null;
    $absen = rows('SELECT a.*, ' . SQL_HARI . ' nilai FROM absensi a WHERE a.pekerja_id = ? AND a.gaji_id IS NULL AND a.tanggal <= ? ORDER BY a.tanggal', [$pid, $sampai]);
    $r = rincian_upah($absen);
    $p['absen'] = $absen;
    $p['rincian'] = $r;
    $p['hari'] = array_sum(array_column($absen, 'nilai'));
    $p['jam'] = $r['jam'];
    $p['mulai'] = $absen ? $absen[0]['tanggal'] : null;
    $p['selesai'] = $absen ? end($absen)['tanggal'] : null;
    $p['total_upah'] = $r['upah'];
    $p['total_lembur'] = $r['lembur'];
    $p['kotor'] = $p['total_upah'] + $p['total_lembur'];
    $p['kasbon'] = (float)val('SELECT COALESCE(SUM(' . SQL_KASBON . '),0) FROM kasbon k WHERE k.pekerja_id = ?', [$pid]);
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'bayar') {
        $sampai = valid_date(post('sampai')) ? post('sampai') : date('Y-m-d');
        $g = hitung_gaji((int)post('pekerja_id'), $sampai);
        $bonus = angka(post('bonus'));
        $pot = angka(post('potongan'));
        $tgl_bayar = valid_date(post('tanggal_bayar')) ? post('tanggal_bayar') : date('Y-m-d');
        if (!$g || ($g['kotor'] + $bonus) <= 0) {
            flash('Tidak ada upah yang perlu dibayar.', 'warning');
            redirect('gaji.php');
        }
        $pot = max(0, min($pot, max(0, $g['kasbon']), $g['kotor'] + $bonus));
        $dibayar = $g['kotor'] + $bonus - $pot;
        // biaya per peran (untuk laporan honor tukang vs pelayan); tambahan masuk ke peran yang dominan
        $bt = round($g['rincian']['biaya']['tukang']); $bp = round($g['rincian']['biaya']['pelayan']);
        if ($bt > $bp || ($bt == $bp && $g['jenis'] === 'tukang')) $bt += $bonus; else $bp += $bonus;

        db()->beginTransaction();
        q('INSERT INTO gaji (pekerja_id, tanggal_bayar, periode_mulai, periode_selesai, hari_kerja, hari_tukang, hari_pelayan, jam_lembur,
                             total_upah, total_lembur, bonus, biaya_tukang, biaya_pelayan, potongan_kasbon, dibayar, keterangan)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$g['id'], $tgl_bayar, $g['mulai'], $g['selesai'], $g['hari'], $g['rincian']['hari']['tukang'], $g['rincian']['hari']['pelayan'], $g['jam'],
           $g['total_upah'], $g['total_lembur'], $bonus, $bt, $bp, $pot, $dibayar, post('keterangan') ?: null]);
        $gid = (int)db()->lastInsertId();
        q('UPDATE absensi SET gaji_id = ? WHERE pekerja_id = ? AND gaji_id IS NULL AND tanggal <= ?', [$gid, $g['id'], $sampai]);
        if ($pot > 0) {
            q("INSERT INTO kasbon (pekerja_id, tanggal, jenis, jumlah, keterangan, gaji_id) VALUES (?,?,'potong',?,?,?)",
              [$g['id'], $tgl_bayar, $pot, 'Potong gaji', $gid]);
        }
        db()->commit();
        flash('Gaji ' . $g['nama'] . ' dibayar ' . rp($dibayar) . '.');
        redirect('slip.php?id=' . $gid);
    }
    if ($aksi === 'hapus') {
        $id = (int)post('id');
        db()->beginTransaction();
        q('UPDATE absensi SET gaji_id = NULL WHERE gaji_id = ?', [$id]);
        q('DELETE FROM kasbon WHERE gaji_id = ?', [$id]);
        q('DELETE FROM gaji WHERE id = ?', [$id]);
        db()->commit();
        flash('Pembayaran dibatalkan. Absensi kembali berstatus belum dibayar.');
        redirect('gaji.php');
    }
}

$pid = (int)($_GET['pekerja'] ?? 0);
$sampai = get_date('sampai', date('Y-m-d'));

require 'inc/header.php';

// ================= Form pembayaran satu pekerja =================
if ($pid && ($g = hitung_gaji($pid, $sampai))):
    $saran_pot = min(max(0, $g['kasbon']), $g['kotor']);
?>
<a href="gaji.php" class="small d-inline-block mb-2"><i class="bi bi-arrow-left"></i> Kembali</a>
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between">
      <div><div class="fw-bold fs-5"><?= e($g['nama']) ?></div><div class="small-muted">Peran utama: <?= $g['jenis'] ?></div></div>
      <form method="get" class="text-end">
        <input type="hidden" name="pekerja" value="<?= $pid ?>">
        <label class="small-muted d-block">Absen s/d</label>
        <input type="date" name="sampai" value="<?= $sampai ?>" class="form-control form-control-sm" onchange="this.form.submit()">
      </form>
    </div>
  </div>
  <?php if ($g['absen']): ?>
  <div class="table-responsive">
    <table class="table table-sm mb-0 small">
      <thead class="table-light"><tr><th class="ps-3">Tanggal</th><th>Peran</th><th>Status</th><th>Lembur</th><th class="text-end pe-3">Upah</th></tr></thead>
      <tbody>
      <?php foreach ($g['absen'] as $a): ?>
        <tr>
          <td class="ps-3 text-nowrap"><?= tgl($a['tanggal'], true) ?></td>
          <td><span class="badge badge-<?= $a['peran'] ?> fw-normal"><?= ucfirst($a['peran']) ?></span></td>
          <td class="st-<?= $a['status'] ?>"><?= ['H' => 'Hadir', 'S' => '½ hari', 'A' => 'Absen'][$a['status']] ?></td>
          <td><?= $a['lembur_jam'] > 0 ? num($a['lembur_jam']) . ' jam' : '' ?></td>
          <td class="text-end pe-3 money"><?= number_format($a['nilai'] * $a['upah_harian'] + $a['lembur_jam'] * $a['upah_lembur'], 0, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="px-3 pb-3 small-muted">Tidak ada absensi yang belum dibayar sampai tanggal ini.</div>
  <?php endif; ?>
</div>

<form method="post" id="form-gaji" class="card mb-3" data-confirm="Bayar gaji <?= e($g['nama']) ?> sekarang?" data-confirm-detail="#bersih" data-confirm-ok="Ya, bayar" data-kotor="<?= $g['kotor'] ?>">
  <div class="card-body">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="bayar">
    <input type="hidden" name="pekerja_id" value="<?= $pid ?>">
    <input type="hidden" name="sampai" value="<?= $sampai ?>">
    <?php foreach ($g['rincian']['baris'] as [$lbl, $jml]): ?>
      <div class="d-flex justify-content-between py-1 gap-2"><span><?= e($lbl) ?></span><span class="money"><?= rp($jml) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$g['rincian']['baris']): ?><div class="small-muted py-1">Belum ada hari kerja.</div><?php endif; ?>
    <div class="row g-2 my-1">
      <div class="col-6">
        <label class="form-label small mb-1">Tambahan / borongan</label>
        <input name="bonus" class="form-control uang" inputmode="numeric" placeholder="0">
      </div>
      <div class="col-6">
        <label class="form-label small mb-1">Potong kasbon <?= $g['kasbon'] > 0 ? '<span class="text-danger">(saldo ' . rp($g['kasbon']) . ')</span>' : '' ?></label>
        <input name="potongan" class="form-control uang" inputmode="numeric" value="<?= $saran_pot ?: '' ?>" placeholder="0" <?= $g['kasbon'] > 0 ? '' : 'readonly' ?>>
      </div>
    </div>
    <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
      <span class="fw-semibold">Uang diterima</span><span class="fs-4 fw-bold money text-success" id="bersih"></span>
    </div>
    <div class="row g-2 mt-1">
      <div class="col-5"><input type="date" name="tanggal_bayar" value="<?= date('Y-m-d') ?>" class="form-control"></div>
      <div class="col-7"><input name="keterangan" class="form-control" placeholder="Keterangan"></div>
    </div>
    <button class="btn btn-primary btn-lg w-100 mt-3"><i class="bi bi-cash-coin"></i> Bayar &amp; Buat Slip</button>
  </div>
</form>

<?php
// ================= Ringkasan semua pekerja =================
else:
    $belum = rows('SELECT p.id, p.nama, p.jenis,
                     COALESCE(SUM(' . SQL_HARI . '),0) hari, COALESCE(SUM(a.lembur_jam),0) jam, MIN(a.tanggal) mulai,
                     COALESCE(SUM(' . SQL_UPAH . '),0) est,
                     COALESCE(SUM(CASE WHEN a.peran = \'tukang\' THEN ' . SQL_HARI . ' END),0) h_tukang,
                     COALESCE(SUM(CASE WHEN a.peran = \'pelayan\' THEN ' . SQL_HARI . ' END),0) h_pelayan,
                     (SELECT COALESCE(SUM(' . SQL_KASBON . '),0) FROM kasbon k WHERE k.pekerja_id = p.id) kasbon
                   FROM pekerja p LEFT JOIN absensi a ON a.pekerja_id = p.id AND a.gaji_id IS NULL
                   WHERE p.aktif = 1 OR a.id IS NOT NULL
                   GROUP BY p.id, p.nama, p.jenis ORDER BY p.jenis, p.nama');
    $riwayat = rows('SELECT g.*, p.nama, p.jenis FROM gaji g JOIN pekerja p ON p.id = g.pekerja_id ORDER BY g.tanggal_bayar DESC, g.id DESC LIMIT 100');
    $total_belum = array_sum(array_column($belum, 'est'));
?>
<div class="card mb-3">
  <div class="card-header bg-white d-flex justify-content-between">
    <span class="fw-semibold">Upah belum dibayar</span><span class="fw-bold money"><?= rp($total_belum) ?></span>
  </div>
  <?php if (!$belum): ?><div class="p-3 small-muted">Belum ada pekerja.</div><?php endif; ?>
  <?php foreach ($belum as $b): $est = $b['est']; ?>
    <div class="tx">
      <div class="tx-main">
        <div class="tx-title"><?= e($b['nama']) ?></div>
        <div class="small-muted"><?= hari_peran($b['h_tukang'], $b['h_pelayan']) ?><?= $b['jam'] > 0 ? ' + ' . num($b['jam']) . ' jam lembur' : '' ?>
          <?= $b['mulai'] ? ' · sejak ' . tgl($b['mulai']) : '' ?>
          <?= $b['kasbon'] > 0 ? ' · <span class="text-danger">kasbon ' . rp($b['kasbon']) . '</span>' : '' ?></div>
      </div>
      <div class="text-end">
        <div class="money fw-semibold"><?= rp($est) ?></div>
        <a href="?pekerja=<?= $b['id'] ?>" class="btn btn-sm btn-primary mt-1">Bayar</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header bg-white fw-semibold">Riwayat pembayaran</div>
  <?php if (!$riwayat): ?><div class="p-3 small-muted">Belum ada pembayaran.</div><?php endif; ?>
  <?php foreach ($riwayat as $r): ?>
    <div class="tx">
      <div class="tx-main">
        <div class="tx-title"><?= e($r['nama']) ?></div>
        <div class="small-muted"><?= tgl($r['tanggal_bayar']) ?> · <?= hari_peran($r['hari_tukang'], $r['hari_pelayan']) ?>
          <?= $r['potongan_kasbon'] > 0 ? ' · potong kasbon ' . rp($r['potongan_kasbon']) : '' ?></div>
      </div>
      <div class="text-end">
        <div class="money fw-semibold"><?= rp($r['dibayar']) ?></div>
        <a href="slip.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt"></i></a>
        <?= tombol_hapus('gaji.php', $r['id'], 'Batalkan pembayaran ini? Absensi akan kembali menjadi belum dibayar.', '<i class="bi bi-x-lg"></i>', 'Ya, batalkan') ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require 'inc/footer.php'; ?>
