<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Reads a spreadsheet of products: a .csv, or an .xlsx saved from Excel (first sheet, first row are the column names). Returns plain rows; the
 * products themselves are created by the same code and rules as the product form, so an imported product can never be one the form would refuse.
 */
class ProductImport
{
    public const MAX_ROWS = 500;

    private const COLUMNS = ['name', 'slug', 'category', 'base_price', 'sku', 'description', 'brand', 'warranty_years', 'stock_cartons', 'units_per_carton'];

    /** @return list<array<string, string>> the rows, keyed by column name; a row's number is its index + 2 (the header is row 1) */
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $table = $ext === 'xlsx' ? $this->xlsx($file->getRealPath()) : $this->csv($file->getRealPath());

        $header = array_map(fn ($h) => strtolower(trim(str_replace(' ', '_', (string) $h))), array_shift($table) ?? []);
        if (! in_array('name', $header, true) || ! in_array('base_price', $header, true) || ! in_array('category', $header, true)) {
            throw ValidationException::withMessages(['file' => ['The first row must name the columns, and include name, category and base_price.']]);
        }

        $rows = [];
        foreach ($table as $cells) {
            $row = [];
            foreach ($header as $i => $name) {
                if (in_array($name, self::COLUMNS, true) && isset($cells[$i]) && trim((string) $cells[$i]) !== '') {
                    $row[$name] = trim((string) $cells[$i]);
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => [__('At most :n products at a time.', ['n' => self::MAX_ROWS])]]);
        }

        return $rows;
    }

    private function csv(string $path): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);       // the byte-order mark Excel adds
        $first = strtok($text, "\n") ?: '';
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $out = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, $text);
        rewind($h);
        while (($r = fgetcsv($h, 0, $sep)) !== false) {
            $out[] = $r;
        }
        fclose($h);

        return $out;
    }

    private function xlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => ['This is not a valid Excel file.']]);
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach ((new \SimpleXMLElement($xml))->si as $si) {
                $shared[] = $si->t !== null && (string) $si->t !== '' ? (string) $si->t : implode('', array_map('strval', iterator_to_array($si->r->t ?? [], false)));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw ValidationException::withMessages(['file' => ['The workbook has no sheet to read.']]);
        }

        $rows = [];
        foreach ((new \SimpleXMLElement($sheet))->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $idx = 0;
                foreach (str_split($m[1] ?? 'A') as $ch) {
                    $idx = $idx * 26 + (ord($ch) - 64);
                }
                $v = (string) $c->v;
                $cells[$idx - 1] = ((string) $c['t']) === 's' ? ($shared[(int) $v] ?? '') : (((string) $c['t']) === 'inlineStr' ? (string) $c->is->t : $v);
            }
            if ($cells) {
                $line = [];
                for ($i = 0; $i <= max(array_keys($cells)); $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            }
        }

        return $rows;
    }
}
