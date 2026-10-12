<?php
require 'inc/init.php';
$title = 'Pengaturan';
$active = 'pengaturan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('aksi') === 'proyek') {
        set_setting('nama_proyek', post('nama_proyek') ?: 'Rehab Rumah');
        set_setting('alamat_proyek', post('alamat_proyek'));
        set_setting('anggaran', (string)angka(post('anggaran')));
        flash('Pengaturan proyek disimpan.');
    }
    if (post('aksi') === 'tarif') {
        foreach (['tarif_tukang', 'tarif_pelayan', 'lembur_tukang', 'lembur_pelayan'] as $k) set_setting($k, (string)angka(post($k)));
        $n = 0;
        if (post('terapkan')) {
            // absensi yang belum dibayar ikut tarif baru; yang sudah dibayar tidak berubah
            foreach (['tukang', 'pelayan'] as $pr) {
                $n += q('UPDATE absensi SET upah_harian = ?, upah_lembur = ? WHERE gaji_id IS NULL AND peran = ?',
                        [angka(post("tarif_$pr")), angka(post("lembur_$pr")), $pr])->rowCount();
            }
        }
        flash('Tarif disimpan.' . (post('terapkan') ? " $n hari absensi yang belum dibayar ikut diperbarui." : ' Berlaku untuk absensi berikutnya.'));
    }
    if (post('aksi') === 'password') {
        $u = row('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
        if (!password_verify(post('lama'), $u['password'])) flash('Password lama salah.', 'danger');
        elseif (strlen(post('baru')) < 6) flash('Password baru minimal 6 karakter.', 'danger');
        elseif (post('baru') !== post('ulang')) flash('Konfirmasi password tidak sama.', 'danger');
        else {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash(post('baru'), PASSWORD_DEFAULT), $u['id']]);
            flash('Password berhasil diganti.');
        }
    }
    if (post('aksi') === 'user') {
        if (post('username') === '' || strlen(post('password')) < 6) flash('Username wajib dan password minimal 6 karakter.', 'danger');
        elseif (val('SELECT COUNT(*) FROM users WHERE username = ?', [post('username')])) flash('Username sudah dipakai.', 'danger');
        else {
            q('INSERT INTO users (username, password, nama) VALUES (?,?,?)', [post('username'), password_hash(post('password'), PASSWORD_DEFAULT), post('nama') ?: post('username')]);
            flash('Pengguna ditambahkan.');
        }
    }
    redirect('pengaturan.php');
}
$users = rows('SELECT id, username, nama FROM users ORDER BY id');
require 'inc/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <div class="fw-semibold mb-2">Proyek</div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="proyek">
    <label class="form-label small mb-1">Nama proyek</label>
    <input name="nama_proyek" class="form-control mb-2" value="<?= e(setting('nama_proyek')) ?>">
    <label class="form-label small mb-1">Alamat (tampil di slip gaji)</label>
    <input name="alamat_proyek" class="form-control mb-2" value="<?= e(setting('alamat_proyek')) ?>">
    <label class="form-label small mb-1">Anggaran total (Rp)</label>
    <input name="anggaran" class="form-control uang mb-3" inputmode="numeric" value="<?= (int)setting('anggaran') ?: '' ?>">
    <button class="btn btn-primary w-100">Simpan</button>
  </form>
</div></div>

<div class="card mb-3" id="tarif"><div class="card-body">
  <div class="fw-semibold">Tarif honor per peran</div>
  <div class="small-muted mb-2">Peran dipilih per hari di Absensi — orang yang sama bisa jadi tukang hari ini dan pelayan besok.</div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="tarif">
    <div class="row g-2 mb-1 small fw-semibold"><div class="col-4"></div><div class="col-4">Per hari</div><div class="col-4">Lembur / jam</div></div>
    <?php foreach (['tukang' => 'Tukang', 'pelayan' => 'Pelayan'] as $pr => $l): ?>
      <div class="row g-2 mb-2 align-items-center">
        <div class="col-4"><span class="badge badge-<?= $pr ?> fs-6 fw-semibold"><?= $l ?></span></div>
        <div class="col-4"><input name="tarif_<?= $pr ?>" class="form-control uang" inputmode="numeric" value="<?= (int)setting("tarif_$pr") ?>" required></div>
        <div class="col-4"><input name="lembur_<?= $pr ?>" class="form-control uang" inputmode="numeric" value="<?= (int)setting("lembur_$pr") ?: '' ?>" placeholder="0"></div>
      </div>
    <?php endforeach; ?>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="terapkan" value="1" id="terapkan">
      <label class="form-check-label small" for="terapkan">Terapkan juga ke absensi yang <b>belum dibayar</b> (yang sudah dibayar tidak berubah)</label>
    </div>
    <button class="btn btn-primary w-100">Simpan tarif</button>
  </form>
</div></div>

<div class="card mb-3"><div class="card-body">
  <div class="fw-semibold mb-2">Ganti password (<?= e($_SESSION['nama'] ?? '') ?>)</div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="password">
    <input type="password" name="lama" class="form-control mb-2" placeholder="Password lama" autocomplete="current-password" required>
    <input type="password" name="baru" class="form-control mb-2" placeholder="Password baru" autocomplete="new-password" required>
    <input type="password" name="ulang" class="form-control mb-3" placeholder="Ulangi password baru" autocomplete="new-password" required>
    <button class="btn btn-outline-primary w-100">Ganti password</button>
  </form>
</div></div>

<div class="card mb-3"><div class="card-body">
  <div class="fw-semibold mb-2">Pengguna</div>
  <?php foreach ($users as $u): ?><div class="small py-1 border-bottom"><i class="bi bi-person"></i> <?= e($u['nama']) ?> <span class="text-muted">(<?= e($u['username']) ?>)</span></div><?php endforeach; ?>
  <form method="post" class="mt-2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="user">
    <div class="row g-2">
      <div class="col-6"><input name="nama" class="form-control form-control-sm" placeholder="Nama (mis. Mandor)"></div>
      <div class="col-6"><input name="username" class="form-control form-control-sm" placeholder="Username" required></div>
      <div class="col-8"><input type="password" name="password" class="form-control form-control-sm" placeholder="Password" autocomplete="new-password" required></div>
      <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100">Tambah</button></div>
    </div>
  </form>
</div></div>
<?php require 'inc/footer.php'; ?>
