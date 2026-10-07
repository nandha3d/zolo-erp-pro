<div class="modal fade" id="material-party-modal" tabindex="-1" role="dialog" aria-labelledby="material-party-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="material-party-form" class="modal-content">
            @csrf
            <input type="hidden" name="party_type" value="{{ $partyType }}">
            <div class="modal-header">
                <h5 id="material-party-title">New {{ ucfirst($partyType) }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="material-party-error" class="alert alert-danger" role="alert" style="display:none"></div>
                @foreach(['name' => 'Name', 'company_name' => 'Company', 'phone_number' => 'Phone', 'address' => 'Address', 'city' => 'City'] as $field => $label)
                    <div class="form-group">
                        <label for="material-party-{{ $field }}">{{ $label }} *</label>
                        <input id="material-party-{{ $field }}" name="{{ $field }}" class="form-control" required>
                    </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save {{ ucfirst($partyType) }}</button>
            </div>
        </form>
    </div>
</div>
