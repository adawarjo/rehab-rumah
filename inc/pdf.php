<?php
/**
 * Pembuat PDF berbasis FPDF (lib/fpdf): kepala halaman berisi nama proyek, judul bagian dan tabel.
 * Teks dari aplikasi (UTF-8) diubah otomatis ke Windows-1252, encoding font bawaan FPDF.
 */
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

class PDF extends FPDF
{
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

    function Header()
    {
        $this->SetTextColor(28, 25, 23);
        $this->SetFont('Helvetica', 'B', 13);
        $this->Cell(0, 7, self::t($this->proyek));
        $this->SetX($this->lMargin);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(0, 7, self::t($this->judul), 0, 1, 'R');
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(120, 113, 108);
        $this->Cell(0, 5, self::t($this->alamat));
        $this->SetX($this->lMargin);
        $this->Cell(0, 5, self::t($this->subjudul), 0, 1, 'R');
        $y = $this->GetY() + 1.5;
        $this->SetDrawColor(180, 83, 9);
        $this->SetLineWidth(0.6);
        $this->Line($this->lMargin, $y, $this->w - $this->rMargin, $y);
        $this->SetLineWidth(0.2);
        $this->SetY($y + 4);
        if ($this->kolom) $this->kepalaTabel();
    }

    function Footer()
    {
        $this->SetY(-13);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(120, 113, 108);
        $this->Cell(0, 5, self::t('Dicetak ' . tgl(date('Y-m-d')) . ' pukul ' . date('H:i')));
        $this->SetX($this->lMargin);
        $this->Cell(0, 5, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }

    /** Judul bagian; pindah halaman dulu kalau sisa ruang terlalu sempit */
    function bagian(string $judul): void
    {
        if ($this->GetY() > $this->PageBreakTrigger - 30) $this->AddPage();
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(146, 64, 14);
        $this->Cell(0, 8, self::t($judul), 0, 1);
    }

    /** Catatan kecil di bawah tabel */
    function catatan(string $teks): void
    {
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(120, 113, 108);
        $this->MultiCell(0, 4, self::t($teks));
        $this->Ln(3);
    }

    /**
     * Tabel. $kolom: [[judul, lebar mm (0 = sisa lebar), 'L'|'R'], ...]
     * $baris: daftar baris (array teks per kolom). $total: baris penutup bercetak tebal (opsional).
     * Teks panjang otomatis turun baris; kepala tabel diulang bila tabel berlanjut ke halaman berikutnya.
     */
    function tabel(array $kolom, array $baris, ?array $total = null): void
    {
        $sisa = $this->w - $this->lMargin - $this->rMargin - array_sum(array_column($kolom, 1));
        foreach ($kolom as &$k) { $k[1] = $k[1] ?: $sisa; $k[2] = $k[2] ?? 'L'; }
        unset($k);
        if ($this->GetY() + 14 > $this->PageBreakTrigger) $this->AddPage();
        $this->kolom = $kolom;
        $this->kepalaTabel();
        if (!$baris) {
            $this->SetTextColor(120, 113, 108);
            $this->SetDrawColor(231, 229, 228);
            $this->Cell(array_sum(array_column($kolom, 1)), 7, 'Tidak ada data', 'B', 1);
        }
        foreach (array_values($baris) as $i => $b) $this->barisTabel($b, $i % 2 === 1);
        if ($total) {
            $this->SetFont('Helvetica', 'B', 9);
            $this->barisTabel($total, false, true);
        }
        $this->kolom = [];
        $this->Ln(4);
    }

    private function kepalaTabel(): void
    {
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetFillColor(180, 83, 9);
        $this->SetTextColor(255, 255, 255);
        foreach ($this->kolom as [$judul, $w, $al]) $this->Cell($w, 7, self::t($judul), 0, 0, $al, true);
        $this->Ln();
        $this->SetFont('Helvetica', '', 9);
    }

    private function barisTabel(array $isi, bool $belang, bool $total = false): void
    {
        $isi = array_map([self::class, 't'], $isi);
        $n = 1;
        foreach ($this->kolom as $i => [, $w]) $n = max($n, $this->jumlahBaris($w, $isi[$i] ?? ''));
        $h = $n * 4.6 + 2;
        if ($this->GetY() + $h > $this->PageBreakTrigger) $this->AddPage();

        $x0 = $this->lMargin;
        $y = $this->GetY();
        $lebar = array_sum(array_column($this->kolom, 1));
        if ($belang) {
            $this->SetFillColor(250, 248, 245);
            $this->Rect($x0, $y, $lebar, $h, 'F');
        }
        $this->SetTextColor(41, 37, 36);
        $x = $x0;
        foreach ($this->kolom as $i => [, $w, $al]) {
            $this->SetXY($x, $y + 1);
            $this->MultiCell($w, 4.6, $isi[$i] ?? '', 0, $al);
            $x += $w;
        }
        $this->SetDrawColor(...($total ? [120, 113, 108] : [231, 229, 228]));
        $this->Line($x0, $total ? $y : $y + $h, $x0 + $lebar, $total ? $y : $y + $h);
        $this->SetXY($x0, $y + $h);
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
