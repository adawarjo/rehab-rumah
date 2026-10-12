<?php
$PUBLIC_PAGE = true;
require 'inc/init.php';

if (!empty($_SESSION['user_id'])) redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = row('SELECT * FROM users WHERE username = ?', [post('username')]);
    if ($u && password_verify(post('password'), $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['nama'] = $u['nama'];
        redirect('index.php');
    }
    flash('Username atau password salah.', 'danger');
    redirect('login.php');
}
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#b45309">
<title>Masuk · <?= e(setting('nama_proyek', 'Rehab Rumah')) ?></title>
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<?php include __DIR__ . '/inc/pwa_head.php'; ?>
</head>
<body class="d-flex align-items-center" style="min-height:100vh;padding:0">
<div class="container" style="max-width:380px">
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle text-white mb-2" style="width:64px;height:64px;background:var(--brand)">
      <i class="bi bi-hammer fs-2"></i>
    </div>
    <h1 class="h5 fw-bold mb-0"><?= e(setting('nama_proyek', 'Rehab Rumah')) ?></h1>
    <div class="small-muted">Catatan pengeluaran proyek</div>
  </div>
  <div class="card p-4">
    <?php show_flash(); ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input name="username" class="form-control form-control-lg" autocomplete="username" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control form-control-lg" autocomplete="current-password" required>
      </div>
      <button class="btn btn-primary btn-lg w-100">Masuk</button>
    </form>
  </div>
</div>
<script src="assets/vendor/sweetalert2.all.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
