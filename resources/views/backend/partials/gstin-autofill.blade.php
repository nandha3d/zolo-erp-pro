{{-- GSTIN-first entry for party forms: decode state offline, recall known parties, fill only empty fields. --}}
{{-- Params: $gstinField (input name of the GSTIN box), $nameField (input name of the party name box). --}}
<script type="text/javascript">
(function () {
    var $gstin = $('input[name="{{ $gstinField }}"]');
    if (!$gstin.length) return;
    var $form = $gstin.closest('form');
    var $status = $('<small class="d-block mt-1" role="status" aria-live="polite"></small>').insertAfter($gstin);
    var fieldMap = {
        name: '{{ $nameField }}', company_name: 'company_name', address: 'address', city: 'city',
        state: 'state', postal_code: 'postal_code', phone_number: 'phone_number', email: 'email'
    };

    function fillIfEmpty(field, value) {
        var $input = $form.find('[name="' + field + '"]');
        if (value && $input.length && !$.trim($input.val())) $input.val(value).trigger('change');
    }

    function lookup() {
        var gstin = $.trim($gstin.val()).toUpperCase();
        $gstin.val(gstin);
        // Other tax numbers (foreign VAT etc.) are left alone; only 15-character GSTINs are looked up.
        if (gstin.length !== 15) { $status.text('').removeClass('text-danger text-success'); return; }
        $status.text('Checking GSTIN...').removeClass('text-danger text-success');
        $.post('{{ route("parties.gst-lookup") }}', { _token: '{{ csrf_token() }}', gstin: gstin }, null, 'json')
            .done(function (res) {
                if (!res || !res.success || !res.is_valid) {
                    $status.text((res && res.error) || 'Invalid GSTIN.').removeClass('text-success').addClass('text-danger');
                    return;
                }
                fillIfEmpty('state', res.state_name);
                fillIfEmpty('country', 'India');
                var message = 'Valid GSTIN: ' + res.state_name + (res.constitution ? ', ' + res.constitution : '') + '.';
                if (res.party) {
                    $.each(fieldMap, function (key, field) { fillIfEmpty(field, res.party[key]); });
                    message += res.party.source === 'live_api'
                        ? ' Registered details filled.'
                        : ' Matches existing ' + (res.party.party_type || 'party') + ' "' + res.party.name + '"; empty fields filled.';
                } else if (res.provider_message) {
                    message += ' (' + res.provider_message + ')';
                }
                $status.text(message).removeClass('text-danger').addClass('text-success');
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'GSTIN lookup failed. Enter details manually.';
                $status.text(msg).removeClass('text-success').addClass('text-danger');
            });
    }

    // Reverse direction: company name -> pick a GSTIN, which then runs the lookup above.
    var $company = $form.find('input[name="company_name"]');
    var $matches = $('<div class="mt-1" role="status" aria-live="polite"></div>').insertAfter($company);
    $company.on('change', function () {
        var name = $.trim($company.val());
        $matches.empty();
        if ($.trim($gstin.val()) || name.length < 3) return;
        $matches.append($('<small class="text-muted">').text('Searching GSTIN...'));
        $.post('{{ route("parties.gst-search-name") }}', { _token: '{{ csrf_token() }}', name: name }, null, 'json')
            .done(function (res) {
                $matches.empty();
                if (!res || !res.matches || !res.matches.length) {
                    $matches.append($('<small class="text-muted">').text(res && res.provider
                        ? 'No GSTIN found for this legal name.'
                        : 'No saved party with this name. GSTIN search by name needs the Masters India API.'));
                    return;
                }
                $matches.append($('<small class="d-block text-muted">').text('Pick GSTIN:'));
                $.each(res.matches, function (_, m) {
                    var label = m.gstin + ' - ' + (m.trade_name || m.legal_name) + (m.city ? ', ' + m.city : '') + (m.status ? ' (' + m.status + ')' : '');
                    $('<button type="button" class="btn btn-sm btn-outline-primary mr-1 mb-1">').text(label)
                        .on('click', function () { $gstin.val(m.gstin); $matches.empty(); lookup(); })
                        .appendTo($matches);
                });
            })
            .fail(function () { $matches.empty(); });
    });

    $gstin.attr({ autocomplete: 'off', placeholder: '15-character GSTIN' })
        .on('change', lookup)
        .on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); lookup(); }
        });
})();
</script>
