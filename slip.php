<?php
require 'inc/init.php';
$title = 'Slip Gaji';
$active = 'gaji';

$g = row('SELECT g.*, p.nama, p.jenis FROM gaji g JOIN pekerja p ON p.id = g.pekerja_id WHERE g.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$g) { flash('Slip tidak ditemukan.', 'danger'); redirect('gaji.php'); }
$absen = rows('SELECT * FROM absensi WHERE gaji_id = ? ORDER BY tanggal', [$g['id']]);
$rinci = rincian_upah($absen)['baris'];
$sisa_kasbon = (float)val('SELECT COALESCE(SUM(' . SQL_KASBON . '),0) FROM kasbon k WHERE k.pekerja_id = ?', [$g['pekerja_id']]);

// Teks ringkas untuk dibagikan via WhatsApp
$wa = "*Slip Gaji - " . setting('nama_proyek') . "*\n"
    . "Nama: {$g['nama']}\nTanggal: " . tgl($g['tanggal_bayar']) . "\n"
    . ($g['periode_mulai'] ? "Periode: " . tgl($g['periode_mulai']) . " - " . tgl($g['periode_selesai']) . "\n" : '')
    . implode('', array_map(fn($b) => str_replace('×', 'x', $b[0]) . " = " . rp($b[1]) . "\n", $rinci))
    . ($g['bonus'] > 0 ? "Tambahan = " . rp($g['bonus']) . "\n" : '')
    . ($g['potongan_kasbon'] > 0 ? "Potong kasbon = -" . rp($g['potongan_kasbon']) . "\n" : '')
    . "*Diterima: " . rp($g['dibayar']) . "*"
    . ($sisa_kasbon > 0 ? "\nSisa kasbon: " . rp($sisa_kasbon) : '');

require 'inc/header.php';
?>
<div class="card mb-3" style="max-width:480px">
  <div class="card-body">
    <div class="text-center border-bottom pb-2 mb-2">
      <div class="fw-bold"><?= e(setting('nama_proyek')) ?></div>
      <?php if (setting('alamat_proyek')): ?><div class="small-muted"><?= e(setting('alamat_proyek')) ?></div><?php endif; ?>
      <div class="small text-uppercase mt-1">Slip Gaji #<?= $g['id'] ?></div>
    </div>
    <table class="table table-sm table-borderless mb-2">
      <tr><td class="text-muted">Nama</td><td class="fw-semibold"><?= e($g['nama']) ?></td></tr>
      <tr><td class="text-muted">Dibayar</td><td><?= tgl($g['tanggal_bayar'], true) ?></td></tr>
      <?php if ($g['periode_mulai']): ?><tr><td class="text-muted">Periode</td><td><?= tgl($g['periode_mulai']) ?> – <?= tgl($g['periode_selesai']) ?></td></tr><?php endif; ?>
    </table>
    <table class="table table-sm mb-2">
      <?php foreach ($rinci as [$lbl, $jml]): ?><tr><td><?= e($lbl) ?></td><td class="text-end money"><?= rp($jml) ?></td></tr><?php endforeach; ?>
      <?php if ($g['bonus'] > 0): ?><tr><td>Tambahan / borongan</td><td class="text-end money"><?= rp($g['bonus']) ?></td></tr><?php endif; ?>
      <?php if ($g['potongan_kasbon'] > 0): ?><tr><td>Potong kasbon</td><td class="text-end money text-danger">−<?= rp($g['potongan_kasbon']) ?></td></tr><?php endif; ?>
      <tr class="fw-bold fs-6"><td>Diterima</td><td class="text-end money"><?= rp($g['dibayar']) ?></td></tr>
    </table>
    <?php if ($sisa_kasbon > 0): ?><div class="small text-danger mb-2">Sisa kasbon saat ini: <?= rp($sisa_kasbon) ?></div><?php endif; ?>
    <?php if ($g['keterangan']): ?><div class="small-muted mb-2">Ket: <?= e($g['keterangan']) ?></div><?php endif; ?>
    <?php if ($absen): ?>
      <div class="small-muted">Hari kerja (T = tukang, P = pelayan):
        <?= implode(', ', array_map(fn($a) => date('j/n', strtotime($a['tanggal'])) . ($a['peran'] === 'pelayan' ? 'P' : 'T') . ($a['status'] === 'S' ? '(½)' : ($a['status'] === 'A' ? '(x)' : '')) . ($a['lembur_jam'] > 0 ? '+' . num($a['lembur_jam']) . 'j' : ''), $absen)) ?>
      </div>
    <?php endif; ?>
    <div class="row text-center small mt-4">
      <div class="col">Penerima<br><br><br>( <?= e($g['nama']) ?> )</div>
      <div class="col">Pemberi<br><br><br>( ................ )</div>
    </div>
  </div>
</div>
<div class="d-flex gap-2 d-print-none" style="max-width:480px">
  <button onclick="window.print()" class="btn btn-light flex-fill"><i class="bi bi-printer"></i> Cetak</button>
  <a href="https://wa.me/?text=<?= rawurlencode($wa) ?>" target="_blank" class="btn btn-success flex-fill"><i class="bi bi-whatsapp"></i> Kirim WA</a>
  <a href="gaji.php" class="btn btn-outline-secondary">Selesai</a>
</div>
<?php require 'inc/footer.php'; ?>
