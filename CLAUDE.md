# Panduan untuk Claude Code — Aplikasi Rehab Rumah

Aplikasi PHP 8 + MySQL (PDO) tanpa framework untuk mencatat pengeluaran rehab rumah. Antarmuka berbahasa Indonesia, utamanya dipakai di HP.

## Struktur
- Satu file PHP per halaman di root (`absensi.php`, `gaji.php`, `bahan.php`, ...). Pola: `require 'inc/init.php'` → proses POST → `redirect()` → query → `inc/header.php` → HTML → `inc/footer.php`.
- `inc/functions.php`: helper `q()`, `rows()`, `row()`, `val()`, `e()`, `rp()`, `num()`, `angka()`, `flash()`, `tarif()`, `rincian_upah()`, konstanta `SQL_HARI`, `SQL_UPAH`, `SQL_KASBON`, `KAT` (warna kategori biaya untuk dashboard & PDF).
- `database.sql` = skema lengkap untuk instal baru. Perubahan skema untuk database yang sudah jalan → buat file `migrasi_<nama>.sql` terpisah.
- `assets/vendor/` berisi Bootstrap 5.3, Bootstrap Icons, SweetAlert2, Chart.js — semua lokal, jangan ganti ke CDN.
- `lib/fpdf/` berisi FPDF 1.86 (font bawaan Helvetica, encoding Windows-1252). Buat PDF lewat class `PDF` di `inc/pdf.php` (`bagian()`, `tabel()`, `catatan()`); teks UTF-8 diubah otomatis.

## Aturan bisnis penting
- Tarif honor mengikuti **peran per hari** (tukang/pelayan) yang dicatat di tabel `absensi` (`peran`, `upah_harian`, `upah_lembur`), bukan per orang.
- Absensi dengan `gaji_id` terisi sudah dibayar → terkunci, jangan diubah.
- Gaji menyimpan snapshot (`biaya_tukang`, `biaya_pelayan`, dll.); gaji yang sudah dibayar tidak boleh ikut berubah saat tarif diubah.
- Kasbon adalah buku besar: `pinjam` menambah saldo, `potong` mengurangi.

## Konvensi kode
- Selalu prepared statement lewat `q()`; jangan menyambung input pengguna ke SQL.
- Output HTML selalu lewat `e()`; uang ditampilkan dengan `rp()`; input uang pakai class `uang` dan dibaca dengan `angka()`.
- Warna: pakai variabel `:root` di `assets/style.css` (`--brand` #ea580c oranye, `--bg`, `--muted`, dst.) dan class `.hero` / `.hero-danger` untuk kartu utama; jangan menulis kode warna baru langsung di PHP. Warna PDF ada di konstanta class `PDF` (`inc/pdf.php`).
- Setiap form POST wajib `<?= csrf_field() ?>`. Konfirmasi hapus pakai `tombol_hapus()` atau atribut `data-confirm` (SweetAlert2).
- Query harus lolos `ONLY_FULL_GROUP_BY` (sebutkan semua kolom non-agregat di GROUP BY).
- Teks antarmuka & pesan dalam Bahasa Indonesia.
- Jika mengubah file di `assets/`, naikkan `VERSION` di `sw.js`.

## Cek sebelum commit
- `for f in *.php inc/*.php; do php -l "$f"; done` harus bersih.
- `config.php` tidak boleh di-commit (ada di `.gitignore`).
- Pesan commit dalam Bahasa Indonesia, singkat, menjelaskan apa yang berubah.
