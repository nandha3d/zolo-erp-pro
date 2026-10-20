<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;

    protected $fillable =[

        "reference_no", "user_id", "biller_id", "supplier_id", "customer_id", "warehouse_id", "item", "total_qty", "total_discount", "total_tax", "total_price", "order_tax_rate", "order_tax", "order_discount", "shipping_cost", "grand_total", "quotation_status","document", "note", "next_follow_up"
    ];

    protected $casts = ['next_follow_up' => 'date:Y-m-d', 'converted_at' => 'datetime', 'conversion_review_required' => 'boolean'];

    public function hasConversionTracking(): bool
    {
        return app(\App\Support\Database\SchemaCache::class)->hasColumn($this->getConnection(), $this->getTable(), 'conversion_review_required');
    }

    public function canConvert(): bool
    {
        if (!$this->hasConversionTracking()) return (int) $this->quotation_status === 1;
        return !$this->conversion_review_required && !$this->converted_document_id && in_array((int) $this->quotation_status, [1, 2], true);
    }

    public function assertDeletable(): void
    {
        if ($this->hasConversionTracking() && ($this->converted_document_id || $this->conversion_review_required)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quotation' => 'Converted quotations and quotations requiring conversion review must remain in history.',
            ]);
        }
    }

    /** Called only under the existing posting transaction and quotation row lock. */
    public function recordConversion(string $kind, int $documentId): void
    {
        $attributes = ['quotation_status' => 2];
        if ($this->hasConversionTracking()) $attributes += ['converted_kind' => $kind, 'converted_document_id' => $documentId, 'converted_at' => now()];
        $this->forceFill($attributes)->save();
    }

    /** Serialize editor changes so the audit records the date actually replaced, including concurrent edits. */
    public function revise(array $attributes): static
    {
        return $this->getConnection()->transaction(function () use ($attributes) {
            $current = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $current->update($attributes);
            return $current;
        });
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $quotation) => $quotation->assertDeletable());
        static::created(function (self $quotation) {
            if ($quotation->next_follow_up) $quotation->recordFollowUp(null);
        });
        static::updated(function (self $quotation) {
            if ($quotation->wasChanged('next_follow_up')) $quotation->recordFollowUp($quotation->getRawOriginal('next_follow_up'));
        });
    }

    private function recordFollowUp(?string $previous): void
    {
        // Older fixture schemas have no follow-up field; unchanged legacy saves never reach this path.
        \Illuminate\Support\Facades\DB::table('quotation_follow_ups')->insert([
            'company_id' => $this->company_id, 'quotation_id' => $this->id,
            'previous_date' => $previous ? substr($previous, 0, 10) : null,
            'next_date' => $this->next_follow_up?->toDateString(), 'actor_id' => auth()->id(), 'created_at' => now(),
        ]);
    }

    public function biller()
    {
    	return $this->belongsTo('App\Models\Biller');
    }

    public function customer()
    {
    	return $this->belongsTo('App\Models\Customer');
    }

    public function supplier()
    {
    	return $this->belongsTo('App\Models\Supplier');
    }

    public function user()
    {
    	return $this->belongsTo('App\Models\User');
    }

    public function warehouse()
    {
        return $this->belongsTo('App\Models\Warehouse');
    }

    public function products()
    {
        return $this->hasMany(ProductQuotation::class,'quotation_id');
    }
}
