@extends('backend.layout.main') @section('content')

<x-error-message key="message" />
<x-error-message key="not_permitted" />
<x-purchase-command-bar active="import" title="Import bill from CSV">
    <a href="{{ url('sample_file/sample_purchase_products.csv') }}" class="btn btn-outline-secondary"><i class="dripicons-download"></i> Sample CSV</a>
</x-purchase-command-bar>

@push('css')
<style>
    .pi-grid { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 12px; padding: 0 12px; }
    .pi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
    .pi-card h2 { font-size: 13.5px; font-weight: 700; margin: 0 0 10px; color: #0f172a; display: flex; align-items: center; gap: 6px; }
    .pi-card h2 .num { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 6px; background: var(--z-primary-soft, #fbf4fb); color: var(--z-primary-hover, #631f64); font-size: 11px; }
    .pi-card label { font-size: 12px; font-weight: 600; margin-bottom: 3px; color: #334155; }
    .pi-card .form-group { margin-bottom: 8px; }
    .pi-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
    .pi-span { grid-column: 1 / -1; }
    .pi-drop { position: relative; border: 1.5px dashed var(--z-primary-text, #7d287e); border-radius: 8px; background: var(--z-primary-soft, #fbf4fb); padding: 14px; text-align: center; cursor: pointer; transition: background .15s; }
    .pi-drop.is-over { background: var(--z-primary-soft, #fbf4fb); }
    .pi-drop input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .pi-drop strong { display: block; font-size: 13px; color: var(--z-primary-hover, #631f64); }
    .pi-drop span { font-size: 11.5px; color: #64748b; }
    .pi-cols { width: 100%; font-size: 12px; border-collapse: collapse; }
    .pi-cols th, .pi-cols td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; text-align: left; }
    .pi-cols th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; background: #f8fafc; }
    .pi-cols code { font-size: 11.5px; color: var(--z-primary-hover, #631f64); background: none; padding: 0; }
    .pi-preview { max-height: 190px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 8px; }
    .pi-preview table { margin: 0; }
    .pi-status { font-size: 12px; margin-top: 6px; }
    .pi-actions { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
    .pi-actions .hint { margin-left: auto; font-size: 11.5px; color: #64748b; }
    .pi-note { font-size: 11.5px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 6px 8px; margin-top: 8px; }
    body.dark-mode .pi-card { background: #0f172a; border-color: #1e293b; }
    body.dark-mode .pi-card h2 { color: #f8fafc; }
    body.dark-mode .pi-card label { color: #94a3b8; }
    body.dark-mode .pi-drop { background: #1e1b2e; border-color: var(--z-primary-hover, #631f64); }
    body.dark-mode .pi-cols th { background: #111827; }
    body.dark-mode .pi-cols th, body.dark-mode .pi-cols td { border-color: #1e293b; }
    body.dark-mode .pi-note { background: #2a2110; border-color: #78350f; color: #fbbf24; }
    @media (max-width: 991px) { .pi-grid { grid-template-columns: 1fr; } }
</style>
@endpush

{!! Form::open(['route' => 'purchase.import', 'method' => 'post', 'files' => true, 'id' => 'purchase-form', 'data-enter-flow' => 'true']) !!}
<div class="pi-grid">
    <div class="pi-card">
        <h2><span class="num">1</span> Bill details</h2>
        <div class="pi-fields">
            <div class="form-group">
                <label for="pi-supplier">Supplier</label>
                <select id="pi-supplier" name="supplier_id" class="form-control"><option value="">Select supplier…</option>
                    @foreach($lims_supplier_list as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->company_name && $supplier->company_name !== $supplier->name ? ' ('.$supplier->company_name.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="pi-warehouse">Warehouse *</label>
                <select id="pi-warehouse" required name="warehouse_id" class="form-control"><option value="">Select warehouse…</option>
                    @foreach($lims_warehouse_list as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($loop->first && $lims_warehouse_list->count() === 1)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="pi-status">Status</label>
                <select id="pi-status" name="status" class="form-control">
                    <option value="1">{{ __('db.Recieved') }}</option>
                    <option value="3">{{ __('db.Pending') }}</option>
                    <option value="4">{{ __('db.Ordered') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label for="pi-tax">Order tax</label>
                <select id="pi-tax" class="form-control" name="order_tax_rate">
                    <option value="0">{{ __('db.No Tax') }}</option>
                    @foreach($lims_tax_list as $tax)<option value="{{ $tax->rate }}">{{ $tax->name }}</option>@endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="pi-discount">Bill discount (₹)</label>
                <input id="pi-discount" type="number" name="order_discount" class="form-control" step="any" min="0" placeholder="0">
            </div>
            <div class="form-group">
                <label for="pi-shipping">Freight (₹)</label>
                <input id="pi-shipping" type="number" name="shipping_cost" class="form-control" step="any" min="0" placeholder="0">
            </div>
            <div class="form-group pi-span">
                <label for="pi-note">Note</label>
                <input id="pi-note" type="text" class="form-control" name="note" maxlength="1000" placeholder="Supplier invoice no., remarks">
            </div>
            <div class="form-group pi-span">
                <label for="pi-document">Attach supplier invoice <span class="text-muted font-weight-normal">(jpg, png, pdf, csv, docx, xlsx, txt)</span></label>
                <input id="pi-document" type="file" name="document" class="form-control">
                @if($errors->has('extension'))<small class="text-danger">{{ $errors->first('extension') }}</small>@endif
            </div>
        </div>
        <input type="hidden" name="total_qty" value="0">
        <input type="hidden" name="total_discount" value="0">
        <input type="hidden" name="total_tax" value="0">
        <input type="hidden" name="total_cost" value="0">
        <input type="hidden" name="item" value="0">
        <input type="hidden" name="order_tax" value="0">
        <input type="hidden" name="grand_total" value="0">
        <input type="hidden" name="paid_amount" value="{{ number_format(0, $general_setting->decimal, '.', '') }}">
        <input type="hidden" name="payment_status" value="1">
    </div>

    <div class="pi-card">
        <h2><span class="num">2</span> Item lines (CSV)</h2>
        <div class="pi-drop" id="pi-drop">
            <input id="pi-file" type="file" name="file" accept=".csv,text/csv" required>
            <strong id="pi-file-name">Drop the CSV here or click to choose</strong>
            <span>First row is the header. One row per item.</span>
        </div>
        <div class="pi-status" id="pi-status" role="status" aria-live="polite"></div>
        <div class="pi-preview" id="pi-preview" hidden></div>
        <table class="pi-cols mt-2" id="pi-format">
            <thead><tr><th>#</th><th>Column</th><th>What to enter</th><th>Example</th></tr></thead>
            <tbody>
                <tr><td>1</td><td><code>product_code</code></td><td>Item code from the item master</td><td>PS-100</td></tr>
                <tr><td>2</td><td><code>quantity</code></td><td>Quantity in the unit of column 3</td><td>500</td></tr>
                <tr><td>3</td><td><code>purchase_unit_code</code></td><td>Unit code (PCS, KG, CASE…)</td><td>PCS</td></tr>
                <tr><td>4</td><td><code>cost</code></td><td>Rate per unit before tax</td><td>35.80</td></tr>
                <tr><td>5</td><td><code>discount_per_unit</code></td><td>Discount per unit, 0 if none</td><td>0</td></tr>
                <tr><td>6</td><td><code>tax_name</code></td><td>Tax name exactly as in Tax settings, or <i>no tax</i></td><td>no tax</td></tr>
                <tr><td>7</td><td><code>imei_number</code></td><td>Optional, serial-tracked items only</td><td></td></tr>
            </tbody>
        </table>
        <div class="pi-note">Imported bills take tax from column 6, not from the item's GST category. For GST bills with CGST/SGST/IGST and ITC, use <a href="{{ route('purchases.index') }}">Bills › New (F2)</a>.</div>
        <div class="pi-actions">
            <button type="submit" class="btn btn-primary" id="submit-button"><i class="dripicons-upload"></i> Import bill</button>
            <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <span class="hint">All rows import together; one bad row stops the whole file.</span>
        </div>
    </div>
</div>
{!! Form::close() !!}

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#purchase").siblings('a').attr('aria-expanded', 'true');
    $("ul#purchase").addClass("show");
    $("ul#purchase #purchase-import-menu").addClass("active");

    (function () {
        var expected = ['product_code', 'quantity', 'purchase_unit_code', 'cost', 'discount_per_unit', 'tax_name'];
        var $drop = $('#pi-drop'), $status = $('#pi-status'), $preview = $('#pi-preview');
        $('#pi-file').on('dragenter dragover', function () { $drop.addClass('is-over'); })
            .on('dragleave drop', function () { $drop.removeClass('is-over'); })
            .on('change', function () {
                var file = this.files[0];
                $preview.prop('hidden', true).empty(); $status.text('').removeClass('text-danger text-success');
                if (!file) { $('#pi-file-name').text('Drop the CSV here or click to choose'); return; }
                $('#pi-file-name').text(file.name);
                if (!/\.csv$/i.test(file.name)) { $status.addClass('text-danger').text('Choose a .csv file.'); return; }
                var reader = new FileReader();
                reader.onload = function () {
                    // Preview only: simple comma split, enough to spot a wrong column order before upload.
                    var rows = String(reader.result).split(/\r?\n/).filter(function (line) { return $.trim(line) !== ''; })
                        .map(function (line) { return line.split(','); });
                    if (!rows.length) { $status.addClass('text-danger').text('The file is empty.'); return; }
                    var header = rows[0].map(function (cell) { return $.trim(cell).toLowerCase(); });
                    var wrong = expected.filter(function (name, i) { return header[i] !== name; });
                    var lines = rows.length - 1;
                    $status.addClass(wrong.length ? 'text-danger' : 'text-success')
                        .text(wrong.length ? 'Header does not match the format below (check: ' + wrong.join(', ') + ').' : lines + ' item line' + (lines === 1 ? '' : 's') + ' ready to import.');
                    var $table = $('<table class="pi-cols">');
                    $table.append($('<thead>').append($('<tr>').append(rows[0].map(function (cell) { return $('<th>').text(cell); }))));
                    var $body = $('<tbody>').appendTo($table);
                    rows.slice(1, 9).forEach(function (row) { $body.append($('<tr>').append(row.map(function (cell) { return $('<td>').text(cell); }))); });
                    if (lines > 8) $body.append($('<tr>').append($('<td>').attr('colspan', rows[0].length).addClass('text-muted').text('… ' + (lines - 8) + ' more')));
                    $preview.append($table).prop('hidden', false);
                    $('#pi-format').prop('hidden', !wrong.length);
                };
                reader.readAsText(file);
            });
    })();
</script>
@endpush
