<?php
require 'inc/init.php';
$title = 'Dashboard';
$active = 'index';

// Warna seri (urutan tetap, aman untuk buta warna) — dipakai di kartu, grafik & legenda
const KAT = [
    'Bahan baku'    => '#2a78d6',
    'Honor tukang'  => '#eb6834',
    'Honor pelayan' => '#1baf7a',
    'Lain-lain'     => '#eda100',
];

// ================= Angka keseluruhan proyek =================
$bahan       = (float)val('SELECT COALESCE(SUM(total),0) FROM bahan');
$upah_tukang = (float)val('SELECT COALESCE(SUM(biaya_tukang),0) FROM gaji');
$upah_pelayan= (float)val('SELECT COALESCE(SUM(biaya_pelayan),0) FROM gaji');
$lain        = (float)val('SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran_lain');
$total_biaya = $bahan + $upah_tukang + $upah_pelayan + $lain;

$hutang_toko = (float)val("SELECT COALESCE((SELECT SUM(total) FROM bahan WHERE status='bon'),0) - COALESCE((SELECT SUM(jumlah) FROM bayar_toko),0)");
$kasbon      = (float)val('SELECT COALESCE(SUM(' . SQL_KASBON . '),0) FROM kasbon k');
$upah_terutang = (float)val('SELECT COALESCE(SUM(' . SQL_UPAH . '),0) FROM absensi a WHERE a.gaji_id IS NULL');

$anggaran = (float)setting('anggaran', 0);
$persen = $anggaran > 0 ? min(100, $total_biaya / $anggaran * 100) : 0;
$proyeksi = $total_biaya + $upah_terutang;

$hari_ini = date('Y-m-d');
$hadir = row("SELECT SUM(status='H') h, SUM(status='S') s, COUNT(*) n FROM absensi WHERE tanggal = ?", [$hari_ini]);
$jml_pekerja = (int)val('SELECT COUNT(*) FROM pekerja WHERE aktif = 1');

// ================= Periode grafik =================
$mulai_proyek = val('SELECT MIN(t) FROM (SELECT MIN(tanggal) t FROM bahan UNION ALL SELECT MIN(tanggal_bayar) FROM gaji
                     UNION ALL SELECT MIN(tanggal) FROM pengeluaran_lain UNION ALL SELECT MIN(tanggal) FROM absensi) x') ?: $hari_ini;
$periode_opsi = ['7' => '7 hari', '30' => '30 hari', 'bulan' => 'Bulan ini', 'semua' => 'Semua'];
$p = array_key_exists($_GET['p'] ?? '', $periode_opsi) ? $_GET['p'] : '30';
$sampai = $hari_ini;
$dari = match ($p) {
    '7' => date('Y-m-d', strtotime('-6 days')),
    '30' => date('Y-m-d', strtotime('-29 days')),
    'bulan' => date('Y-m-01'),
    default => min($mulai_proyek, $hari_ini),
};
$span = (int)round((strtotime($sampai) - strtotime($dari)) / 86400) + 1;
$satuan = $span <= 31 ? 'hari' : ($span <= 182 ? 'minggu' : 'bulan');

$bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
/** Kunci kelompok waktu untuk sebuah tanggal */
$kunci = function (string $d) use ($satuan): string {
    return match ($satuan) {
        'hari' => $d,
        'minggu' => date('Y-m-d', strtotime('monday this week', strtotime($d))),
        default => substr($d, 0, 7) . '-01',
    };
};
$fmt_waktu = function (string $k) use ($satuan, $bln): string {
    $t = strtotime($k);
    return match ($satuan) {
        'hari' => date('j', $t) . ' ' . $bln[(int)date('n', $t)],
        'minggu' => 'Mg ' . date('j', $t) . ' ' . $bln[(int)date('n', $t)],
        default => $bln[(int)date('n', $t)] . ' ' . date('y', $t),
    };
};

// Siapkan semua kelompok waktu (termasuk yang kosong agar sumbu tidak lompat)
$buckets = [];
for ($t = strtotime($dari); $t <= strtotime($sampai); $t += 86400) {
    $buckets[$kunci(date('Y-m-d', $t))] = array_fill_keys(array_keys(KAT), 0);
}

$tx = rows("
  SELECT tanggal, 'Bahan baku' kategori, total jumlah FROM bahan WHERE tanggal BETWEEN ? AND ?
  UNION ALL
  SELECT tanggal_bayar, 'Honor tukang', biaya_tukang FROM gaji WHERE biaya_tukang <> 0 AND tanggal_bayar BETWEEN ? AND ?
  UNION ALL
  SELECT tanggal_bayar, 'Honor pelayan', biaya_pelayan FROM gaji WHERE biaya_pelayan <> 0 AND tanggal_bayar BETWEEN ? AND ?
  UNION ALL
  SELECT tanggal, 'Lain-lain', jumlah FROM pengeluaran_lain WHERE tanggal BETWEEN ? AND ?",
  [$dari, $sampai, $dari, $sampai, $dari, $sampai, $dari, $sampai]);
$per_kat = array_fill_keys(array_keys(KAT), 0);
foreach ($tx as $r) {
    $k = $kunci($r['tanggal']);
    if (isset($buckets[$k])) $buckets[$k][$r['kategori']] += (float)$r['jumlah'];
    $per_kat[$r['kategori']] += (float)$r['jumlah'];
}
$total_periode = array_sum($per_kat);

// Biaya kumulatif (mulai dari total sebelum periode)
$sebelum = (float)val("SELECT
   COALESCE((SELECT SUM(total) FROM bahan WHERE tanggal < ?),0)
 + COALESCE((SELECT SUM(biaya_tukang+biaya_pelayan) FROM gaji WHERE tanggal_bayar < ?),0)
 + COALESCE((SELECT SUM(jumlah) FROM pengeluaran_lain WHERE tanggal < ?),0)", [$dari, $dari, $dari]);
$kumulatif = [];
$jalan = $sebelum;
foreach ($buckets as $v) { $jalan += array_sum($v); $kumulatif[] = round($jalan); }

// Kehadiran per hari (maks. 31 hari terakhir dari periode)
$dari_absen = max($dari, date('Y-m-d', strtotime("$sampai -30 days")));
$absen_rows = q('SELECT a.tanggal, SUM(' . SQL_HARI . ') hari, SUM(a.status <> \'A\') orang
                 FROM absensi a WHERE a.tanggal BETWEEN ? AND ? GROUP BY a.tanggal', [$dari_absen, $sampai])->fetchAll(PDO::FETCH_UNIQUE);
$absen_lbl = []; $absen_val = []; $absen_org = [];
for ($t = strtotime($dari_absen); $t <= strtotime($sampai); $t += 86400) {
    $d = date('Y-m-d', $t);
    $absen_lbl[] = date('j', $t) . ' ' . $bln[(int)date('n', $t)];
    $absen_val[] = (float)($absen_rows[$d]['hari'] ?? 0);
    $absen_org[] = (int)($absen_rows[$d]['orang'] ?? 0);
}
$hari_terisi = count(array_filter($absen_val));
$rata_hadir = $hari_terisi ? array_sum($absen_val) / $hari_terisi : 0;

// Bahan terbesar dalam periode
$top_bahan = rows('SELECT nama_barang, SUM(total) jml FROM bahan WHERE tanggal BETWEEN ? AND ? GROUP BY nama_barang ORDER BY jml DESC LIMIT 5', [$dari, $sampai]);
$max_bahan = $top_bahan ? max(array_column($top_bahan, 'jml')) : 0;

$terbaru = rows("
  (SELECT tanggal, CONCAT(nama_barang, ' (', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM qty)), ' ', COALESCE(satuan,''), ')') judul, total jumlah, IF(status='bon','Bon bahan','Bahan') jenis, id, created_at FROM bahan)
  UNION ALL
  (SELECT tanggal, COALESCE(NULLIF(keterangan,''), kategori), jumlah, kategori, id, NULL FROM pengeluaran_lain)
  UNION ALL
  (SELECT g.tanggal_bayar, CONCAT('Gaji ', p.nama), g.dibayar, 'Gaji', g.id, g.created_at FROM gaji g JOIN pekerja p ON p.id=g.pekerja_id)
  UNION ALL
  (SELECT k.tanggal, CONCAT('Kasbon ', p.nama), k.jumlah, 'Kasbon', k.id, k.created_at FROM kasbon k JOIN pekerja p ON p.id=k.pekerja_id WHERE k.jenis='pinjam')
  ORDER BY tanggal DESC, created_at DESC LIMIT 6");

// Data untuk grafik (dibaca assets/dashboard.js)
$chart = [
    'labels'    => array_map($fmt_waktu, array_keys($buckets)),
    'series'    => array_map(fn($n, $c) => ['name' => $n, 'color' => $c, 'data' => array_column($buckets, $n)], array_keys(KAT), KAT),
    'kumulatif' => $kumulatif,
    'anggaran'  => $anggaran,
    'absen'     => ['labels' => $absen_lbl, 'hari' => $absen_val, 'orang' => $absen_org],
];
$extra_js = '<script src="assets/vendor/chart.umd.min.js"></script>'
          . '<script>window.DASH = ' . json_encode($chart, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) . ';</script>'
          . '<script src="assets/dashboard.js"></script>';

require 'inc/header.php';
?>

<!-- ===== Ringkasan anggaran ===== -->
<div class="card mb-3 text-white" style="background:linear-gradient(135deg,#b45309,#78350f)">
  <div class="card-body">
    <div class="small text-white-50 text-uppercase">Total biaya rehab</div>
    <div class="fs-2 fw-bold"><?= rp($total_biaya) ?></div>
    <?php if ($anggaran > 0): ?>
      <div class="progress mt-2 bg-white bg-opacity-25" style="height:8px" role="progressbar" aria-valuenow="<?= round($persen) ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar <?= $persen >= 90 ? 'bg-danger' : 'bg-warning' ?>" style="width:<?= round($persen) ?>%"></div>
      </div>
      <div class="d-flex justify-content-between small mt-1 text-white-50">
        <span><?= round($persen) ?>% dari <?= rp($anggaran) ?></span>
        <span>Sisa <b class="text-white"><?= rp($anggaran - $total_biaya) ?></b></span>
      </div>
    <?php else: ?>
      <div class="small text-white-50 mt-1"><a href="pengaturan.php" class="text-white">Atur anggaran</a> untuk melihat sisa dana.</div>
    <?php endif; ?>
    <?php if ($upah_terutang > 0): ?>
      <div class="small mt-2 text-white-50">Jika upah yang belum dibayar dilunasi: <b class="text-white"><?= rp($proyeksi) ?></b></div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-2 mb-3">
  <?php foreach ([
      ['Bahan baku', $bahan, 'bricks', 'bahan.php'],
      ['Honor tukang', $upah_tukang, 'person-gear', 'gaji.php'],
      ['Honor pelayan', $upah_pelayan, 'person', 'gaji.php'],
      ['Lain-lain', $lain, 'receipt', 'lain.php'],
  ] as [$l, $v, $i, $u]): ?>
  <div class="col-6 col-md-3">
    <a href="<?= $u ?>" class="card stat p-3 h-100 text-decoration-none text-body">
      <div class="d-flex justify-content-between align-items-center">
        <span class="label"><span class="dot" style="background:<?= KAT[$l] ?>"></span><?= $l ?></span>
        <i class="bi bi-<?= $i ?> text-muted"></i>
      </div>
      <div class="value"><?= rp($v) ?></div>
      <div class="small-muted"><?= $total_biaya ? round($v / $total_biaya * 100) : 0 ?>% dari total</div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-2 mb-3">
  <div class="col-4"><a href="toko.php" class="card stat p-2 h-100 text-decoration-none text-body text-center">
    <span class="label">Hutang toko</span><span class="fw-bold <?= $hutang_toko > 0 ? 'text-danger' : '' ?> small"><?= rp($hutang_toko) ?></span></a></div>
  <div class="col-4"><a href="gaji.php" class="card stat p-2 h-100 text-decoration-none text-body text-center">
    <span class="label">Upah terutang</span><span class="fw-bold small"><?= rp($upah_terutang) ?></span></a></div>
  <div class="col-4"><a href="kasbon.php" class="card stat p-2 h-100 text-decoration-none text-body text-center">
    <span class="label">Kasbon</span><span class="fw-bold small"><?= rp($kasbon) ?></span></a></div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6"><a href="absensi.php" class="btn btn-primary w-100 py-2"><i class="bi bi-calendar-check"></i> Absen hari ini
    <div class="small opacity-75"><?= (int)$hadir['n'] ? ((int)$hadir['h'] . ' hadir, ' . (int)$hadir['s'] . ' ½ hari') : "0/$jml_pekerja terisi" ?></div></a></div>
  <div class="col-6"><a href="bahan.php?form=1" class="btn btn-outline-primary bg-white w-100 py-2"><i class="bi bi-cart-plus"></i> Catat belanja
    <div class="small opacity-75">bahan baku</div></a></div>
</div>

<!-- ===== Filter periode (berlaku untuk semua grafik di bawah) ===== -->
<div class="d-flex align-items-center gap-2 mb-2 sticky-filter">
  <div class="btn-group btn-group-sm flex-fill" role="group" aria-label="Periode">
    <?php foreach ($periode_opsi as $k => $l): ?>
      <a href="?p=<?= $k ?>" class="btn <?= $p === (string)$k ? 'btn-primary' : 'btn-outline-primary bg-white' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="small-muted mb-2"><?= tgl($dari) ?> – <?= tgl($sampai) ?> · biaya periode ini <b class="text-body"><?= rp($total_periode) ?></b></div>

<!-- ===== Grafik pengeluaran per waktu ===== -->
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="fw-semibold">Pengeluaran per <?= $satuan ?></div>
        <div class="small-muted">Bahan, honor &amp; lain-lain</div>
      </div>
      <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#tbl-pengeluaran"><i class="bi bi-table"></i> Tabel</button>
    </div>
    <?php if (!$total_periode): ?><div class="alert alert-light small py-2 my-2 mb-0">Belum ada pengeluaran di periode ini.</div><?php endif; ?>
    <div class="legend my-2">
      <?php foreach (KAT as $n => $c): ?><span><span class="dot" style="background:<?= $c ?>"></span><?= $n ?></span><?php endforeach; ?>
    </div>
    <div class="chart-box"><canvas id="ch-pengeluaran" aria-label="Grafik pengeluaran per <?= $satuan ?>" role="img"></canvas></div>
    <div class="collapse mt-2" id="tbl-pengeluaran">
      <div class="table-responsive" style="max-height:320px">
        <table class="table table-sm small mb-0">
          <thead class="table-light sticky-top"><tr><th><?= ucfirst($satuan) ?></th><?php foreach (KAT as $n => $c): ?><th class="text-end"><?= explode(' ', $n)[0] === 'Honor' ? ucfirst(explode(' ', $n)[1]) : explode(' ', $n)[0] ?></th><?php endforeach; ?><th class="text-end">Total</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($buckets, true) as $k => $v): if (!array_sum($v)) continue; ?>
            <tr><td class="text-nowrap"><?= $fmt_waktu($k) ?></td>
              <?php foreach ($v as $x): ?><td class="text-end money"><?= $x ? number_format($x, 0, ',', '.') : '–' ?></td><?php endforeach; ?>
              <td class="text-end money fw-semibold"><?= number_format(array_sum($v), 0, ',', '.') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <!-- Komposisi biaya -->
  <div class="col-md-6">
    <div class="card h-100"><div class="card-body">
      <div class="fw-semibold mb-2">Komposisi biaya periode ini</div>
      <?php foreach ($per_kat as $n => $v): $pct = $total_periode ? $v / $total_periode * 100 : 0; ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small"><span><span class="dot" style="background:<?= KAT[$n] ?>"></span><?= $n ?></span>
            <span class="money"><?= rp($v) ?> <span class="text-muted"><?= round($pct) ?>%</span></span></div>
          <div class="hbar"><div style="width:<?= $pct ?>%;background:<?= KAT[$n] ?>"></div></div>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <!-- Bahan terbesar -->
  <div class="col-md-6">
    <div class="card h-100"><div class="card-body">
      <div class="fw-semibold mb-2">5 bahan terbesar</div>
      <?php if (!$top_bahan): ?><div class="small-muted">Belum ada belanja bahan di periode ini.</div><?php endif; ?>
      <?php foreach ($top_bahan as $b): ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small gap-2"><span class="text-truncate"><?= e($b['nama_barang']) ?></span><span class="money"><?= rp($b['jml']) ?></span></div>
          <div class="hbar"><div style="width:<?= $max_bahan ? $b['jml'] / $max_bahan * 100 : 0 ?>%;background:<?= KAT['Bahan baku'] ?>"></div></div>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>

<!-- ===== Biaya kumulatif vs anggaran ===== -->
<div class="card mb-3">
  <div class="card-body">
    <div class="fw-semibold">Biaya berjalan<?= $anggaran > 0 ? ' vs anggaran' : '' ?></div>
    <div class="small-muted mb-2">Total biaya sejak awal proyek sampai tiap <?= $satuan ?></div>
    <div class="chart-box"><canvas id="ch-kumulatif" aria-label="Grafik biaya kumulatif" role="img"></canvas></div>
  </div>
</div>

<!-- ===== Kehadiran ===== -->
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="fw-semibold">Kehadiran pekerja</div>
        <div class="small-muted">Hari-orang per hari (½ hari = 0,5)</div>
      </div>
      <div class="text-end"><div class="fw-bold fs-5"><?= num(round($rata_hadir, 1)) ?></div><div class="small-muted">rata-rata/hari kerja</div></div>
    </div>
    <div class="chart-box chart-sm mt-2"><canvas id="ch-absen" aria-label="Grafik kehadiran" role="img"></canvas></div>
    <a href="rekap_absensi.php" class="small">Lihat rekap absensi lengkap <i class="bi bi-arrow-right"></i></a>
  </div>
</div>

<!-- ===== Transaksi terakhir ===== -->
<div class="card mb-3">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between">Transaksi terakhir <a href="laporan.php" class="small fw-normal">Laporan <i class="bi bi-arrow-right"></i></a></div>
  <?php if (!$terbaru): ?><div class="p-3 small-muted">Belum ada transaksi.</div><?php endif; ?>
  <?php foreach ($terbaru as $t): ?>
    <div class="tx">
      <div class="tx-main">
        <div class="tx-title"><?= e($t['judul']) ?></div>
        <div class="small-muted"><?= tgl($t['tanggal']) ?> · <?= e($t['jenis']) ?></div>
      </div>
      <div class="money fw-semibold"><?= rp($t['jumlah']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php require 'inc/footer.php'; ?>
