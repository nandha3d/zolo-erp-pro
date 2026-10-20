<?php

namespace App\Services\ERP;

use App\Models\Product;
use App\Services\Commercial\CommercialPermission;
use App\Services\Platform\CompanyContext;
use App\Services\Tax\GstSetupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

/** Create-only, reviewed CSV import using the normal master writer. */
class ReviewedProductImport
{
    public const COLUMNS = ['name', 'code', 'type', 'category_id', 'brand_id', 'unit_id', 'sale_unit_id',
        'purchase_unit_id', 'cost', 'price', 'tax_id', 'tax_category_id', 'hsn_code', 'negative_stock_policy'];

    public function authorize(CompanyContext $context, int $actor): void
    {
        app(CompanyWriteGuard::class)->context($context, $actor);
        foreach (['products-import', 'products-add'] as $permission) app(CommercialPermission::class)->assert($permission, $context, $actor);
    }

    public function preview(UploadedFile $file, CompanyContext $context, int $actor): object
    {
        $this->authorize($context, $actor);
        Validator::make(['file' => $file], ['file' => 'required|file|max:1024'])->validate();
        $stream = fopen($file->getRealPath(), 'rb'); $rows = []; $codes = []; $line = 1;
        try {
            $header = fgetcsv($stream);
            if (!$header) throw ValidationException::withMessages(['file' => 'CSV is empty.']);
            $header = array_map(fn ($value) => mb_strtolower(trim((string) $value)), $header);
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            if (count($header) !== count(array_unique($header)) || array_diff($header, self::COLUMNS)
                || array_diff(['name', 'code', 'type', 'category_id'], $header)) {
                throw ValidationException::withMessages(['file' => 'Use the template columns, including name, code, type and category_id. Unsupported fields cannot be imported.']);
            }
            while (($values = fgetcsv($stream)) !== false) {
                $line++;
                if (count($values) === 1 && trim((string) $values[0]) === '') continue;
                if (count($rows) >= 500) throw ValidationException::withMessages(['file' => 'Limit each reviewed file to 500 items.']);
                $row = ['row' => $line, 'data' => [], 'errors' => [], 'master_hash' => null];
                if (count($values) !== count($header)) $row['errors']['row'] = ['Column count does not match the header.'];
                else {
                    $data = array_combine($header, $values);
                    if (!mb_check_encoding(implode('', $values), 'UTF-8')) throw ValidationException::withMessages(['file' => 'Save the CSV using UTF-8 encoding.']);
                    try {
                        [$row['data'], $row['master_hash']] = $this->prepare($data, $context);
                        $key = mb_strtolower($row['data']['code']);
                        if (isset($codes[$key])) throw ValidationException::withMessages(['code' => 'Duplicate code within this file.']);
                        $codes[$key] = true;
                    } catch (ValidationException $error) { $row['data'] = $data; $row['errors'] = $error->errors(); }
                }
                $rows[] = $row;
            }
        } finally { fclose($stream); }
        if (!$rows) throw ValidationException::withMessages(['file' => 'CSV has no item rows.']);
        $id = DB::table('product_imports')->insertGetId(['company_id' => $context->companyId, 'branch_id' => $context->branchId,
            'user_id' => $actor, 'source_hash' => hash_file('sha256', $file->getRealPath()), 'status' => 'preview',
            'rows_json' => json_encode($rows, JSON_THROW_ON_ERROR), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        return $this->find($id, $context, $actor);
    }

    public function find(int $id, CompanyContext $context, int $actor): object
    {
        $this->authorize($context, $actor);
        $record = DB::table('product_imports')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
            ->where('user_id', $actor)->where('id', $id)->first();
        abort_unless($record, 404);
        $record->rows = json_decode($record->rows_json, true, 512, JSON_THROW_ON_ERROR);
        $record->results = json_decode($record->results_json ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $record->can_commit = $record->status === 'preview' && now()->lt($record->expires_at)
            && collect($record->rows)->every(fn ($row) => empty($row['errors']));
        return $record;
    }

    public function commit(int $id, CompanyContext $context, int $actor): array
    {
        $this->authorize($context, $actor);
        return DB::transaction(function () use ($id, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            DB::table('product_imports')->where('company_id', $context->companyId)->where('id', $id)->lockForUpdate()->first();
            $record = $this->find($id, $context, $actor);
            if ($record->status === 'committed') return $record->results;
            if (!$record->can_commit) throw ValidationException::withMessages(['preview' => 'Preview contains errors or expired. Upload a corrected file. No items were created.']);
            $results = [];
            foreach ($record->rows as $row) {
                [$data, $hash] = $this->prepare($row['data'], $context);
                if (!hash_equals($row['master_hash'], $hash)) throw ValidationException::withMessages(['preview' => 'Master data changed after review. Create a new preview before importing.']);
                $product = app(ProductMasterService::class)->create($data, $data, $context);
                $results[] = ['row' => $row['row'], 'id' => $product->id, 'code' => $product->code, 'name' => $product->name];
            }
            DB::table('product_imports')->where('id', $id)->update(['status' => 'committed', 'results_json' => json_encode($results, JSON_THROW_ON_ERROR), 'committed_at' => now(), 'updated_at' => now()]);
            return $results;
        });
    }

    private function prepare(array $input, CompanyContext $context): array
    {
        $data = array_intersect_key($input, array_flip(self::COLUMNS));
        foreach ($data as &$value) $value = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        unset($value);
        Validator::make($data, ['type' => 'required|in:standard,service,digital', 'category_id' => 'required|integer|min:1'])->validate();
        foreach (['category_id', 'brand_id', 'unit_id', 'sale_unit_id', 'purchase_unit_id', 'tax_id', 'tax_category_id'] as $field) {
            if (isset($data[$field]) && filter_var($data[$field], FILTER_VALIDATE_INT) !== false) $data[$field] = (int) $data[$field];
        }
        $data += ['cost' => 0, 'price' => 0, 'barcode_symbology' => 'C128', 'tax_method' => 1, 'image' => 'zummXD2dvAtI.png',
            'is_batch' => false, 'is_imei' => false, 'is_variant' => null, 'profit_margin_type' => 'percentage', 'profit_margin' => 0];
        $data['cost'] ??= 0; $data['price'] ??= 0;
        if ($data['type'] !== 'standard') $data['unit_id'] = $data['sale_unit_id'] = $data['purchase_unit_id'] = 0;
        $data = app(ProductMasterService::class)->validateCreation($data, $data, $context);
        if (Product::forCompany($context)->whereRaw('LOWER(code) = ?', [mb_strtolower($data['code'])])->exists()) {
            throw ValidationException::withMessages(['code' => 'Code already exists, including inactive items. Import creates new masters only.']);
        }
        $definitions = [];
        foreach (['category_id' => 'categories', 'brand_id' => 'brands', 'unit_id' => 'units', 'sale_unit_id' => 'units',
            'purchase_unit_id' => 'units', 'tax_id' => 'taxes', 'tax_category_id' => 'tax_categories'] as $field => $table) {
            if (!empty($data[$field])) $definitions[$field] = (array) DB::table($table)->where('id', $data[$field])->first();
        }
        if (!empty($data['tax_category_id'])) {
            $definitions['gst_rates'] = DB::table('tax_rates')->where('company_id', $context->companyId)
                ->where('tax_category_id', $data['tax_category_id'])->orderBy('id')->get()->toArray();
        }
        $data['hsn_code'] = app(GstSetupService::class)->productCode($data['hsn_code'] ?? null, $data['category_id'], $data['type'], $context);
        return [$data, hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR))];
    }
}
