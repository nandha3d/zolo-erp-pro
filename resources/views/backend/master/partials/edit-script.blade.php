{{--
    Reuses #createModal for editing. Each Edit button carries the record as JSON
    (data-record) and its update URL (data-update-url); fields are filled by name.
--}}
@push('scripts')
<script>
(function ($) {
    var $modal = $('#createModal');
    var $form = $modal.find('form');
    var $title = $modal.find('.modal-title');
    var $submit = $form.find('[type=submit]');
    var createAction = $form.attr('action');
    var createTitle = $title.html();
    var createSubmit = $submit.html();
    var editTitle = @json($editTitle);

    function setEditOnly(on) {
        $form.find('.js-edit-only').toggleClass('d-none', !on).find('input').prop('disabled', !on);
    }

    // The layout turns every select into bootstrap-select, so redraw each enhanced one after a value change.
    function refreshPickers() {
        $form.find('select').each(function () {
            if ($(this).data('selectpicker')) $(this).selectpicker('refresh');
        });
    }

    $(document).on('click', '.js-master-edit', function () {
        var record = $(this).data('record');
        $form[0].reset();
        $form.attr('action', $(this).data('update-url'));
        if (!$form.find('input[name=_method]').length) {
            $form.append('<input type="hidden" name="_method" value="PUT">');
        }
        setEditOnly(true);
        $form.find('[name]').each(function () {
            if (this.type === 'hidden' || !(this.name in record)) return;
            var value = record[this.name];
            if (this.type === 'checkbox') this.checked = !!Number(value);
            else $(this).val(value === null ? '' : value);
        });
        refreshPickers();
        $title.html('<i class="dripicons-pencil mr-1"></i> ' + editTitle);
        $submit.text('Update');
        $modal.modal('show');
    });

    $modal.on('hidden.bs.modal', function () {
        $form[0].reset();
        $form.attr('action', createAction);
        $form.find('input[name=_method]').remove();
        setEditOnly(false);
        refreshPickers();
        $title.html(createTitle);
        $submit.html(createSubmit);
    });
})(jQuery);
</script>
@endpush
