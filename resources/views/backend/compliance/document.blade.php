@extends('backend.compliance.layout')
@section('title', $document['title'].' '.$document['number'])
@section('content')
<section class="panel"><p>{{ $document['party_name'] }} · {{ $document['date'] }}</p><p class="document-total">{{ $document['currency'] }} {{ number_format($document['grand_total'],4,'.','') }}</p>
<nav class="actions" aria-label="Print formats">
<a href="{{ url('/compliance/documents/'.$kind.'/'.$id.'/print?format=a4') }}">A4 preview</a>
<a href="{{ url('/compliance/documents/'.$kind.'/'.$id.'/print?format=thermal') }}">Thermal preview</a>
<a href="{{ url('/compliance/documents/'.$kind.'/'.$id.'/print?format=dot_matrix') }}">Dot matrix text</a>
<a href="{{ url('/compliance/documents/'.$kind.'/'.$id.'/print?format=a4&output=pdf') }}">PDF</a>
<a href="{{ url('/compliance/documents/'.$kind.'/'.$id.'/print?format=dot_matrix&output=escp') }}">ESC/P payload</a>
</nav>
@foreach($profiles as $profile)<p><a href="{{ url('/compliance/documents/'.$kind.'/'.$id.'/print?profile_id='.$profile->id) }}">{{ $profile->name }}</a></p>@endforeach
@if(in_array($kind,['sale','purchase']))<p><a href="{{ url('/compliance/'.$kind.'/'.$id.'/return') }}">Create return or financial note</a></p>@endif
</section>
<section class="panel"><h2>Send document</h2><form class="fields" method="POST" action="{{ url('/compliance/documents/'.$kind.'/'.$id.'/dispatch') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string) Illuminate\Support\Str::uuid()) }}">
<label for="dispatch-channel">Channel<select id="dispatch-channel" name="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label>
<label for="dispatch-recipient">Recipient<input id="dispatch-recipient" name="recipient" value="{{ old('recipient') }}" required maxlength="191" placeholder="Email or +919876543210"></label>
<button type="submit">Queue delivery</button></form></section>
<section class="panel"><h2>Delivery history</h2><div class="table-scroll"><table><thead><tr><th scope="col">Channel</th><th scope="col">Recipient</th><th scope="col">Status</th><th scope="col">Attempts</th><th scope="col">Action</th></tr></thead><tbody>
@forelse($dispatches as $log)<tr><td>{{ ucfirst($log->channel) }}</td><td>{{ $log->recipient }}</td><td>{{ str_replace('_',' ',$log->status) }}@if($log->failure_category)<br><small>{{ str_replace('_',' ',$log->failure_category) }}</small>@endif</td><td>{{ $log->attempts }}</td><td>
@if($log->status === 'failed')<form method="POST" action="{{ url('/compliance/dispatch/'.$log->id.'/retry') }}">@csrf<button type="submit">Retry delivery</button></form>@elseif($log->status === 'delivery_unknown')Check provider before sending again.@endif
</td></tr>@empty<tr><td colspan="5">No deliveries yet. Select a channel and recipient above.</td></tr>@endforelse</tbody></table></div></section>
@endsection
