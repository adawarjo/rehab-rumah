-- =========================================================
--  Database: Catatan Pengeluaran Rehab Rumah
--  Jalankan sekali via phpMyAdmin (Import) atau:
--  mysql -u root -p < database.sql
-- =========================================================
CREATE DATABASE IF NOT EXISTS rehab_rumah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rehab_rumah;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- login awal: admin / admin123  (segera ganti di menu Pengaturan)
INSERT INTO users (username, password, nama) VALUES
('admin', '$2y$10$tARwUxgVv7vNYNjqJ3f8Ie9ihNekbHGpLuNn26zcazkQfV86lqo3e', 'Administrator');

CREATE TABLE pengaturan (
  kunci VARCHAR(50) PRIMARY KEY,
  nilai TEXT
) ENGINE=InnoDB;

INSERT INTO pengaturan VALUES
('nama_proyek', 'Rehab Rumah'),
('alamat_proyek', ''),
('anggaran', '0'),
('jam_kerja', '8'),
('tarif_tukang', '150000'),
('tarif_pelayan', '130000'),
('lembur_tukang', '18750'),
('lembur_pelayan', '16250');

-- Tukang & pelayan (kenek). Tarif mengikuti peran harian di absensi, bukan orangnya.
CREATE TABLE pekerja (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  jenis ENUM('tukang','pelayan') NOT NULL DEFAULT 'tukang' COMMENT 'peran utama (pilihan awal di absensi)',
  no_hp VARCHAR(20) NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Pembayaran gaji / honor (snapshot saat dibayar)
CREATE TABLE gaji (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pekerja_id INT NOT NULL,
  tanggal_bayar DATE NOT NULL,
  periode_mulai DATE NULL,
  periode_selesai DATE NULL,
  hari_kerja DECIMAL(6,1) NOT NULL DEFAULT 0,
  hari_tukang DECIMAL(6,1) NOT NULL DEFAULT 0,
  hari_pelayan DECIMAL(6,1) NOT NULL DEFAULT 0,
  jam_lembur DECIMAL(6,1) NOT NULL DEFAULT 0,
  total_upah DECIMAL(14,0) NOT NULL DEFAULT 0 COMMENT 'upah harian',
  total_lembur DECIMAL(14,0) NOT NULL DEFAULT 0,
  bonus DECIMAL(14,0) NOT NULL DEFAULT 0,
  biaya_tukang DECIMAL(14,0) NOT NULL DEFAULT 0 COMMENT 'upah+lembur hari-hari sbg tukang (+bonus)',
  biaya_pelayan DECIMAL(14,0) NOT NULL DEFAULT 0 COMMENT 'upah+lembur hari-hari sbg pelayan (+bonus)',
  potongan_kasbon DECIMAL(14,0) NOT NULL DEFAULT 0,
  dibayar DECIMAL(14,0) NOT NULL DEFAULT 0 COMMENT 'uang tunai yang diserahkan',
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pekerja_id) REFERENCES pekerja(id),
  INDEX (tanggal_bayar)
) ENGINE=InnoDB;

-- Absensi harian. status: H=hadir (1 hari), S=setengah hari, A=tidak masuk
-- peran + tarif dicatat per hari (orang yang sama bisa jadi tukang hari ini, pelayan besok)
CREATE TABLE absensi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pekerja_id INT NOT NULL,
  tanggal DATE NOT NULL,
  status ENUM('H','S','A') NOT NULL DEFAULT 'H',
  peran ENUM('tukang','pelayan') NOT NULL DEFAULT 'tukang',
  upah_harian DECIMAL(12,0) NOT NULL DEFAULT 0,
  upah_lembur DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT 'per jam',
  lembur_jam DECIMAL(4,1) NOT NULL DEFAULT 0,
  keterangan VARCHAR(255) NULL,
  gaji_id INT NULL COMMENT 'terisi jika sudah dibayar',
  UNIQUE KEY uk_absen (pekerja_id, tanggal),
  FOREIGN KEY (pekerja_id) REFERENCES pekerja(id) ON DELETE CASCADE,
  FOREIGN KEY (gaji_id) REFERENCES gaji(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Bon / kasbon tukang. pinjam = tukang ambil uang, potong = dilunasi (potong gaji / bayar tunai)
CREATE TABLE kasbon (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pekerja_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jenis ENUM('pinjam','potong') NOT NULL DEFAULT 'pinjam',
  jumlah DECIMAL(14,0) NOT NULL,
  keterangan VARCHAR(255) NULL,
  gaji_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pekerja_id) REFERENCES pekerja(id) ON DELETE CASCADE,
  FOREIGN KEY (gaji_id) REFERENCES gaji(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Toko bangunan / supplier
CREATE TABLE toko (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  alamat VARCHAR(255) NULL,
  no_hp VARCHAR(20) NULL
) ENGINE=InnoDB;

-- Belanja bahan baku. status: lunas = bayar tunai, bon = hutang ke toko
CREATE TABLE bahan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  toko_id INT NULL,
  no_nota VARCHAR(50) NULL,
  nama_barang VARCHAR(150) NOT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  satuan VARCHAR(20) NULL,
  harga_satuan DECIMAL(14,0) NOT NULL DEFAULT 0,
  total DECIMAL(14,0) NOT NULL DEFAULT 0,
  status ENUM('lunas','bon') NOT NULL DEFAULT 'lunas',
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (toko_id) REFERENCES toko(id) ON DELETE SET NULL,
  INDEX (tanggal)
) ENGINE=InnoDB;

-- Pembayaran / cicilan bon ke toko
CREATE TABLE bayar_toko (
  id INT AUTO_INCREMENT PRIMARY KEY,
  toko_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(14,0) NOT NULL,
  keterangan VARCHAR(255) NULL,
  FOREIGN KEY (toko_id) REFERENCES toko(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pengeluaran lain (transport, konsumsi, sewa alat, dll)
CREATE TABLE pengeluaran_lain (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  kategori VARCHAR(50) NOT NULL,
  keterangan VARCHAR(255) NULL,
  jumlah DECIMAL(14,0) NOT NULL,
  INDEX (tanggal)
) ENGINE=InnoDB;
