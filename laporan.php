<?php
require 'inc/init.php';
$title = 'Laporan';
$active = 'laporan';

$dari   = get_date('dari', '2000-01-01');
$sampai = get_date('sampai', date('Y-m-d'));
$P = [$dari, $sampai];

// Semua transaksi biaya dalam periode (dasar laporan & export)
$sql_tx = "
  SELECT b.tanggal, 'Bahan baku' kategori, CONCAT(b.nama_barang, ' ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM b.qty)), ' ', COALESCE(b.satuan,'')) uraian,
         COALESCE(t.nama,'') pihak, b.total jumlah, IF(b.status='bon','Bon','Tunai') ket
    FROM bahan b LEFT JOIN toko t ON t.id = b.toko_id WHERE b.tanggal BETWEEN ? AND ?
  UNION ALL
  SELECT g.tanggal_bayar, 'Honor tukang',
         CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM g.hari_tukang)), ' hari sebagai tukang'),
         p.nama, g.biaya_tukang, IF(g.potongan_kasbon>0, CONCAT('Potong kasbon ', g.potongan_kasbon+0), '')
    FROM gaji g JOIN pekerja p ON p.id = g.pekerja_id WHERE g.biaya_tukang <> 0 AND g.tanggal_bayar BETWEEN ? AND ?
  UNION ALL
  SELECT g.tanggal_bayar, 'Honor pelayan',
         CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM g.hari_pelayan)), ' hari sebagai pelayan'),
         p.nama, g.biaya_pelayan, IF(g.potongan_kasbon>0 AND g.biaya_tukang = 0, CONCAT('Potong kasbon ', g.potongan_kasbon+0), '')
    FROM gaji g JOIN pekerja p ON p.id = g.pekerja_id WHERE g.biaya_pelayan <> 0 AND g.tanggal_bayar BETWEEN ? AND ?
  UNION ALL
  SELECT l.tanggal, 'Lain-lain', COALESCE(l.keterangan,''), l.kategori, l.jumlah, '' FROM pengeluaran_lain l WHERE l.tanggal BETWEEN ? AND ?
  ORDER BY tanggal, kategori";
$tx = rows($sql_tx, [...$P, ...$P, ...$P, ...$P]);

// ---- Export CSV (buka di Excel) ----
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-rehab-' . $dari . '_' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['Tanggal', 'Kategori', 'Uraian', 'Toko/Pekerja', 'Jumlah', 'Keterangan'], ';');
    foreach ($tx as $t) fputcsv($out, [$t['tanggal'], $t['kategori'], $t['uraian'], $t['pihak'], (int)$t['jumlah'], $t['ket']], ';');
    exit;
}

$kat = ['Bahan baku' => 0, 'Honor tukang' => 0, 'Honor pelayan' => 0, 'Lain-lain' => 0];
$bulanan = [];
foreach ($tx as $t) {
    $kat[$t['kategori']] += $t['jumlah'];
    $m = substr($t['tanggal'], 0, 7);
    $bulanan[$m][$t['kategori']] = ($bulanan[$m][$t['kategori']] ?? 0) + $t['jumlah'];
}
$total = array_sum($kat);

$per_toko = rows("SELECT COALESCE(t.nama, 'Eceran / tanpa toko') nama, SUM(b.total) jml, SUM(IF(b.status='bon', b.total, 0)) bon
                  FROM bahan b LEFT JOIN toko t ON t.id = b.toko_id WHERE b.tanggal BETWEEN ? AND ? GROUP BY t.id, t.nama ORDER BY jml DESC", $P);
$top_barang = rows('SELECT nama_barang, SUM(qty) qty, MAX(satuan) satuan, SUM(total) jml FROM bahan WHERE tanggal BETWEEN ? AND ?
                    GROUP BY nama_barang ORDER BY jml DESC LIMIT 10', $P);
$per_pekerja = rows('SELECT p.nama, SUM(g.hari_tukang) h_tukang, SUM(g.hari_pelayan) h_pelayan, SUM(g.biaya_tukang + g.biaya_pelayan) jml
                     FROM gaji g JOIN pekerja p ON p.id = g.pekerja_id WHERE g.tanggal_bayar BETWEEN ? AND ?
                     GROUP BY p.id, p.nama ORDER BY jml DESC', $P);

// Arus kas keluar (uang yang benar-benar keluar dari dompet)
$kas = [
    'Belanja bahan tunai'   => (float)val("SELECT COALESCE(SUM(total),0) FROM bahan WHERE status='lunas' AND tanggal BETWEEN ? AND ?", $P),
    'Bayar bon toko'        => (float)val('SELECT COALESCE(SUM(jumlah),0) FROM bayar_toko WHERE tanggal BETWEEN ? AND ?', $P),
    'Gaji diserahkan'       => (float)val('SELECT COALESCE(SUM(dibayar),0) FROM gaji WHERE tanggal_bayar BETWEEN ? AND ?', $P),
    'Kasbon diberikan'      => (float)val("SELECT COALESCE(SUM(jumlah),0) FROM kasbon WHERE jenis='pinjam' AND tanggal BETWEEN ? AND ?", $P),
    'Kasbon kembali tunai'  => -(float)val("SELECT COALESCE(SUM(jumlah),0) FROM kasbon WHERE jenis='potong' AND gaji_id IS NULL AND tanggal BETWEEN ? AND ?", $P),
    'Pengeluaran lain'      => $kat['Lain-lain'],
];

$semua = $dari === '2000-01-01';
$bln_ini = date('Y-m-01');
$bln_lalu = date('Y-m-01', strtotime('first day of last month'));
$akhir_bln_lalu = date('Y-m-t', strtotime('last month'));
$periode = $semua ? 'semua data' : tgl($dari) . ' – ' . tgl($sampai);
ksort($bulanan);

// ---- Unduh PDF ----
if (isset($_GET['pdf'])) {
    require 'inc/pdf.php';
    $ribu = fn($n) => number_format((float)$n, 0, ',', '.');
    $pdf = new PDF('Laporan Pengeluaran', 'Periode: ' . $periode);

    $pdf->bagian('Ringkasan biaya');
    $pdf->tabel([['Kategori', 0], ['Jumlah', 45, 'R'], ['%', 20, 'R']],
        array_map(fn($k, $v) => [$k, rp($v), ($total ? round($v / $total * 100) : 0) . '%'], array_keys($kat), $kat),
        ['Total biaya', rp($total), '']);

    $pdf->bagian('Uang keluar (arus kas)');
    $kas_ada = array_filter($kas);
    $pdf->tabel([['Uraian', 0], ['Jumlah', 45, 'R']],
        array_map(fn($k, $v) => [$k, rp($v)], array_keys($kas_ada), $kas_ada),
        ['Total uang keluar', rp(array_sum($kas))]);
    $pdf->catatan('Berbeda dari total biaya karena bon toko yang belum dibayar dan kasbon yang belum dipotong.');

    if (count($bulanan) > 1) {
        $pdf->bagian('Per bulan (Rp)');
        $pdf->tabel(array_merge([['Bulan', 0]], array_map(fn($j) => [$j, 29, 'R'], ['Bahan', 'Tukang', 'Pelayan', 'Lain', 'Total'])),
            array_map(fn($m, $v) => [substr(tgl("$m-01"), 2), ...array_map(fn($k) => $ribu($v[$k] ?? 0), array_keys($kat)), $ribu(array_sum($v))],
                array_keys($bulanan), $bulanan),
            ['Total', ...array_map($ribu, array_values($kat)), $ribu($total)]);
    }

    $pdf->bagian('Bahan per toko');
    $pdf->tabel([['Toko', 0], ['Belum dibayar (bon)', 42, 'R'], ['Total belanja', 40, 'R']],
        array_map(fn($t) => [$t['nama'], $t['bon'] > 0 ? rp($t['bon']) : '-', rp($t['jml'])], $per_toko));

    $pdf->bagian('Honor per pekerja');
    $pdf->tabel([['Nama', 0], ['Hari kerja', 55], ['Honor', 40, 'R']],
        array_map(fn($p) => [$p['nama'], hari_peran($p['h_tukang'], $p['h_pelayan']), rp($p['jml'])], $per_pekerja));

    if ($top_barang) {
        $pdf->bagian('10 bahan terbesar');
        $pdf->tabel([['Barang', 0], ['Jumlah', 40, 'R'], ['Total', 40, 'R']],
            array_map(fn($b) => [$b['nama_barang'], num($b['qty']) . ' ' . $b['satuan'], rp($b['jml'])], $top_barang));
    }

    if ($tx) $pdf->AddPage();
    $pdf->bagian('Rincian transaksi');
    $pdf->tabel([['Tanggal', 22], ['Kategori', 24], ['Uraian', 0], ['Toko/Pekerja', 30], ['Ket.', 22], ['Jumlah (Rp)', 24, 'R']],
        array_map(fn($t) => [tgl($t['tanggal']), $t['kategori'], $t['uraian'], $t['pihak'],
            preg_replace_callback('/\d+/', fn($m) => $ribu($m[0]), $t['ket']), $ribu($t['jumlah'])], $tx),   // "Potong kasbon 200.000"
        ['Total', '', count($tx) . ' transaksi', '', '', $ribu($total)]);

    $pdf->Output('D', 'laporan-rehab-' . $dari . '_' . $sampai . '.pdf');
    exit;
}

$header_action = '<button onclick="window.print()" class="btn btn-light btn-sm d-print-none"><i class="bi bi-printer"></i></button>';
require 'inc/header.php';
?>
<form method="get" class="card mb-3 d-print-none">
  <div class="card-body">
    <div class="d-flex gap-1 mb-2 flex-wrap">
      <a href="laporan.php" class="btn btn-sm <?= $semua ? 'btn-primary' : 'btn-light' ?>">Semua</a>
      <a href="?dari=<?= $bln_ini ?>&sampai=<?= date('Y-m-t') ?>" class="btn btn-sm <?= $dari === $bln_ini ? 'btn-primary' : 'btn-light' ?>">Bulan ini</a>
      <a href="?dari=<?= $bln_lalu ?>&sampai=<?= $akhir_bln_lalu ?>" class="btn btn-sm <?= $dari === $bln_lalu ? 'btn-primary' : 'btn-light' ?>">Bulan lalu</a>
      <a href="?dari=<?= date('Y-m-d', strtotime('monday this week')) ?>&sampai=<?= date('Y-m-d') ?>" class="btn btn-sm btn-light">Minggu ini</a>
    </div>
    <div class="row g-2">
      <div class="col-5"><input type="date" name="dari" value="<?= $semua ? '' : $dari ?>" class="form-control form-control-sm"></div>
      <div class="col-5"><input type="date" name="sampai" value="<?= $sampai ?>" class="form-control form-control-sm"></div>
      <div class="col-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button></div>
    </div>
  </div>
</form>

<div class="small-muted mb-2"><?= e(setting('nama_proyek')) ?> · Periode: <?= $periode ?></div>

<div class="card mb-3">
  <div class="card-header bg-white fw-semibold">Ringkasan biaya</div>
  <div class="card-body py-2">
    <?php foreach ($kat as $k => $v): $pct = $total ? $v / $total * 100 : 0; ?>
      <div class="py-1">
        <div class="d-flex justify-content-between"><span><?= $k ?></span><span class="money"><?= rp($v) ?> <small class="text-muted"><?= round($pct) ?>%</small></span></div>
        <div class="progress" style="height:5px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
      </div>
    <?php endforeach; ?>
    <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-2 fs-6"><span>Total biaya</span><span class="money"><?= rp($total) ?></span></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header bg-white fw-semibold">Uang keluar (arus kas)</div>
  <div class="card-body py-2">
    <?php foreach ($kas as $k => $v): if (!$v) continue; ?>
      <div class="d-flex justify-content-between py-1 small"><span><?= $k ?></span><span class="money <?= $v < 0 ? 'text-success' : '' ?>"><?= rp($v) ?></span></div>
    <?php endforeach; ?>
    <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-1"><span>Total uang keluar</span><span class="money"><?= rp(array_sum($kas)) ?></span></div>
    <div class="small-muted mt-1">Berbeda dari total biaya karena bon toko yang belum dibayar dan kasbon yang belum dipotong.</div>
  </div>
</div>

<?php if (count($bulanan) > 1): ?>
<div class="card mb-3">
  <div class="card-header bg-white fw-semibold">Per bulan</div>
  <div class="table-responsive">
    <table class="table table-sm small mb-0">
      <thead class="table-light"><tr><th class="ps-3">Bulan</th><th class="text-end">Bahan</th><th class="text-end">Tukang</th><th class="text-end">Pelayan</th><th class="text-end">Lain</th><th class="text-end pe-3">Total</th></tr></thead>
      <tbody>
      <?php foreach ($bulanan as $m => $v): ?>
        <tr><td class="ps-3 text-nowrap"><?= substr(tgl("$m-01"), 2) ?></td>
          <?php foreach (array_keys($kat) as $k): ?><td class="text-end money"><?= number_format($v[$k] ?? 0, 0, ',', '.') ?></td><?php endforeach; ?>
          <td class="text-end pe-3 money fw-semibold"><?= number_format(array_sum($v), 0, ',', '.') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Bahan per toko</div>
      <table class="table table-sm small mb-0">
        <?php foreach ($per_toko as $t): ?>
          <tr><td class="ps-3"><?= e($t['nama']) ?><?= $t['bon'] > 0 ? '<div class="text-danger">bon ' . rp($t['bon']) . '</div>' : '' ?></td><td class="text-end pe-3 money"><?= rp($t['jml']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$per_toko): ?><tr><td class="ps-3 text-muted">Tidak ada data</td></tr><?php endif; ?>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Honor per pekerja</div>
      <table class="table table-sm small mb-0">
        <?php foreach ($per_pekerja as $p): ?>
          <tr><td class="ps-3"><?= e($p['nama']) ?> <div class="text-muted"><?= hari_peran($p['h_tukang'], $p['h_pelayan']) ?></div></td><td class="text-end pe-3 money"><?= rp($p['jml']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$per_pekerja): ?><tr><td class="ps-3 text-muted">Tidak ada data</td></tr><?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php if ($top_barang): ?>
<div class="card mb-3">
  <div class="card-header bg-white fw-semibold">10 bahan terbesar</div>
  <table class="table table-sm small mb-0">
    <?php foreach ($top_barang as $b): ?>
      <tr><td class="ps-3"><?= e($b['nama_barang']) ?> <span class="text-muted"><?= num($b['qty']) ?> <?= e($b['satuan']) ?></span></td><td class="text-end pe-3 money"><?= rp($b['jml']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 d-print-none">
  <a href="?<?= e(http_build_query(['dari' => $dari, 'sampai' => $sampai, 'pdf' => 1])) ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Unduh laporan (PDF)</a>
  <a href="?<?= e(http_build_query(['dari' => $dari, 'sampai' => $sampai, 'csv' => 1])) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Unduh rincian (Excel/CSV)</a>
</div>

<?php require 'inc/footer.php'; ?>
