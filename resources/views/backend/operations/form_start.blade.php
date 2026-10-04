<form method="post" action="{{ url('/operations/'.$action) }}" class="ops-form" id="{{ $formId }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ old('form_id') === $formId ? old('idempotency_key') : (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="form_id" value="{{ $formId }}">
