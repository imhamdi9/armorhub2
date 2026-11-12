<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Every product belongs to a category: normalise the spelling of existing ones and file the rest under "general". */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->select('id', 'category')->orderBy('id')->each(function ($row) {
            $slug = Str::slug((string) $row->category);
            $slug = $slug === '' ? 'general' : $slug;

            if ($slug !== $row->category) {
                DB::table('products')->where('id', $row->id)->update(['category' => $slug]);
            }
        });
    }

    public function down(): void {}
};
