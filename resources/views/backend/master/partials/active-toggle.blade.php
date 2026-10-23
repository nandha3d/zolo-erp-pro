{{-- Shown only while the create modal is in edit mode; disabled inputs are not submitted on create. --}}
<div class="form-group js-edit-only d-none mb-0">
    <input type="hidden" name="is_active" value="0" disabled>
    <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" disabled>
        <label class="custom-control-label font-weight-bold" for="is_active">Active</label>
        <small class="form-text text-muted">Inactive entries stay on old bills but are not offered on new ones</small>
    </div>
</div>
