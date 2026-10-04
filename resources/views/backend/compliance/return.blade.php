@extends('backend.compliance.layout')
@section('title','Return / note against '.$source->reference_no)
@section('content')
<p>Choose original lines. Quantity returns move stock; financial notes only change value. Tax uses the original snapshot.</p>
<form method="POST" action="{{ url('/compliance/'.$kind.'/'.$source->id.'/notes') }}" id="return-form">@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string) Illuminate\Support\Str::uuid()) }}">
<section class="panel fields">
<label for="note-date">Note date<input type="date" id="note-date" name="business_date" value="{{ old('business_date',now()->toDateString()) }}" min="{{ $source->created_at->toDateString() }}" required></label>
<label for="note-type">Note type<select id="note-type" name="note_type"><option value="{{ $kind === 'sale' ? 'credit' : 'debit' }}">{{ $kind === 'sale' ? 'Credit' : 'Debit' }} — reduce invoice</option><option value="{{ $kind === 'sale' ? 'debit' : 'credit' }}">{{ $kind === 'sale' ? 'Debit' : 'Credit' }} — increase invoice</option></select></label>
<label for="adjustment-type">Adjustment<select id="adjustment-type" name="adjustment_type"><option value="quantity">Quantity return</option><option value="rate_difference">Rate difference</option><option value="discount">Discount</option><option value="tax_correction">Tax correction</option></select></label>
<label for="note-reason">Reason<textarea id="note-reason" name="reason" required minlength="3" maxlength="500">{{ old('reason') }}</textarea></label></section>
<section class="panel"><h2>Original lines</h2><div class="return-lines">
@foreach($lines as $i => $line)<fieldset class="return-line"><legend><label for="select-{{ $i }}"><input id="select-{{ $i }}" name="items[{{ $i }}][selected]" type="checkbox" value="1" @checked(old('items.'.$i.'.selected'))> {{ $line->product->name }} · {{ $line->product->code }}</label></legend>
<input type="hidden" name="items[{{ $i }}][line_id]" value="{{ $line->id }}"><p>Original quantity {{ $line->qty }} · Line total {{ number_format($line->total,4,'.','') }}</p><div class="fields">
<label for="qty-{{ $i }}">Return quantity<input id="qty-{{ $i }}" name="items[{{ $i }}][qty]" type="number" min="0.0001" max="{{ $line->qty }}" step="0.0001" value="{{ old('items.'.$i.'.qty') }}"></label>
<label for="amount-{{ $i }}">Financial change before tax<input id="amount-{{ $i }}" name="items[{{ $i }}][amount]" type="number" min="0" step="0.0001" value="{{ old('items.'.$i.'.amount') }}"></label>
<label for="disposition-{{ $i }}">Disposition<select id="disposition-{{ $i }}" name="items[{{ $i }}][disposition]">@foreach(['restock','quarantine','damaged','expired','quality_reject'] as $value)<option value="{{ $value }}">{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select></label>
<label for="return-warehouse-{{ $i }}">Return warehouse<select id="return-warehouse-{{ $i }}" name="items[{{ $i }}][warehouse_id]">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouse->id == $source->warehouse_id)>{{ $warehouse->name }}{{ $warehouse->is_quarantine ? ' · Quarantine' : '' }}</option>@endforeach</select></label></div>
@foreach($stock->filter(fn($m) => ($m->attributes_json['commercial_line_id'] ?? 0) == $line->id && $m->stock_identity_id) as $movement)
<label class="identity-choice" for="stock-{{ $movement->id }}"><input type="checkbox" id="stock-{{ $movement->id }}" name="items[{{ $i }}][stock_line_ids][]" value="{{ $movement->id }}"> {{ $movement->identity->identity_no }} · {{ $movement->identity->identity_type }}</label>@endforeach
</fieldset>@endforeach</div></section><button type="submit" class="primary">Submit note</button><p>High-value or late notes await approval. Until approval, stock and accounts stay unchanged.</p></form>
@endsection
