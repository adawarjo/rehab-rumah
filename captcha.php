<?php
/**
 * Gambar captcha untuk halaman login: 4 angka, dibuat sebagai SVG (tanpa GD/font).
 * Setiap kali dimuat membuat kode baru yang disimpan di $_SESSION['captcha'].
 */
$PUBLIC_PAGE = true;
require 'inc/init.php';

// Bentuk angka 0–9 sebagai garis (kotak 20 × 30), digambar dengan stroke tebal agar jelas dibaca
const CAPTCHA_ANGKA = [
    '0' => 'M10 0 C3 0 1 7 1 15 C1 23 3 30 10 30 C17 30 19 23 19 15 C19 7 17 0 10 0 Z',
    '1' => 'M4 7 L11 0 V30 M4 30 H18',
    '2' => 'M1 7 C1 2 5 0 10 0 C15 0 19 3 19 8 C19 14 12 19 1 30 H20',
    '3' => 'M1 4 C3 1 6 0 10 0 C15 0 18 3 18 7 C18 11 15 14 9 14 C15 14 19 17 19 22 C19 27 15 30 10 30 C6 30 3 29 1 26',
    '4' => 'M15 30 V0 L0 21 H20',
    '5' => 'M18 1 H4 L2 14 C4 12 7 11 10 11 C16 11 19 15 19 20 C19 26 15 30 10 30 C6 30 3 29 1 26',
    '6' => 'M17 2 C15 1 13 0 10 0 C4 0 1 6 1 16 C1 25 4 30 10 30 C16 30 19 26 19 21 C19 15 15 12 10 12 C5 12 2 15 1 19',
    '7' => 'M1 1 H19 C13 9 9 19 7 30',
    '8' => 'M10 14 C5 14 2 11 2 7 C2 3 5 0 10 0 C15 0 18 3 18 7 C18 11 15 14 10 14 C4 14 1 17 1 22 C1 27 5 30 10 30 C15 30 19 27 19 22 C19 17 16 14 10 14 Z',
    '9' => 'M3 28 C5 29 7 30 10 30 C16 30 19 24 19 14 C19 5 16 0 10 0 C4 0 1 4 1 9 C1 15 5 18 10 18 C15 18 18 15 19 11',
];
const CAPTCHA_WARNA = ['#1c1917', '#7c2d12', '#1e3a8a', '#14532d', '#581c87'];

$kode = '';
for ($i = 0; $i < 4; $i++) $kode .= random_int(0, 9);
$_SESSION['captcha'] = $kode;
session_write_close();

// Garis samar di belakang angka
$garis = '';
for ($i = 0; $i < 3; $i++) {
    $garis .= sprintf('<path d="M0 %d Q%d %d 150 %d"/>', random_int(8, 42), random_int(30, 120), random_int(0, 50), random_int(8, 42));
}

// Angka: posisi, kemiringan dan warna diacak sedikit; titik-titik garisnya digeser acak agar tidak sama persis
$angka = '';
foreach (str_split($kode) as $i => $d) {
    $d = preg_replace_callback('/\d+(?:\.\d+)?/', fn($m) => round($m[0] + random_int(-5, 5) / 10, 1), CAPTCHA_ANGKA[$d]);
    $angka .= sprintf('<path transform="translate(%d %d) rotate(%d 10 15)" stroke="%s" d="%s"/>',
        17 + $i * 30 + random_int(-2, 2), 10 + random_int(-3, 3), random_int(-12, 12),
        CAPTCHA_WARNA[array_rand(CAPTCHA_WARNA)], $d);
}

header('Content-Type: image/svg+xml');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 50" width="150" height="50">',
     '<rect width="150" height="50" fill="#f8fafc"/>',
     '<g fill="none" stroke="#cbd5e1" stroke-width="1.5">', $garis, '</g>',
     '<g fill="none" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">', $angka, '</g>',
     '</svg>';
