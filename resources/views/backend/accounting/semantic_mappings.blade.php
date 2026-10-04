@extends('backend.layout.main')
@section('content')

@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('message') }}
    </div>
@endif

<section class="forms">
    <div class="container-fluid">
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <h3 class="font-weight-bold text-dark m-0">Semantic Account Mappings</h3>
                <p class="text-muted small m-0">Maintain company account mapping metadata. Automatic postings currently use system account codes and subtypes; mapping activation requires a reviewed posting integration.</p>
            </div>
            <div class="col-md-4 text-right">
                <button type="submit" form="mapping-form" class="btn btn-primary">
                    <i class="dripicons-checkmark"></i> Save Account Mappings
                </button>
            </div>
        </div>

        <form action="{{ route('accounting.semantic-mappings.update') }}" method="POST" id="mapping-form">
            @csrf
            <div class="card border-0 shadow-sm rounded-lg">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted uppercase small">
                                <tr>
                                    <th>Semantic ERP Role</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th style="width: 380px;">Assigned General Ledger Account</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mappings as $map)
                                    <tr>
                                        <td>
                                            <span class="badge badge-dark px-2 py-1 font-mono text-uppercase">
                                                {{ str_replace('_', ' ', $map->semantic_role) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info px-2 py-1 text-uppercase">{{ $map->category }}</span>
                                        </td>
                                        <td class="small text-muted">{{ $map->description }}</td>
                                        <td>
                                            <select name="mappings[{{ $map->semantic_role }}]" class="form-control form-control-sm">
                                                @foreach($accounts as $acc)
                                                    <option value="{{ $acc->id }}" {{ $map->account_id == $acc->id ? 'selected' : '' }}>
                                                        {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $map->account_code ? 'badge-secondary' : 'badge-warning' }} px-2 py-1">{{ $map->account_code ? 'Metadata only' : 'Review required' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light p-3 text-right border-0">
                    <button type="submit" class="btn btn-primary px-5 font-weight-bold">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</section>

@endsection
