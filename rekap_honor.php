<?php
require 'inc/init.php';
$title = 'Rekap Honor per Tanggal';
$active = 'gaji';

$sampai = get_date('sampai', date('Y-m-d'));
$dari   = get_date('dari', date('Y-m-d', strtotime("$sampai -6 days")));
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
$peran = in_array($_GET['peran'] ?? '', ['tukang', 'pelayan'], true) ? $_GET['peran'] : '';

// Honor per orang per hari, memakai tarif yang tercatat hari itu. Hari absen tanpa lembur tidak ditampilkan.
$baris = rows("SELECT a.tanggal, a.status, a.peran, a.lembur_jam, a.gaji_id, p.nama, " . SQL_HARI . " hari, " . SQL_UPAH . " upah
               FROM absensi a JOIN pekerja p ON p.id = a.pekerja_id
               WHERE a.tanggal BETWEEN ? AND ? AND (a.status <> 'A' OR a.lembur_jam > 0)" . ($peran ? ' AND a.peran = ?' : '') . "
               ORDER BY a.tanggal DESC, a.peran DESC, p.nama", $peran ? [$dari, $sampai, $peran] : [$dari, $sampai]);

$per_tgl = [];
$jml = ['tukang' => 0, 'pelayan' => 0, 'hari_tukang' => 0, 'hari_pelayan' => 0, 'dibayar' => 0];
foreach ($baris as $b) {
    $d = &$per_tgl[$b['tanggal']];
    $d ??= ['orang' => [], 'tukang' => 0, 'pelayan' => 0, 'hari_tukang' => 0, 'hari_pelayan' => 0, 'jam' => 0, 'dibayar' => 0];
    $d['orang'][] = $b;
    $d[$b['peran']] += $b['upah'];
    $d['hari_' . $b['peran']] += $b['hari'];
    $d['jam'] += $b['lembur_jam'];
    if ($b['gaji_id']) $d['dibayar'] += $b['upah'];
    $jml[$b['peran']] += $b['upah'];
    $jml['hari_' . $b['peran']] += $b['hari'];
    if ($b['gaji_id']) $jml['dibayar'] += $b['upah'];
}
unset($d);
$total = $jml['tukang'] + $jml['pelayan'];

// Pilihan periode cepat
$hari_ini = date('Y-m-d');
$opsi = [
    '7 hari'     => [date('Y-m-d', strtotime('-6 days')), $hari_ini],
    'Minggu ini' => [date('Y-m-d', strtotime('monday this week')), $hari_ini],
    'Bulan ini'  => [date('Y-m-01'), date('Y-m-t')],
    'Bulan lalu' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
    'Semua'      => [val('SELECT MIN(tanggal) FROM absensi') ?: $hari_ini, $hari_ini],
];
$url = fn(array $ubah) => '?' . http_build_query(array_merge(['dari' => $dari, 'sampai' => $sampai, 'peran' => $peran], $ubah));

$header_action = '<button onclick="window.print()" class="btn btn-light btn-sm d-print-none"><i class="bi bi-printer"></i></button>';
require 'inc/header.php';
?>
<form method="get" class="card mb-3 d-print-none">
  <div class="card-body">
    <div class="d-flex gap-1 mb-2 flex-wrap">
      <?php foreach ($opsi as $label => [$d1, $d2]): ?>
        <a href="<?= e($url(['dari' => $d1, 'sampai' => $d2])) ?>" class="btn btn-sm <?= [$dari, $sampai] === [$d1, $d2] ? 'btn-primary' : 'btn-light' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="peran" value="<?= $peran ?>">
    <div class="row g-2">
      <div class="col-5"><input type="date" name="dari" value="<?= $dari ?>" class="form-control form-control-sm" aria-label="Dari tanggal"></div>
      <div class="col-5"><input type="date" name="sampai" value="<?= $sampai ?>" class="form-control form-control-sm" aria-label="Sampai tanggal"></div>
      <div class="col-2"><button class="btn btn-primary btn-sm w-100" aria-label="Tampilkan"><i class="bi bi-search"></i></button></div>
    </div>
  </div>
</form>

<div class="btn-group btn-group-sm w-100 mb-3 d-print-none" role="group" aria-label="Peran">
  <?php foreach (['' => 'Semua', 'tukang' => 'Tukang', 'pelayan' => 'Pelayan'] as $k => $l): ?>
    <a href="<?= e($url(['peran' => $k])) ?>" class="btn <?= $peran === $k ? 'btn-primary' : 'btn-outline-primary bg-white' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<div class="card hero mb-3">
  <div class="card-body">
    <div class="small text-white-50 text-uppercase">Total honor<?= $peran ? ' ' . $peran : '' ?></div>
    <div class="fs-2 fw-bold money"><?= rp($total) ?></div>
    <div class="small text-white-50"><?= tgl($dari) ?> – <?= tgl($sampai) ?> · <?= count($per_tgl) ?> hari kerja · <?= hari_peran($jml['hari_tukang'], $jml['hari_pelayan']) ?></div>
  </div>
</div>

<div class="row g-2 mb-3">
  <?php if (!$peran): ?>
  <div class="col-6"><div class="card stat p-3 h-100">
    <span class="label"><span class="dot" style="background:<?= KAT['Honor tukang'] ?>"></span>Honor tukang</span>
    <div class="value money"><?= rp($jml['tukang']) ?></div><div class="small-muted"><?= num($jml['hari_tukang']) ?> hari</div>
  </div></div>
  <div class="col-6"><div class="card stat p-3 h-100">
    <span class="label"><span class="dot" style="background:<?= KAT['Honor pelayan'] ?>"></span>Honor pelayan</span>
    <div class="value money"><?= rp($jml['pelayan']) ?></div><div class="small-muted"><?= num($jml['hari_pelayan']) ?> hari</div>
  </div></div>
  <?php endif; ?>
  <div class="col-6"><div class="card stat p-3 h-100">
    <span class="label"><i class="bi bi-check-circle-fill text-success"></i> Sudah dibayar</span>
    <div class="value money"><?= rp($jml['dibayar']) ?></div>
  </div></div>
  <div class="col-6"><a href="gaji.php" class="card stat p-3 h-100 text-decoration-none text-body">
    <span class="label"><i class="bi bi-clock-history text-danger"></i> Belum dibayar</span>
    <div class="value money <?= $total - $jml['dibayar'] > 0 ? 'text-danger' : '' ?>"><?= rp($total - $jml['dibayar']) ?></div>
  </a></div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Per tanggal</span><span class="small-muted d-print-none">Ketuk tanggal untuk rincian</span>
  </div>
  <?php if (!$per_tgl): ?><div class="p-3 small-muted">Belum ada absensi pada periode ini.</div><?php endif; ?>
  <?php foreach ($per_tgl as $t => $d):
      $jumlah = $d['tukang'] + $d['pelayan'];
      [$status, $kelas] = $d['dibayar'] >= $jumlah ? ['Lunas', 'lunas'] : ($d['dibayar'] > 0 ? ['Sebagian', 'sebagian'] : ['Belum dibayar', 'belum']); ?>
    <details class="rekap-hari">
      <summary class="tx">
        <div class="tx-main">
          <div class="tx-title"><?= tgl($t, true) ?></div>
          <div class="small-muted"><?= count($d['orang']) ?> orang · <?= hari_peran($d['hari_tukang'], $d['hari_pelayan']) ?><?= $d['jam'] > 0 ? ' · lembur ' . num($d['jam']) . ' jam' : '' ?></div>
        </div>
        <div class="text-end">
          <div class="fw-bold money"><?= rp($jumlah) ?></div>
          <span class="badge badge-<?= $kelas ?> fw-normal"><?= $status ?></span>
        </div>
        <i class="bi bi-chevron-down chevron d-print-none"></i>
      </summary>
      <div class="rekap-detail">
        <?php foreach ($d['orang'] as $o):
            $ket = array_filter([['H' => '', 'S' => '½ hari', 'A' => 'absen'][$o['status']], $o['lembur_jam'] > 0 ? 'lembur ' . num($o['lembur_jam']) . ' jam' : '']); ?>
          <div class="orang">
            <div><?= e($o['nama']) ?> <span class="badge badge-<?= $o['peran'] ?> fw-normal"><?= ucfirst($o['peran']) ?></span>
              <?php if ($ket): ?><span class="small-muted"><?= implode(' · ', $ket) ?></span><?php endif; ?></div>
            <div class="text-nowrap"><span class="money"><?= rp($o['upah']) ?></span>
              <?= $o['gaji_id'] ? '<i class="bi bi-check-circle-fill text-success" title="Sudah dibayar"></i>' : '<i class="bi bi-clock text-muted" title="Belum dibayar"></i>' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endforeach; ?>
  <?php if ($per_tgl): ?>
    <div class="d-flex justify-content-between fw-bold px-3 py-2"><span>Total <?= count($per_tgl) ?> hari</span><span class="money"><?= rp($total) ?></span></div>
  <?php endif; ?>
</div>
<div class="small-muted mb-3">Honor dihitung dari absensi memakai tarif yang tercatat pada hari itu (upah harian + lembur).
  Tambahan/borongan yang diberikan saat pembayaran tidak termasuk karena tidak terikat tanggal.
  <i class="bi bi-check-circle-fill text-success"></i> sudah dibayar · <i class="bi bi-clock"></i> belum dibayar.</div>

<a href="rekap_absensi.php?<?= e(http_build_query(['dari' => $dari, 'sampai' => $sampai])) ?>" class="btn btn-light d-print-none"><i class="bi bi-table"></i> Rekap absensi</a>
<?php require 'inc/footer.php'; ?>
