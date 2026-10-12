-- =========================================================
--  HANYA untuk database yang sudah terpasang dengan versi lama.
--  Instal baru cukup pakai database.sql.
--  Mengubah tarif dari per orang menjadi per peran harian.
-- =========================================================
USE rehab_rumah;

INSERT IGNORE INTO pengaturan VALUES
('tarif_tukang', '150000'), ('tarif_pelayan', '130000'),
('lembur_tukang', '18750'), ('lembur_pelayan', '16250');

ALTER TABLE absensi
  ADD COLUMN peran ENUM('tukang','pelayan') NOT NULL DEFAULT 'tukang' AFTER status,
  ADD COLUMN upah_harian DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER peran,
  ADD COLUMN upah_lembur DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER upah_harian;

-- absensi lama: peran = jenis pekerja, tarif = tarif lama orangnya
UPDATE absensi a JOIN pekerja p ON p.id = a.pekerja_id
   SET a.peran = p.jenis, a.upah_harian = p.upah_harian, a.upah_lembur = p.upah_lembur;

ALTER TABLE gaji
  ADD COLUMN hari_tukang DECIMAL(6,1) NOT NULL DEFAULT 0 AFTER hari_kerja,
  ADD COLUMN hari_pelayan DECIMAL(6,1) NOT NULL DEFAULT 0 AFTER hari_tukang,
  ADD COLUMN biaya_tukang DECIMAL(14,0) NOT NULL DEFAULT 0 AFTER bonus,
  ADD COLUMN biaya_pelayan DECIMAL(14,0) NOT NULL DEFAULT 0 AFTER biaya_tukang;

UPDATE gaji g JOIN pekerja p ON p.id = g.pekerja_id SET
  g.hari_tukang   = IF(p.jenis = 'tukang',  g.hari_kerja, 0),
  g.hari_pelayan  = IF(p.jenis = 'pelayan', g.hari_kerja, 0),
  g.biaya_tukang  = IF(p.jenis = 'tukang',  g.total_upah + g.total_lembur + g.bonus, 0),
  g.biaya_pelayan = IF(p.jenis = 'pelayan', g.total_upah + g.total_lembur + g.bonus, 0);

ALTER TABLE gaji DROP COLUMN upah_harian, DROP COLUMN upah_lembur;
ALTER TABLE pekerja DROP COLUMN upah_harian, DROP COLUMN upah_lembur;
