@extends('backend.layout.main')
@section('content')
<section class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-3"><h1 class="h4 mb-0">Dispatch Register</h1><a class="btn btn-outline-primary btn-sm" href="{{ route('sales.index') }}">Sales workspace</a></div>
    <nav class="nav nav-pills mb-3" aria-label="Dispatch document types">
        @foreach($tabs as $key => $definition)<a class="nav-link {{ $key === $tab ? 'active' : '' }}" href="{{ route('dispatch-register.index', ['tab' => $key]) }}" @if($key === $tab) aria-current="page" @endif>{{ $definition[0] }}</a>@endforeach
    </nav>
    <form method="get" class="form-inline mb-3"><input type="hidden" name="tab" value="{{ $tab }}"><label for="dispatch-search" class="mr-2">Document reference</label><input id="dispatch-search" name="q" maxlength="100" value="{{ request('q') }}" class="form-control mr-2"><button class="btn btn-primary btn-sm">Search</button><a class="btn btn-outline-primary btn-sm ml-2" href="{{ route($route) }}">Open {{ $label }} workspace</a></form>
    <div class="table-responsive"><table class="table table-striped table-sm"><caption>{{ $label }} in the selected company and branch</caption><thead><tr><th>Document</th><th>Date</th><th>Party / sale</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($rows ?? [] as $row)<tr><td>{{ $tab === 'dc' ? $row->challan_no : $row->reference_no }}</td><td>{{ $tab === 'dc' ? $row->challan_date->toDateString() : $row->created_at?->toDateString() }}</td><td>{{ $tab === 'dc' ? $row->customer?->name : ($row->sale_id ? 'Sale #'.$row->sale_id : 'Courier challan') }}</td><td>{{ str_replace('_', ' ', $row->status) }}</td><td>@if($tab === 'dc')<a href="{{ route('delivery-challans.show', $row->id) }}">Open Customer DC</a>@else<a href="{{ route($route, ['reference_no' => $row->reference_no]) }}">Manage document</a>@endif</td></tr>
    @empty<tr><td colspan="5">No dispatch documents match.</td></tr>@endforelse
    </tbody></table></div>
    @if($rows){{ $rows->links() }}@endif
</section>
@endsection
