<?php

namespace App\Services\Pdf;

/**
 * A very small PDF writer: A4 pages of text and rules in the built-in Helvetica faces. It needs no library and no font file, which also means
 * it can only draw Latin text: anything else is turned into its closest Latin letters (or left out) by ascii().
 */
class MiniPdf
{
    public const WIDTH = 595;
    public const HEIGHT = 842;

    /** @var list<string> the drawing commands of each page */
    private array $pages = [''];

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, bool $right = false): void
    {
        $text = self::ascii($text);
        if ($right) {
            $x -= $this->width($text, $size, $bold);
        }
        $this->add(sprintf("BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $x, self::HEIGHT - $y, self::escape($text)));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $weight = 0.6): void
    {
        $this->add(sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $weight, $x1, self::HEIGHT - $y1, $x2, self::HEIGHT - $y2));
    }

    public function newPage(): void
    {
        $this->pages[] = '';
    }

    /** The finished file. */
    public function output(): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $kids = [];
        $next = 5;
        foreach ($this->pages as $content) {
            $page = $next++;
            $stream = $next++;
            $kids[] = "{$page} 0 R";
            $objects[$page] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::WIDTH.' '.self::HEIGHT."] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$stream} 0 R >>";
            $objects[$stream] = '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $n => $body) {
            $offsets[$n] = strlen($out);
            $out .= "{$n} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $out .= sprintf("%010d 00000 n \n", $offset);
        }

        return $out.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /** Latin letters only: accents are dropped to their base letter and anything else (such as Arabic) is left out. */
    public static function ascii(string $text): string
    {
        $text = strtr($text, ['–' => '-', '—' => '-', '’' => "'", '“' => '"', '”' => '"']);
        // One character at a time: on some systems (glibc, i.e. Linux) a letter with no Latin form comes back as "?" instead of nothing,
        // so a name in Arabic would print as "????". A character that cannot be written in Latin letters is left out, whatever the system does.
        // Accents first (é becomes e + a mark, the mark is dropped), so every system gives the same plain letter.
        if (class_exists(\Normalizer::class)) {
            $text = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text) ?? $text;
        }
        $out = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (strlen($char) === 1) {
                $out .= $char;

                continue;
            }
            $latin = @iconv('UTF-8', 'ASCII//TRANSLIT', $char);
            if (is_string($latin) && $latin !== '' && ! str_contains($latin, '?')) {
                $out .= $latin;
            }
        }

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\x20-\x7E]/', '', $out)));
    }

    /** Roughly how wide the text is, so it can be right-aligned (Helvetica averages about half the type size per letter). */
    public function width(string $text, float $size, bool $bold = false): float
    {
        return strlen($text) * $size * ($bold ? 0.56 : 0.52);
    }

    private static function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }

    private function add(string $command): void
    {
        $this->pages[count($this->pages) - 1] .= $command;
    }
}
