/* Unknown item on the Sales, Purchase, and Material Document entry pages.
 * When a typed item name matches no product or the user chooses "+ Create as new product" from the search dropdown,
 * open this page's Quick Create Product form with that name pre-filled.
 * The bill/document stays open; the new product fills the row it was typed on (or a new row from the search bar). */
(function ($) {
    'use strict';

    // Directly opens the Quick Create item modal without any browser confirm dialog
    window.zoloOpenQuickCreateProduct = function (name, $row) {
        name = String(name || '').trim();
        var $modal = $('#quick-create-item-modal');
        if (!$modal.length) return false;
        $('#btn-create-item-modal').trigger('click'); // resets the form, generates a code and opens modal
        $modal.data('replace-row', $row && $row.length ? $row : null);
        if (name) {
            $('#quick-item-name').val(name).trigger('input');
        }
        $modal.one('shown.bs.modal', function () {
            $('#quick-item-name').trigger('focus');
        });
        return true;
    };

    // Prompts or directly opens Quick Create modal for unknown products
    window.zoloUnknownProduct = function (name, $row, skipConfirm) {
        name = String(name || '').trim();
        var $modal = $('#quick-create-item-modal');
        if (!name || !$modal.length) return false;
        if (!skipConfirm && !window.confirm('"' + name + '" is not in your products.\n\nAdd it as a new product now?')) {
            return false;
        }
        return window.zoloOpenQuickCreateProduct(name, $row);
    };

    // The row a new product should fill, once; cleared when the form closes without saving.
    window.zoloUnknownProductRow = function () {
        var $modal = $('#quick-create-item-modal'), $row = $modal.data('replace-row');
        $modal.removeData('replace-row');
        return $row && $row.closest('body').length ? $row : null;
    };
    $(document).on('hidden.bs.modal', '#quick-create-item-modal', function () { $(this).removeData('replace-row'); });
})(jQuery);
