<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** The next number of a kind of document, per year, never repeated: RFQ-2026-000012. */
class DocumentNumbers
{
    public function next(string $kind): string
    {
        $year = (int) now()->year;

        $n = DB::transaction(function () use ($kind, $year) {
            DB::table('document_numbers')->insertOrIgnore(['kind' => $kind, 'year' => $year, 'last' => 0]);
            $row = DB::table('document_numbers')->where(['kind' => $kind, 'year' => $year])->lockForUpdate()->first();
            DB::table('document_numbers')->where('id', $row->id)->update(['last' => $row->last + 1]);

            return $row->last + 1;
        });

        return sprintf('%s-%d-%06d', $kind, $year, $n);
    }
}
