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
            <div class="col-md-7">
                <h3 class="font-weight-bold text-dark m-0">Digital Catalogue &amp; QR Menu Generator</h3>
                <p class="text-muted small m-0">Generate contactless digital menus and live catalogue QR codes for dine-in tables and marketing counters.</p>
            </div>
            <div class="col-md-5 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createQrModal">
                    <i class="dripicons-plus"></i> + Generate New QR Code
                </button>
            </div>
        </div>

        <div class="row">
            @forelse($qrs as $qr)
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm rounded-lg text-center p-4 h-100 bg-white">
                        <div class="mb-3">
                            <img src="{{ $qr->qr_code_path }}" alt="QR Code" class="img-fluid rounded border p-2" style="max-width: 180px;">
                        </div>
                        <h5 class="font-weight-bold text-dark mb-1">{{ $qr->title }}</h5>
                        <p class="text-muted small mb-3">Slug: <code>/menu/{{ $qr->slug }}</code></p>
                        
                        <div class="mt-auto pt-2 d-flex justify-content-center gap-2">
                            <a href="{{ $qr->qr_code_path }}" download="qr-{{ $qr->slug }}.png" class="btn btn-outline-primary btn-sm px-3 mr-2">
                                <i class="dripicons-download"></i> Download QR
                            </a>
                            <a href="{{ url('/menu/' . $qr->slug) }}" target="_blank" class="btn btn-outline-secondary btn-sm px-3">
                                <i class="dripicons-preview"></i> Preview
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-lg p-5 text-center bg-white">
                        <i class="dripicons-qr-code text-muted" style="font-size: 48px; opacity: 0.3;"></i>
                        <h5 class="font-weight-bold text-dark mt-3">No Catalogue QR Codes Created</h5>
                        <p class="text-muted small">Generate your first digital menu or product catalogue QR code for table-side ordering.</p>
                        <button type="button" class="btn btn-primary btn-sm mx-auto" data-toggle="modal" data-target="#createQrModal" style="width: 220px;">
                            + Generate QR Code
                        </button>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>

<!-- Create QR Modal -->
<div id="createQrModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('qr.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Generate Digital Catalogue QR Code</h5>
                    <button type="button" data-dismiss="modal" class="close text-white opacity-75"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">QR Title / Location *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Table 1, Dine-in Counter, Reception Stand" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Linked Warehouse / Store</label>
                        <select name="warehouse_id" class="form-control">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Assign Table (Optional for Restaurants/Cafes)</label>
                        <select name="table_id" class="form-control">
                            <option value="">None (General Catalogue)</option>
                            @foreach($tables as $tbl)
                                <option value="{{ $tbl->id }}">{{ $tbl->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Brand Theme Accent Color</label>
                        <input type="color" name="theme_color" class="form-control" value="#4f46e5" style="height: 42px;">
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">Generate QR</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
