<?php
/**
 * Pembuat PDF berbasis FPDF (lib/fpdf): kepala halaman, kartu angka, judul bagian dan tabel.
 * Warna mengikuti tampilan aplikasi (oranye segar + abu kebiruan, lihat assets/style.css).
 * Teks dari aplikasi (UTF-8) diubah otomatis ke Windows-1252, encoding font bawaan FPDF.
 */
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

class PDF extends FPDF
{
    const BRAND = [234, 88, 12], BRAND_GELAP = [194, 65, 12], BRAND_LEMBUT = [255, 247, 237];
    const TINTA = [15, 23, 42], TEKS = [30, 41, 59], SAMAR = [100, 116, 139];
    const GARIS = [226, 232, 240], LATAR = [241, 245, 249], LATAR_KARTU = [248, 250, 252];

    private string $judul, $subjudul, $proyek, $alamat;
    private array $kolom = [];   // kolom tabel yang sedang dicetak, diulang di setiap halaman baru

    function __construct(string $judul, string $subjudul = '')
    {
        parent::__construct('P', 'mm', 'A4');
        $this->judul = $judul;
        $this->subjudul = $subjudul;
        $this->proyek = setting('nama_proyek', 'Rehab Rumah');
        $this->alamat = setting('alamat_proyek');
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 18);
        $this->AliasNbPages();
        $this->SetTitle($judul . ' - ' . $this->proyek, true);
        $this->SetCreator($this->proyek, true);
        $this->AddPage();
    }

    /** UTF-8 -> Windows-1252; huruf yang tidak ada diganti yang mirip atau "?" */
    static function t($s): string
    {
        $s = (string)$s;
        $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        return $r === false ? mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : $r;
    }

    /** "#2a78d6" -> [42, 120, 214] */
    static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    function Header()
    {
        $kanan = $this->w - $this->rMargin;
        $this->SetTextColor(...self::TINTA);
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(0, 7, self::t($this->proyek));
        $this->SetX($this->lMargin);
        $this->SetTextColor(...self::BRAND_GELAP);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->Cell(0, 7, self::t(mb_strtoupper($this->judul)), 0, 1, 'R');
        $this->SetTextColor(...self::SAMAR);
        $this->SetFont('Helvetica', '', 8.5);
        $this->Cell(0, 5, self::t($this->alamat));
        $this->SetX($this->lMargin);
        $this->Cell(0, 5, self::t($this->subjudul), 0, 1, 'R');
        // garis tipis dengan aksen oranye di kiri
        $y = $this->GetY() + 2.5;
        $this->SetDrawColor(...self::GARIS);
        $this->SetLineWidth(0.3);
        $this->Line($this->lMargin, $y, $kanan, $y);
        $this->SetLineWidth(0.2);
        $this->SetFillColor(...self::BRAND);
        $this->kotak($this->lMargin, $y - 0.6, 24, 1.2, 0.6, 'F');
        $this->SetY($y + 6);
        if ($this->kolom) $this->kepalaTabel();
    }

    function Footer()
    {
        $y = $this->h - 13;
        $this->SetDrawColor(...self::GARIS);
        $this->Line($this->lMargin, $y, $this->w - $this->rMargin, $y);
        $this->SetY($y + 1.5);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::SAMAR);
        $this->Cell(0, 5, self::t('Dicetak ' . tgl(date('Y-m-d')) . ' pukul ' . date('H:i')));
        $this->SetX($this->lMargin);
        $this->Cell(0, 5, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }

    /** Kartu angka penting berjajar: [[label, nilai, keterangan], ...]; kartu pertama berwarna oranye */
    function kartu(array $kartu): void
    {
        $jarak = 4;
        $w = ($this->w - $this->lMargin - $this->rMargin - $jarak * (count($kartu) - 1)) / count($kartu);
        $h = 22;
        $x = $this->lMargin;
        $y = $this->GetY();
        foreach (array_values($kartu) as $i => [$label, $nilai, $ket]) {
            $utama = $i === 0;
            $this->SetFillColor(...($utama ? self::BRAND : self::LATAR_KARTU));
            $this->SetDrawColor(...($utama ? self::BRAND : self::GARIS));
            $this->kotak($x, $y, $w, $h, 3, 'DF');
            $this->SetXY($x + 4, $y + 3.5);
            $this->SetFont('Helvetica', 'B', 7);
            $this->SetTextColor(...($utama ? [255, 237, 213] : self::SAMAR));
            $this->Cell($w - 8, 4, self::t(mb_strtoupper($label)));
            $this->SetXY($x + 4, $y + 8.3);
            $this->SetFont('Helvetica', 'B', 14);
            $this->SetTextColor(...($utama ? [255, 255, 255] : self::TINTA));
            $this->Cell($w - 8, 7, self::t($nilai));
            $this->SetXY($x + 4, $y + 15.5);
            $this->SetFont('Helvetica', '', 7.5);
            $this->SetTextColor(...($utama ? [255, 237, 213] : self::SAMAR));
            $this->Cell($w - 8, 4, self::t($ket));
            $x += $w + $jarak;
        }
        $this->SetY($y + $h + 6);
    }

    /** Judul bagian dengan aksen oranye; pindah halaman dulu kalau sisa ruang terlalu sempit */
    function bagian(string $judul): void
    {
        if ($this->GetY() > $this->PageBreakTrigger - 30) $this->AddPage();
        $y = $this->GetY();
        $this->SetFillColor(...self::BRAND);
        $this->kotak($this->lMargin, $y + 1.2, 1.4, 4.6, 0.7, 'F');
        $this->SetXY($this->lMargin + 3.5, $y);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...self::TINTA);
        $this->Cell(0, 7, self::t($judul), 0, 1);
        $this->Ln(1.5);
    }

    /** Catatan kecil di bawah tabel */
    function catatan(string $teks): void
    {
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(...self::SAMAR);
        $this->MultiCell(0, 4, self::t($teks));
        $this->Ln(4);
    }

    /**
     * Tabel. $kolom: [[judul, lebar mm (0 = sisa lebar), 'L'|'R'|'P'], ...] — 'P' = porsi (angka persen digambar sebagai batang).
     * $baris: daftar baris (array teks per kolom); kunci '_warna' => '#hex' memberi titik warna di kolom pertama
     * dan warna batang porsi. $total: baris penutup bercetak tebal (opsional).
     * Teks panjang otomatis turun baris; kepala tabel diulang bila tabel berlanjut ke halaman berikutnya.
     */
    function tabel(array $kolom, array $baris, ?array $total = null): void
    {
        $sisa = $this->w - $this->lMargin - $this->rMargin - array_sum(array_column($kolom, 1));
        foreach ($kolom as &$k) { $k[1] = $k[1] ?: $sisa; $k[2] = $k[2] ?? 'L'; }
        unset($k);
        if ($this->GetY() + 15 > $this->PageBreakTrigger) $this->AddPage();
        $this->kolom = $kolom;
        $this->kepalaTabel();
        if (!$baris) {
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetTextColor(...self::SAMAR);
            $this->SetDrawColor(...self::GARIS);
            $this->Cell(array_sum(array_column($kolom, 1)), 7, 'Tidak ada data', 'B', 1);
        }
        $akhir = array_key_last($baris);
        foreach ($baris as $i => $b) $this->barisTabel($b, false, $total && $i === $akhir ? 8 : 0);   // baris terakhir tidak dipisah dari total
        if ($total) {
            $this->SetFont('Helvetica', 'B', 9);
            $this->barisTabel($total, true);
        }
        $this->kolom = [];
        $this->Ln(5);
    }

    private function kepalaTabel(): void
    {
        $this->SetFillColor(...self::LATAR);
        $this->kotak($this->lMargin, $this->GetY(), array_sum(array_column($this->kolom, 1)), 7, 1.8, 'F');
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetTextColor(...self::SAMAR);
        foreach ($this->kolom as [$judul, $w, $al]) $this->Cell($w, 7, self::t(mb_strtoupper($judul)), 0, 0, $al === 'P' ? 'L' : $al);
        $this->Ln(7.5);
        $this->SetFont('Helvetica', '', 9);
    }

    private function barisTabel(array $isi, bool $total = false, float $cadangan = 0): void
    {
        $warna = isset($isi['_warna']) ? self::rgb($isi['_warna']) : null;
        unset($isi['_warna']);
        $isi = array_map(fn($v) => is_string($v) ? self::t($v) : $v, array_values($isi));
        $geser = $warna ? 4.5 : 0;   // ruang untuk titik warna di kolom pertama

        $n = 1;
        foreach ($this->kolom as $i => [, $w, $al]) {
            if ($al !== 'P') $n = max($n, $this->jumlahBaris($w - ($i ? 0 : $geser), (string)($isi[$i] ?? '')));
        }
        $h = $n * 4.6 + 2.6;
        if ($this->GetY() + $h + $cadangan > $this->PageBreakTrigger) $this->AddPage();

        $x0 = $this->lMargin;
        $y = $this->GetY();
        $lebar = array_sum(array_column($this->kolom, 1));
        if ($total) {
            $this->SetFillColor(...self::BRAND_LEMBUT);
            $this->kotak($x0, $y + 0.6, $lebar, $h - 0.6, 1.8, 'F');
        }
        $this->SetTextColor(...($total ? self::TINTA : self::TEKS));
        $x = $x0;
        foreach ($this->kolom as $i => [, $w, $al]) {
            $v = $isi[$i] ?? '';
            if ($i === 0 && $warna) {
                $this->SetFillColor(...$warna);
                $this->kotak($x + 1.3, $y + 2.3, 2.6, 2.6, 0.7, 'F');
            }
            if ($al === 'P') {
                if (is_numeric($v)) $this->porsi($x, $y + 1.3, $w, (float)$v, $warna ?? self::BRAND);
            } else {
                $this->SetXY($x + ($i ? 0 : $geser), $y + 1.3);
                $this->MultiCell($w - ($i ? 0 : $geser), 4.6, (string)$v, 0, $al);
            }
            $x += $w;
        }
        if (!$total) {
            $this->SetDrawColor(...self::GARIS);
            $this->Line($x0, $y + $h, $x0 + $lebar, $y + $h);
        }
        $this->SetXY($x0, $y + $h);
    }

    /** Batang porsi (0–100%) dengan angka persen di kanan */
    private function porsi(float $x, float $y, float $w, float $persen, array $warna): void
    {
        $lebar_teks = 12;
        $panjang = $w - $lebar_teks - 2 * $this->cMargin;
        $this->SetFillColor(...self::LATAR);
        $this->kotak($x + $this->cMargin, $y + 1.2, $panjang, 2.2, 1.1, 'F');
        if ($persen > 0) {
            $this->SetFillColor(...$warna);
            $this->kotak($x + $this->cMargin, $y + 1.2, max(2.2, $panjang * min($persen, 100) / 100), 2.2, 1.1, 'F');
        }
        $this->SetXY($x + $w - $lebar_teks, $y);
        $this->Cell($lebar_teks, 4.6, round($persen) . '%', 0, 0, 'R');
    }

    /** Kotak bersudut bulat ($style: 'F' isi, 'D' garis, 'DF' keduanya) — dari contoh resmi FPDF (RoundedRect) */
    private function kotak(float $x, float $y, float $w, float $h, float $r, string $style = 'F'): void
    {
        $k = $this->k;
        $hp = $this->h;
        $op = $style === 'F' ? 'f' : ($style === 'DF' || $style === 'FD' ? 'B' : 'S');
        $arc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->busur($xc + $r * $arc, $yc - $r, $xc + $r, $yc - $r * $arc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->busur($xc + $r, $yc + $r * $arc, $xc + $r * $arc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->busur($xc - $r * $arc, $yc + $r, $xc - $r, $yc + $r * $arc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->busur($xc - $r, $yc - $r * $arc, $xc - $r * $arc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function busur(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }

    /** Jumlah baris yang dipakai MultiCell untuk teks $txt selebar $w (dari contoh resmi FPDF) */
    private function jumlahBaris(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = rtrim(str_replace("\r", '', $txt), "\n");
        $nb = strlen($s);
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c === ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep === -1) { if ($i === $j) $i++; }
                else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}
