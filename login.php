<?php
$PUBLIC_PAGE = true;
require 'inc/init.php';

if (!empty($_SESSION['user_id'])) redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kode captcha hanya berlaku sekali: setiap percobaan login memakai gambar baru
    $kode = $_SESSION['captcha'] ?? '';
    unset($_SESSION['captcha']);
    $_SESSION['login_username'] = post('username');
    $isi = post('captcha');
    if ($kode === '' || !is_string($isi) || !hash_equals($kode, $isi)) {
        flash('Kode keamanan salah. Silakan coba lagi.', 'danger');
        redirect('login.php');
    }
    $u = row('SELECT * FROM users WHERE username = ?', [post('username')]);
    if ($u && password_verify(post('password'), $u['password'])) {
        session_regenerate_id(true);
        unset($_SESSION['login_username']);
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['nama'] = $u['nama'];
        redirect('index.php');
    }
    flash('Username atau password salah.', 'danger');
    redirect('login.php');
}
// Username diisi lagi setelah gagal, supaya tidak perlu diketik ulang
$username = $_SESSION['login_username'] ?? '';
unset($_SESSION['login_username']);
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#ea580c">
<title>Masuk · <?= e(setting('nama_proyek', 'Rehab Rumah')) ?></title>
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<?php include __DIR__ . '/inc/pwa_head.php'; ?>
</head>
<body class="d-flex align-items-center login-page" style="min-height:100vh;padding:0">
<div class="container" style="max-width:380px">
  <div class="text-center mb-4">
    <div class="logo-badge mb-3">
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
        <input name="username" value="<?= e($username) ?>" class="form-control form-control-lg" autocomplete="username" required<?= $username === '' ? ' autofocus' : '' ?>>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control form-control-lg" autocomplete="current-password" required<?= $username !== '' ? ' autofocus' : '' ?>>
      </div>
      <div class="mb-3">
        <label class="form-label" for="captcha">Kode keamanan</label>
        <div class="d-flex align-items-center gap-2 mb-2">
          <img src="captcha.php" id="captcha-img" alt="Kode keamanan" width="180" height="60" class="rounded border">
          <button type="button" id="captcha-baru" class="btn btn-outline-secondary btn-lg" title="Ganti kode" aria-label="Ganti kode"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        <input id="captcha" name="captcha" class="form-control form-control-lg" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" placeholder="Ketik 4 angka di atas" title="Ketik 4 angka pada gambar" required>
      </div>
      <button class="btn btn-primary btn-lg w-100">Masuk</button>
    </form>
  </div>
</div>
<script src="assets/vendor/sweetalert2.all.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
