<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;

class Supplier extends Model
{
    use ScopesCompanyQueries;

    protected $fillable =[
        "company_id", "name", "print_name", "image", "company_name", "contact_person", "vat_number", "tax_no", "tin_no",
        "email", "phone_number", "wa_number", "address", "city", "state", "postal_code", "country",
        "area_id", "agent_id", "opening_balance", "credit_days", "credit_limit", "cd_days", "cd_percent", "bill_by_bill",
        "pay_term_no", "pay_term_period", "purchase_defaults", "is_active"
    ];

    protected $casts = ['purchase_defaults' => 'array'];

    /** Standard purchase-bill terms this supplier's bills start from; blank entries mean "use the screen default". */
    public static function purchaseDefaultRules(): array
    {
        return [
            'purchase_defaults' => 'nullable|array',
            'purchase_defaults.payment_mode' => 'nullable|in:Cash,Credit',
            'purchase_defaults.nature' => 'nullable|in:product,service,mixed',
            'purchase_defaults.place_of_supply' => 'nullable|string|size:2',
            'purchase_defaults.reverse_charge' => 'nullable|boolean',
            'purchase_defaults.note' => 'nullable|string|max:500',
        ];
    }

    /** Drop blank entries so an untouched form stores null rather than a row of empty strings. */
    public static function cleanPurchaseDefaults(?array $defaults): ?array
    {
        $defaults = array_filter(array_intersect_key($defaults ?? [], array_flip(['payment_mode', 'nature', 'place_of_supply', 'reverse_charge', 'note'])),
            fn ($value) => $value !== null && $value !== '');
        if (isset($defaults['reverse_charge'])) $defaults['reverse_charge'] = (bool) $defaults['reverse_charge'];
        if (empty($defaults['reverse_charge'])) unset($defaults['reverse_charge']);
        return $defaults ?: null;
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function product()
    {
    	return $this->hasMany('App\Models\Product');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function returnPurchases()
    {
        return $this->hasMany(ReturnPurchase::class, 'supplier_id');
    }
}
