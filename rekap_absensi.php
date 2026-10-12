<?php
require 'inc/init.php';
$title = 'Rekap Absensi';
$active = 'absensi';

$sampai = get_date('sampai', date('Y-m-d'));
$dari   = get_date('dari', date('Y-m-d', strtotime("$sampai -6 days")));
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
if ((strtotime($sampai) - strtotime($dari)) / 86400 > 62) $dari = date('Y-m-d', strtotime("$sampai -62 days"));

$tanggal = [];
for ($t = strtotime($dari); $t <= strtotime($sampai); $t += 86400) $tanggal[] = date('Y-m-d', $t);

$absen = [];
foreach (rows('SELECT pekerja_id, tanggal, status, peran, upah_harian, upah_lembur, lembur_jam FROM absensi WHERE tanggal BETWEEN ? AND ?', [$dari, $sampai]) as $a) {
    $absen[$a['pekerja_id']][$a['tanggal']] = $a;
}
$pekerja = rows('SELECT p.* FROM pekerja p WHERE p.aktif = 1 OR EXISTS (SELECT 1 FROM absensi a WHERE a.pekerja_id = p.id AND a.tanggal BETWEEN ? AND ?) ORDER BY p.jenis, p.nama', [$dari, $sampai]);
$hr = ['M', 'S', 'S', 'R', 'K', 'J', 'S'];

require 'inc/header.php';
?>
<form method="get" class="card mb-3 d-print-none">
  <div class="card-body row g-2 align-items-end">
    <div class="col-5"><label class="form-label small mb-1">Dari</label><input type="date" name="dari" value="<?= $dari ?>" class="form-control form-control-sm"></div>
    <div class="col-5"><label class="form-label small mb-1">Sampai</label><input type="date" name="sampai" value="<?= $sampai ?>" class="form-control form-control-sm"></div>
    <div class="col-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button></div>
  </div>
</form>

<div class="card mb-3">
  <div class="card-body p-2">
    <div class="small-muted mb-2"><?= tgl($dari) ?> – <?= tgl($sampai) ?></div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered rekap-table mb-0">
        <thead><tr>
          <th>Nama</th>
          <?php foreach ($tanggal as $t): ?><th><?= date('j', strtotime($t)) ?><br><span class="fw-normal text-muted"><?= $hr[date('w', strtotime($t))] ?></span></th><?php endforeach; ?>
          <th>Hari</th><th>Lbr</th><th>Upah</th>
        </tr></thead>
        <tbody>
        <?php $grand = 0; foreach ($pekerja as $p):
            $hari = 0; $jam = 0; $upah = 0; ?>
          <tr>
            <td><?= e($p['nama']) ?></td>
            <?php foreach ($tanggal as $t): $a = $absen[$p['id']][$t] ?? null;
                if ($a) { $n = ['H' => 1, 'S' => .5, 'A' => 0][$a['status']]; $hari += $n; $jam += $a['lembur_jam'];
                          $upah += $n * $a['upah_harian'] + $a['lembur_jam'] * $a['upah_lembur']; }
                $kode = $a ? ($a['status'] === 'A' ? '✗' : ($a['status'] === 'S' ? '½' : '') . ($a['peran'] === 'pelayan' ? 'P' : 'T')) : ''; ?>
              <td class="st-<?= $a['status'] ?? '' ?> <?= $a && $a['status'] !== 'A' ? 'pr-' . $a['peran'] : '' ?>"><?= $kode ?><?= $a && $a['lembur_jam'] > 0 ? '<sup>+' . num($a['lembur_jam']) . '</sup>' : '' ?></td>
            <?php endforeach;
            $grand += $upah; ?>
            <td class="fw-bold"><?= num($hari) ?></td>
            <td><?= num($jam) ?></td>
            <td class="money text-end"><?= number_format($upah, 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="<?= count($tanggal) + 3 ?>" class="text-end">Total upah</th><th class="money text-end"><?= number_format($grand, 0, ',', '.') ?></th></tr></tfoot>
      </table>
    </div>
    <div class="small-muted mt-2">T = tukang · P = pelayan · ½ = setengah hari · ✗ absen · angka kecil = jam lembur. Upah memakai tarif yang tercatat per hari.</div>
  </div>
</div>
<button onclick="window.print()" class="btn btn-light d-print-none"><i class="bi bi-printer"></i> Cetak</button>
<?php require 'inc/footer.php'; ?>
