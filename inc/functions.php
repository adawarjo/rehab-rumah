<?php
// ---------- Database ----------
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die('<h3>Koneksi database gagal</h3><p>Periksa pengaturan di config.php dan pastikan database.sql sudah diimport.</p>');
        }
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []) { return q($sql, $p)->fetch(); }
function val(string $sql, array $p = []) { return q($sql, $p)->fetchColumn(); }

// Nilai hari kerja dari status absensi (dipakai di banyak query)
const SQL_HARI = "CASE a.status WHEN 'H' THEN 1 WHEN 'S' THEN 0.5 ELSE 0 END";
// Upah satu baris absensi (memakai tarif yang tercatat pada hari itu)
const SQL_UPAH = "((CASE a.status WHEN 'H' THEN 1 WHEN 'S' THEN 0.5 ELSE 0 END) * a.upah_harian + a.lembur_jam * a.upah_lembur)";
// Saldo kasbon: pinjam menambah, potong mengurangi
const SQL_KASBON = "CASE k.jenis WHEN 'pinjam' THEN k.jumlah ELSE -k.jumlah END";

// Warna kategori biaya (urutan tetap, aman untuk buta warna) — dipakai di dashboard & PDF laporan
const KAT = [
    'Bahan baku'    => '#2a78d6',
    'Honor tukang'  => '#eb6834',
    'Honor pelayan' => '#1baf7a',
    'Lain-lain'     => '#eda100',
];

// ---------- Format ----------
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function num($n): string { return rtrim(rtrim(number_format((float)$n, 2, ',', '.'), '0'), ','); }
/** "1.500.000" / "1500000" -> 1500000 */
function angka($s): float {
    $s = preg_replace('/[^\d,\-]/', '', (string)$s);
    return (float)str_replace(',', '.', $s);
}
/** angka desimal (qty, jam) — terima koma atau titik */
function desimal($s): float { return (float)str_replace(',', '.', (string)$s); }

function tgl($d, bool $hari = false): string {
    if (!$d) return '-';
    $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $hr  = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $t = strtotime($d);
    return ($hari ? $hr[date('w', $t)] . ', ' : '') . date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function valid_date($d): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) && strtotime($d); }
function get_date(string $key, string $default): string {
    $d = $_GET[$key] ?? $default;
    return valid_date($d) ? $d : $default;
}

// ---------- Request ----------
function post(string $k, $default = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $default; }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function flash(string $msg, string $type = 'success'): void { $_SESSION['flash'][] = [$type, $msg]; }
/** Pesan flash dikirim ke JavaScript dan ditampilkan dengan SweetAlert2 (lihat assets/app.js) */
function show_flash(): void {
    $data = array_map(fn($f) => ['type' => $f[0], 'msg' => $f[1]], $_SESSION['flash'] ?? []);
    unset($_SESSION['flash']);
    echo '<script>window.FLASH = ' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';</script>';
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Sesi formulir kedaluwarsa. Silakan kembali dan muat ulang halaman.');
    }
}

// ---------- Pengaturan ----------
function setting(string $k, $default = '') {
    static $cache = null;
    if ($cache === null) $cache = q('SELECT kunci, nilai FROM pengaturan')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $cache[$k] ?? $default;
}
function set_setting(string $k, $v): void {
    q('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)', [$k, $v]);
}

/** Tarif standar per peran: ['harian' => .., 'lembur' => ..] */
function tarif(string $peran): array {
    $peran = $peran === 'pelayan' ? 'pelayan' : 'tukang';
    return ['harian' => (float)setting("tarif_$peran", $peran === 'tukang' ? 150000 : 130000),
            'lembur' => (float)setting("lembur_$peran", 0)];
}

/**
 * Rincian upah dari baris-baris absensi, dikelompokkan per peran & tarif.
 * Hasil: ['baris' => [[label, jumlah], ...], 'hari' => [tukang, pelayan], 'biaya' => [tukang, pelayan], 'upah', 'lembur', 'jam']
 */
function rincian_upah(array $absen): array {
    $nilai = ['H' => 1, 'S' => .5, 'A' => 0];
    $grp = []; $lbr = [];
    $r = ['hari' => ['tukang' => 0, 'pelayan' => 0], 'biaya' => ['tukang' => 0, 'pelayan' => 0], 'upah' => 0, 'lembur' => 0, 'jam' => 0, 'baris' => []];
    foreach ($absen as $a) {
        $h = $nilai[$a['status']]; $pr = $a['peran'];
        $k = $pr . '|' . (float)$a['upah_harian'];
        $grp[$k] = ($grp[$k] ?? 0) + $h;
        if ($a['lembur_jam'] > 0) { $k2 = $pr . '|' . (float)$a['upah_lembur']; $lbr[$k2] = ($lbr[$k2] ?? 0) + $a['lembur_jam']; }
        $u = $h * $a['upah_harian']; $l = $a['lembur_jam'] * $a['upah_lembur'];
        $r['hari'][$pr] += $h; $r['biaya'][$pr] += $u + $l;
        $r['upah'] += $u; $r['lembur'] += $l; $r['jam'] += $a['lembur_jam'];
    }
    foreach ($grp as $k => $h) { if (!$h) continue; [$pr, $t] = explode('|', $k);
        $r['baris'][] = [num($h) . ' hari ' . $pr . ' × ' . rp($t), $h * $t]; }
    foreach ($lbr as $k => $j) { [$pr, $t] = explode('|', $k);
        $r['baris'][] = ['Lembur ' . $pr . ' ' . num($j) . ' jam × ' . rp($t), $j * $t]; }
    $r['upah'] = round($r['upah']); $r['lembur'] = round($r['lembur']);
    return $r;
}

/** "3 hari tukang, 2 hari pelayan" */
function hari_peran($tukang, $pelayan): string {
    $x = [];
    if ($tukang > 0) $x[] = num($tukang) . ' hr tukang';
    if ($pelayan > 0) $x[] = num($pelayan) . ' hr pelayan';
    return $x ? implode(', ', $x) : '0 hari';
}

// ---------- Komponen form ----------
function opsi_pekerja($selected = null, bool $semua = false): string {
    $list = rows('SELECT id, nama, jenis FROM pekerja' . ($semua ? '' : ' WHERE aktif = 1') . ' ORDER BY jenis, nama');
    $h = '';
    foreach ($list as $p) {
        $h .= '<option value="' . $p['id'] . '"' . ((string)$selected === (string)$p['id'] ? ' selected' : '') . '>'
            . e($p['nama']) . ' (' . ucfirst($p['jenis']) . ')</option>';
    }
    return $h;
}
function opsi_toko($selected = null): string {
    $h = '';
    foreach (rows('SELECT id, nama FROM toko ORDER BY nama') as $t) {
        $h .= '<option value="' . $t['id'] . '"' . ((string)$selected === (string)$t['id'] ? ' selected' : '') . '>' . e($t['nama']) . '</option>';
    }
    return $h;
}
/** Tombol hapus kecil (POST + konfirmasi) */
function tombol_hapus(string $action, int $id, string $pesan = 'Hapus data ini?', string $label = '<i class="bi bi-trash"></i>', string $ok = 'Ya, hapus'): string {
    return '<form method="post" action="' . e($action) . '" class="d-inline" data-confirm="' . e($pesan) . '" data-confirm-type="danger" data-confirm-ok="' . e($ok) . '">'
         . csrf_field() . '<input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="' . $id . '">'
         . '<button class="btn btn-sm btn-outline-danger" title="Hapus">' . $label . '</button></form>';
}
