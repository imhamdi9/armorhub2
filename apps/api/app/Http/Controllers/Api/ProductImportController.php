<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Add many products at once from a spreadsheet: all of them or none, with every problem listed by row. */
class ProductImportController extends Controller
{
    public function store(Request $request, ProductImport $import, ProductController $products)
    {
        $request->validate(['file' => 'required|file|max:5120|extensions:csv,xlsx', 'dry_run' => 'nullable|boolean']);
        $rows = $import->read($request->file('file'));
        $dry = $request->boolean('dry_run');

        $errors = [];
        $created = [];
        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $row += ['slug' => Str::slug($row['name'] ?? '').'-'.Str::lower(Str::random(5))];
                $sub = $request->duplicate([], $row, [], [], [], $request->server->all());
                $sub->setJson(new \Symfony\Component\HttpFoundation\InputBag($row));    // read from here when the upload itself came as JSON-typed
                $sub->setUserResolver($request->getUserResolver());
                try {
                    $created[] = $products->store($sub)->getData(true)['id'] ?? null;
                } catch (ValidationException $e) {
                    $errors[] = ['row' => $i + 2, 'messages' => collect($e->errors())->flatten()->all()];
                }
            }
            if ($errors || $dry) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($errors) {
            return response()->json(['message' => 'Some rows have problems; nothing was imported.', 'imported' => 0, 'errors' => $errors], 422);
        }

        return response()->json(['message' => $dry ? 'The file is fine.' : 'Imported.', 'dry_run' => $dry, 'imported' => $dry ? 0 : count($created), 'rows' => count($rows)], $dry ? 200 : 201);
    }
}
