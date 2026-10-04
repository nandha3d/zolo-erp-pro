<?php

namespace App\Services\Documents;

use App\Models\{Sale, Purchase, Returns, ReturnPurchase, Company, Product};
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Templates consume a frozen read-only array. Totals always come from the posted source. */
class DocumentRenderingService
{
    public function source(string $kind, int $id, CompanyContext $context): Sale|Purchase|Returns|ReturnPurchase
    {
        $model = match ($kind) { 'sale' => Sale::class, 'purchase' => Purchase::class, 'sale_note' => Returns::class,
            'purchase_note' => ReturnPurchase::class, default => throw ValidationException::withMessages(['document' => 'Unsupported document.']) };
        $source = in_array($kind, ['sale', 'purchase'], true) ? $model::visibleIn($context)->findOrFail($id)
            : $model::forCompany($context)->where('branch_id', $context->branchId)->findOrFail($id);
        if (!$source->posted_at || (int) $source->branch_id !== $context->branchId) {
            throw ValidationException::withMessages(['document' => 'Print and dispatch require a posted document in this branch.']);
        }
        return $source;
    }

    public function capture(Sale|Purchase|Returns|ReturnPurchase $source, string $kind, CompanyContext $context): array
    {
        $sale = str_starts_with($kind, 'sale'); $note = str_ends_with($kind, '_note');
        $company = Company::findOrFail($context->companyId);
        $party = ($sale ? \App\Models\Customer::class : \App\Models\Supplier::class)::forCompany($context)
            ->findOrFail($source->{$sale ? 'customer_id' : 'supplier_id'});
        $tax = $source->tax_snapshot_json ?? [];
        $lines = $note ? $source->products : ($sale ? $source->productSales : $source->productPurchases);
        $rows = [];
        foreach ($lines as $line) {
            if ((int) $line->company_id !== $context->companyId) throw new \LogicException('Document contains a foreign line.');
            $product = Product::forCompany($context)->findOrFail($line->product_id); $snapshot = $line->tax_snapshot_json ?? [];
            $rows[] = ['name' => $snapshot['product_name'] ?? $product->name, 'code' => $snapshot['product_code'] ?? $product->code,
                'qty' => (float) $line->qty, 'price' => (float) $line->{$sale ? 'net_unit_price' : 'net_unit_cost'},
                'taxable_value' => $snapshot['taxable_value'] ?? (float) $line->total - (float) $line->tax,
                'tax_rate' => $snapshot['rate'] ?? (float) $line->tax_rate, 'cess_rate' => $snapshot['cess_rate'] ?? 0,
                'tax' => (float) $line->tax, 'total' => (float) $line->total, 'hsn_sac' => $snapshot['hsn_sac'] ?? $product->hsn_code,
                'cgst' => $snapshot['cgst'] ?? 0, 'sgst' => $snapshot['sgst'] ?? 0, 'igst' => $snapshot['igst'] ?? 0, 'cess' => $snapshot['cess'] ?? 0];
        }
        $currency = DB::table('currencies')->where('id', $company->base_currency_id)->first();
        return ['kind' => $kind, 'title' => $note ? ucfirst($source->note_type).' note' : ($sale ? ($tax ? ($tax['registration_type'] === 'composition' ? 'Bill of supply' : 'Tax invoice') : 'Invoice') : 'Purchase bill'),
            'number' => $source->reference_no, 'date' => $source->created_at->toDateString(),
            'company_name' => $tax[$sale ? 'seller_name' : 'buyer_name'] ?? $company->legal_name,
            'party_name' => $tax[$sale ? 'buyer_name' : 'seller_name'] ?? $party->name,
            'company_address' => $tax['company_address'] ?? '', 'party_address' => $tax['party_address'] ?? $party->address ?? '', 'company_gstin' => $tax['registration_gstin'] ?? '',
            'party_gstin' => $tax[$sale ? 'buyer_gstin' : 'seller_gstin'] ?? '', 'place_of_supply' => $tax['place_of_supply'] ?? '',
            'reverse_charge' => $tax['reverse_charge'] ?? false, 'currency' => $currency->code ?? 'INR',
            'lines' => $rows, 'total_tax' => (float) $source->total_tax + (float) $source->order_tax,
            'discount' => (float) ($source->order_discount ?? 0), 'shipping' => (float) ($source->shipping_cost ?? 0),
            'grand_total' => (float) $source->grand_total, 'source_number' => $note ? $source->attributes_json['source_no'] : null,
            'transport' => array_intersect_key($source->attributes_json ?? [], array_flip(['transport_name', 'lr_number', 'vehicle_number'])),
        ];
    }

    public function dto(string $kind, int $id, CompanyContext $context): array
    {
        $source = $this->source($kind, $id, $context);
        if (!$source->document_snapshot_json) throw ValidationException::withMessages(['document' => 'Retained document needs reviewed print-snapshot migration.']);
        return $source->document_snapshot_json + ['reversed_at' => $source->reversed_at?->toDateString()];
    }

    public function profile(string $kind, CompanyContext $context, ?int $id, string $format): array
    {
        if ($id) {
            $row = DB::table('print_profiles')->where('company_id', $context->companyId)->where('document_type', $kind)->where('id', $id)->first();
            abort_unless($row, 404); $format = $row->format;
            $settings = json_decode($row->settings_json ?? '{}', true, 512, JSON_THROW_ON_ERROR);
            $profile = ['format' => $format, 'width' => $row->paper_width, 'lines' => $row->height_lines,
                'columns' => $settings['columns'] ?? 80, 'copies' => json_decode($row->copies_json ?? '["Original"]', true)];
        } else $profile = ['format' => $format, 'width' => $format === 'thermal' ? 80 : 210, 'lines' => 68, 'columns' => 80, 'copies' => ['Original']];
        validator($profile, ['format' => 'required|in:a4,thermal,dot_matrix', 'width' => 'required|integer|min:58|max:300',
            'lines' => 'required|integer|min:20|max:200', 'columns' => 'required|integer|min:60|max:200',
            'copies' => 'required|array|min:1|max:5', 'copies.*' => 'string|max:60'])->validate();
        if ($format === 'dot_matrix') app(\App\Services\Platform\CapabilityService::class)->assertEnabled('printing.dot_matrix', $context);
        return $profile;
    }

    public function html(array $document, array $profile): string
    {
        return view('backend.compliance.print', compact('document', 'profile'))->render();
    }

    public function fixedWidth(array $document, array $profile): string
    {
        $columns = $profile['columns']; $height = $profile['lines']; $output = [];
        $safe = function ($value) use ($columns) {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $value);
            return substr(preg_replace('/[\x00-\x1F\x7F]/', ' ', $text), 0, $columns);
        };
        $body = [];
        foreach ($document['lines'] as $line) {
            $body[] = $safe($line['code'].' '.$line['name']);
            $body[] = $safe('Qty '.self::amount($line['qty']).' Rate '.self::amount($line['price']).' Tax '.self::amount($line['tax']).' Total '.self::amount($line['total']));
            $body[] = $safe('HSN/SAC '.$line['hsn_sac'].' CGST '.self::amount($line['cgst']).' SGST '.self::amount($line['sgst']).' IGST '.self::amount($line['igst']).' Cess '.self::amount($line['cess']));
        }
        foreach ($profile['copies'] as $copy) {
            $pages = array_chunk($body, $height - 13);
            foreach ($pages ?: [[]] as $index => $lines) {
                $page = [$safe($document['company_name']), $safe($document['title'].' '.$document['number']),
                    $safe('Date '.$document['date'].' '.$copy.' Page '.($index + 1)),
                    $safe('GSTIN '.$document['company_gstin'].' POS '.$document['place_of_supply']),
                    $safe($document['party_name'].' GSTIN '.$document['party_gstin']), $safe($document['party_address']),
                    str_repeat('-', $columns), ...$lines, str_repeat('-', $columns),
                    $safe('Total tax '.$document['currency'].' '.self::amount($document['total_tax'])),
                    $safe('Discount '.self::amount($document['discount']).' Freight '.self::amount($document['shipping'])),
                    $safe('Grand total '.$document['currency'].' '.self::amount($document['grand_total'])),
                    $safe($document['source_number'] ? 'Against '.$document['source_number'] : '')];
                $output[] = implode("\r\n", array_pad($page, $height, ''));
            }
        }
        return implode("\f", $output);
    }

    public static function amount(float $value): string { return number_format($value, 4, '.', ''); }
}
