<?php
$title  = $title ?? 'Beranda';
$active = $active ?? '';
$menu = [
    'index'    => ['Dashboard', 'speedometer2'],
    'absensi'  => ['Absensi', 'calendar-check'],
    'bahan'    => ['Bahan Baku', 'bricks'],
    'gaji'     => ['Gaji / Honor', 'cash-stack'],
    'kasbon'   => ['Kasbon Tukang', 'journal-minus'],
    'toko'     => ['Toko & Bon Bahan', 'shop'],
    'lain'     => ['Pengeluaran Lain', 'receipt'],
    'pekerja'  => ['Data Pekerja', 'people'],
    'laporan'  => ['Laporan', 'bar-chart'],
    'pengaturan' => ['Pengaturan', 'gear'],
];
$bottom = ['index', 'absensi', 'bahan', 'kasbon'];
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ea580c">
<title><?= e($title) ?> · <?= e(setting('nama_proyek', 'Rehab Rumah')) ?></title>
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>
<nav class="navbar navbar-dark topbar sticky-top d-print-none">
  <div class="container-lg">
    <button class="btn btn-link text-white p-0 me-2 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidemenu" aria-label="Menu">
      <i class="bi bi-list fs-3"></i>
    </button>
    <a class="navbar-brand me-auto text-truncate" href="index.php">
      <i class="bi bi-hammer"></i> <?= e(setting('nama_proyek', 'Rehab Rumah')) ?>
    </a>
    <a href="logout.php" class="text-white opacity-75 small" data-confirm="Keluar dari aplikasi?" data-confirm-ok="Ya, keluar"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</nav>

<div class="offcanvas-lg offcanvas-start sidebar d-print-none" tabindex="-1" id="sidemenu">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Menu</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidemenu"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div class="list-group list-group-flush w-100">
      <?php foreach ($menu as $k => [$label, $icon]): ?>
        <a href="<?= $k ?>.php" class="list-group-item list-group-item-action <?= $active === $k ? 'active' : '' ?>">
          <i class="bi bi-<?= $icon ?> me-2"></i><?= $label ?>
        </a>
      <?php endforeach; ?>
      <a href="#" class="list-group-item list-group-item-action d-none text-primary fw-semibold" id="btn-install">
        <i class="bi bi-phone me-2"></i>Pasang aplikasi di HP
      </a>
    </div>
  </div>
</div>

<div id="offline-bar" class="offline-bar d-none d-print-none"><i class="bi bi-wifi-off"></i> Offline — menampilkan data terakhir. Simpan data setelah sinyal kembali.</div>
<main class="container-lg main-content">
<div class="d-flex align-items-center justify-content-between mb-3 mt-3">
  <h1 class="h5 mb-0 fw-bold"><?= e($title) ?></h1>
  <?= $header_action ?? '' ?>
</div>
<?php show_flash(); ?>
