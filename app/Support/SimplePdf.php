<?php

namespace App\Support;

/**
 * Générateur PDF 1.4 minimal et sans dépendance : texte en Helvetica (police standard, aucune
 * incorporation), filets et rectangles. Suffisant pour des documents tabulaires (factures).
 * Encodage WinAnsi : les accents français et le symbole « € » sont pris en charge.
 */
class SimplePdf
{
    private const LARGEUR = 595.28; // A4
    private const HAUTEUR = 841.89;

    /** @var array<int, string> flux de contenu par page */
    private array $pages = [''];

    public function ligne(float $x, float $y, string $texte, float $taille = 11, bool $gras = false): self
    {
        $this->ajouter(sprintf("BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", $gras ? 'F2' : 'F1', $taille, $x, self::HAUTEUR - $y, $this->echapper($texte)));

        return $this;
    }

    /** Texte aligné à droite : $xDroit est le bord droit du texte. */
    public function droite(float $xDroit, float $y, string $texte, float $taille = 11, bool $gras = false): self
    {
        // Largeur approchée d'Helvetica (≈ 0,5 em par caractère) : suffisante pour des montants.
        $largeur = mb_strlen($texte) * $taille * 0.5;

        return $this->ligne($xDroit - $largeur, $y, $texte, $taille, $gras);
    }

    public function filet(float $x1, float $y, float $x2, float $epaisseur = 0.6): self
    {
        $this->ajouter(sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $epaisseur, $x1, self::HAUTEUR - $y, $x2, self::HAUTEUR - $y));

        return $this;
    }

    public function rectangle(float $x, float $y, float $largeur, float $hauteur, float $gris = 0.93): self
    {
        $this->ajouter(sprintf("%.2F g %.2F %.2F %.2F %.2F re f 0 g\n", $gris, $x, self::HAUTEUR - $y - $hauteur, $largeur, $hauteur));

        return $this;
    }

    public function render(): string
    {
        $objets = [];
        $nPages = count($this->pages);
        // 1 catalogue, 2 pages, 3 police, 4 police grasse, puis (page, contenu) par page.
        $objets[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $nPages; $i++) {
            $kids[] = (5 + 2 * $i) . ' 0 R';
        }
        $objets[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nPages . ' >>';
        $objets[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objets[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($this->pages as $i => $contenu) {
            $page = 5 + 2 * $i;
            $objets[$page] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::LARGEUR, self::HAUTEUR, $page + 1);
            $objets[$page + 1] = '<< /Length ' . strlen($contenu) . " >>\nstream\n" . $contenu . 'endstream';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objets as $numero => $corps) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$corps}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objets) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer << /Size ' . (count($objets) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function ajouter(string $operateurs): void
    {
        $this->pages[count($this->pages) - 1] .= $operateurs;
    }

    private function echapper(string $texte): string
    {
        $win = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texte);
        $win = $win === false ? preg_replace('/[^\x20-\x7E]/', '?', $texte) : $win;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $win);
    }
}
