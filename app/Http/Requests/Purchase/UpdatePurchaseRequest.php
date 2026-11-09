<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items') && is_array($this->items)) {
            $productCodes = $this->product_code ?? [];
            $qtys = $this->qty ?? [];
            $productIds = $this->product_id ?? [];
            $costs = $this->net_unit_cost ?? [];
            $subtotals = $this->subtotal ?? [];
            $taxes = $this->tax ?? [];
            $taxRates = $this->tax_rate ?? [];
            $units = $this->purchase_unit ?? [];

            if (empty($productCodes)) {
                foreach ($this->items as $item) {
                    $productCodes[] = $item['product_code'] ?? ($item['code'] ?? '');
                    $qtys[] = $item['qty'] ?? 1;
                    $productIds[] = $item['product_id'] ?? ($item['id'] ?? 0);
                    $costs[] = $item['net_unit_cost'] ?? ($item['cost'] ?? 0);
                    $subtotals[] = $item['total'] ?? ($item['subtotal'] ?? 0);
                    $taxes[] = $item['tax'] ?? 0;
                    $taxRates[] = $item['tax_rate'] ?? 0;
                    $units[] = $item['purchase_unit'] ?? ($item['unit'] ?? 'Unit');
                }

                $this->merge([
                    'product_code' => $productCodes,
                    'qty' => $qtys,
                    'product_id' => $productIds,
                    'net_unit_cost' => $costs,
                    'subtotal' => $subtotals,
                    'tax' => $taxes,
                    'tax_rate' => $taxRates,
                    'purchase_unit' => $units,
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_code' => 'required|array',
            'product_code.*' => 'required|string',
            'qty' => 'required|array',
            'qty.*' => 'required|numeric|min:0.01',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
        ];
    }
    
    public function messages()
    {
        return [
            'warehouse_id.required' => 'Select a warehouse',
            'product_code.required' => 'Please insert a product.',
            'qty.required' => 'At least one quantity is required.',
            'qty.array' => 'The quantities must be in an array format.',
            'qty.*.required' => 'Each quantity must be provided.',
            'qty.*.numeric' => 'Each quantity must be a valid number.',
            'qty.*.min' => 'Each quantity must be at least 1.',
        ];
    }
}
