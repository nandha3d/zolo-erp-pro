<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PartyResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource->only(['id', 'name', 'company_name', 'email', 'phone_number', 'address', 'city', 'state', 'state_code', 'country', 'postal_code', 'tax_no', 'gstin', 'customer_group_id', 'is_active']);
        if ($this->resource->relationLoaded('customerGroup')) $data['customer_group'] = $this->resource->customerGroup?->only(['id', 'name']);
        return $data;
    }
}
