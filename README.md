# Aplikasi Catatan Pengeluaran Rehab Rumah

PHP 8.0+ + MySQL 5.7+ / MariaDB 10.3+. Tampilan Bootstrap 5, dibuat untuk HP (menu bawah) dan tetap rapi di laptop. Notifikasi dan konfirmasi memakai SweetAlert2, grafik dashboard memakai Chart.js. Semua file CSS/JS (Bootstrap 5.3, Bootstrap Icons, SweetAlert2, Chart.js 4) sudah ada di folder `assets/vendor`, jadi tetap jalan tanpa internet (mis. di jaringan lokal).

## Pemasangan
1. Salin folder `rehab-rumah` ke `htdocs` (XAMPP/Laragon) atau `public_html` hosting.
2. Buka phpMyAdmin → **Import** → pilih `database.sql`. (Di shared hosting yang tidak boleh `CREATE DATABASE`, buat database lewat cPanel dulu, lalu hapus 2 baris pertama `CREATE DATABASE` dan `USE` sebelum import.)
3. Salin `config.example.php` menjadi `config.php`, lalu isi host, nama database, user dan password. (`config.php` sengaja tidak ikut ke Git.)
4. Buka `http://localhost/rehab-rumah/` → login **admin / admin123** → segera ganti password di **Pengaturan**.

## Pasang sebagai aplikasi di HP (PWA)
Syarat: situs dibuka lewat **HTTPS** (hosting dengan SSL, mis. Let's Encrypt gratis di cPanel) atau `localhost`. Lewat `http://192.168.x.x` di jaringan lokal, PWA tidak aktif — aplikasi tetap jalan seperti website biasa.
- **Android (Chrome):** buka aplikasi → menu ☰ → **Pasang aplikasi di HP** (atau menu ⋮ Chrome → *Instal aplikasi*).
- **iPhone (Safari):** tombol **Bagikan** → **Tambah ke Layar Utama**.
- Setelah dipasang: ikon rumah di layar utama, terbuka layar penuh, tekan lama ikon untuk pintasan Absensi / Belanja / Kasbon.
- **Saat sinyal hilang:** halaman yang pernah dibuka tetap bisa dilihat (data terakhir) dengan tanda "Offline". Menyimpan data ditahan dengan peringatan supaya isian tidak hilang — tekan Simpan lagi setelah sinyal kembali.
- Keluar (logout) menghapus salinan halaman yang tersimpan di HP.
- Jika mengubah file di `assets/`, naikkan `VERSION` di `sw.js` (mis. `rehab-v2`) agar HP mengambil versi baru.

## Dashboard (halaman utama)
- Total biaya vs anggaran, 4 kartu kategori (bahan, honor tukang, honor pelayan, lain-lain), hutang toko, upah terutang, kasbon.
- Filter periode: 7 hari · 30 hari · Bulan ini · Semua — berlaku untuk semua grafik.
- Grafik pengeluaran bertumpuk per hari/minggu/bulan (otomatis sesuai panjang periode) + tombol **Tabel**.
- Komposisi biaya, 5 bahan terbesar, grafik biaya berjalan vs garis anggaran, grafik kehadiran (hari-orang per hari).

## Alur pemakaian
1. **Pengaturan** – isi nama proyek, alamat dan total anggaran.
   Atur juga **tarif honor per peran** (default tukang Rp150.000/hari, pelayan Rp130.000/hari, lembur per jam).
2. **Data Pekerja** – tambah nama pekerja dan peran utamanya (hanya pilihan awal).
3. **Absensi** (tiap hari) – pilih **peran hari itu** (Tukang/Pelayan), tandai Hadir / ½ hari / Absen + jam lembur. Orang yang sama bisa jadi tukang hari ini dan pelayan besok; pilihan awal mengikuti peran terakhirnya. Tarif dicatat per hari.
4. **Kasbon Tukang** – catat saat tukang ambil uang; pelunasan tunai juga bisa dicatat.
5. **Gaji / Honor** – pilih pekerja → sistem menghitung hari kerja yang belum dibayar, lembur, dan menawarkan potongan kasbon → Bayar → slip gaji (cetak / kirim WhatsApp). Absensi yang sudah dibayar terkunci; pembayaran bisa dibatalkan untuk membukanya lagi. Tombol **Rekap per tanggal** menampilkan total honor tukang & pelayan tiap hari, status sudah/belum dibayar, dan rincian per orang.
6. **Bahan Baku** – catat belanja per barang (jumlah × harga otomatis). Pilih **Tunai** atau **Bon**. Tombol "Simpan & tambah lagi" untuk satu nota berisi banyak barang.
7. **Toko & Bon Bahan** – sisa hutang per toko, catat pembayaran/cicilan bon.
8. **Pengeluaran Lain** – konsumsi, transport, sewa alat, buang puing, dll.
9. **Laporan** – ringkasan biaya per kategori, per bulan, per toko, per pekerja, arus kas keluar, cetak, unduh PDF (ringkasan + rincian semua transaksi), dan unduh CSV untuk Excel.

## Cara hitung
- **Total biaya** = semua belanja bahan (tunai + bon) + honor kotor (upah + lembur + tambahan) + pengeluaran lain.
- **Uang keluar** = bahan tunai + bayar bon + gaji yang diserahkan + kasbon diberikan − kasbon dikembalikan tunai + pengeluaran lain.
- Upah per hari = tarif peran hari itu (½ hari = 50%). Lembur = jam × tarif lembur peran hari itu.
- Honor tukang / pelayan di laporan dihitung dari hari-hari sesuai perannya, bukan dari orangnya. Tambahan/borongan masuk ke peran yang paling banyak di pembayaran itu.
- Mengubah tarif hanya berlaku untuk absensi berikutnya, kecuali dicentang "terapkan ke absensi yang belum dibayar". Gaji yang sudah dibayar tidak pernah berubah.

## Upgrade dari versi sebelumnya
Kalau database versi lama (tarif per orang) sudah terpasang, import `migrasi_tarif_peran.sql` sekali lewat phpMyAdmin. Data lama tetap utuh: absensi lama memakai tarif orang tersebut, dan total honor tidak berubah.

## Struktur
```
config.example.php  contoh koneksi database (salin jadi config.php)
database.sql        struktur tabel + user admin
inc/                init, fungsi bantu, header & footer, pdf.php (pembuat PDF)
assets/             CSS, JS, Bootstrap lokal, ikon aplikasi
lib/fpdf/           library PDF (FPDF 1.86), tidak bisa dibuka langsung dari browser
manifest.json, sw.js, offline.html   PWA (pasang di HP & mode offline)
index.php           beranda / ringkasan
absensi.php, rekap_absensi.php
gaji.php, rekap_honor.php, slip.php, kasbon.php, pekerja.php
bahan.php, toko.php, lain.php
laporan.php, pengaturan.php, login.php, logout.php, captcha.php
```
