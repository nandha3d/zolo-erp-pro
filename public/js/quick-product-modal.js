/**
 * Quick Product Modal Logic
 * Controls dynamic responsive grid spans, toggle buttons, and real-time margin/price calculation.
 */
(function ($) {
    'use strict';

    function applyQuickModalDynamicGridSpans() {
        var isCombo = $('#quick-item-type').val() === 'combo';

        var sections = [
            {
                id: 'quick-initial-stock-section',
                $el: $('#quick-initial-stock-section'),
                isWide: false,
                active: !isCombo && $('#quick_chk_initial_stock').is(':checked')
            },
            {
                id: 'quick-diffPrice-section',
                $el: $('#quick-diffPrice-section'),
                isWide: false,
                active: !isCombo && $('#quick_is_diffPrice').is(':checked')
            },
            {
                id: 'quick-imei-section',
                $el: $('#quick-imei-section'),
                isWide: false,
                active: !isCombo && $('#quick_is_imei').is(':checked')
            },
            {
                id: 'quick-promo-container',
                $el: $('#quick-promo-container'),
                isWide: false,
                active: $('#quick_promotion').is(':checked')
            },
            {
                id: 'quick-variant-section',
                $el: $('#quick-variant-section'),
                isWide: true,
                active: !isCombo && $('#quick_is_variant').is(':checked')
            },
            {
                id: 'quick-batch-section',
                $el: $('#quick-batch-section'),
                isWide: true,
                active: !isCombo && $('#quick_is_batch').is(':checked')
            }
        ];

        var spanClasses = 'zolo-grid-span-1 zolo-grid-span-2 zolo-grid-span-3 zolo-grid-span-4';
        sections.forEach(function (s) {
            if (s.$el.length) {
                s.$el.removeClass(spanClasses);
            }
        });

        var activeSections = sections.filter(function (s) {
            return s.active && s.$el.length;
        });

        var count = activeSections.length;
        if (count === 0) return;

        if (count === 1) {
            activeSections[0].$el.addClass('zolo-grid-span-4');
        } else if (count === 2) {
            var wideList = activeSections.filter(function (s) { return s.isWide; });
            var compactList = activeSections.filter(function (s) { return !s.isWide; });

            if (wideList.length === 1 && compactList.length === 1) {
                compactList[0].$el.addClass('zolo-grid-span-1');
                wideList[0].$el.addClass('zolo-grid-span-3');
            } else {
                activeSections[0].$el.addClass('zolo-grid-span-2');
                activeSections[1].$el.addClass('zolo-grid-span-2');
            }
        } else if (count === 3) {
            var wideList = activeSections.filter(function (s) { return s.isWide; });
            var compactList = activeSections.filter(function (s) { return !s.isWide; });

            if (wideList.length === 1 && compactList.length === 2) {
                compactList[0].$el.addClass('zolo-grid-span-1');
                compactList[1].$el.addClass('zolo-grid-span-1');
                wideList[0].$el.addClass('zolo-grid-span-2');
            } else if (wideList.length === 0) {
                activeSections[0].$el.addClass('zolo-grid-span-2');
                activeSections[1].$el.addClass('zolo-grid-span-1');
                activeSections[2].$el.addClass('zolo-grid-span-1');
            } else if (wideList.length === 2 && compactList.length === 1) {
                wideList[0].$el.addClass('zolo-grid-span-2');
                wideList[1].$el.addClass('zolo-grid-span-2');
                compactList[0].$el.addClass('zolo-grid-span-4');
            }
        } else if (count >= 4) {
            activeSections.forEach(function (s) {
                s.$el.addClass(s.isWide ? 'zolo-grid-span-2' : 'zolo-grid-span-1');
            });
        }
    }

    $(function () {
        // Switch toggles within quick modal
        $('#quick-create-item-modal .zolo-toggle-pill input[type="checkbox"]').on('change', function () {
            var $pill = $(this).closest('.zolo-toggle-pill');
            $pill.toggleClass('is-active', $(this).is(':checked'));

            var id = $(this).attr('id');
            var targetMap = {
                'quick_chk_initial_stock': '#quick-initial-stock-section',
                'quick_is_diffPrice': '#quick-diffPrice-section',
                'quick_is_variant': '#quick-variant-section',
                'quick_is_batch': '#quick-batch-section',
                'quick_is_imei': '#quick-imei-section',
                'quick_promotion': '#quick-promo-container'
            };

            var target = targetMap[id];
            if (target) {
                if ($(this).is(':checked')) {
                    $(target).removeClass('u-d-none').hide().slideDown(200);
                } else {
                    $(target).slideUp(200, function () {
                        $(this).addClass('u-d-none');
                    });
                }
            }

            applyQuickModalDynamicGridSpans();
        });

        // Price, Cost, and Profit Margin 2-way calculations
        function calculateQuickPrice() {
            var cost = parseFloat($('#quick-item-cost').val()) || 0;
            var margin = parseFloat($('#quick-item-margin').val()) || 0;
            var marginType = $('#quick-item-margin-type').val() || 'percentage';

            if (cost >= 0) {
                var price = 0;
                if (marginType === 'percentage') {
                    price = cost + (cost * margin / 100);
                } else {
                    price = cost + margin;
                }
                $('#quick-item-price').val(price.toFixed(2));
            }
        }

        function calculateQuickMargin() {
            var cost = parseFloat($('#quick-item-cost').val()) || 0;
            var price = parseFloat($('#quick-item-price').val()) || 0;
            var marginType = $('#quick-item-margin-type').val() || 'percentage';

            if (cost > 0 && price >= cost) {
                var margin = 0;
                if (marginType === 'percentage') {
                    margin = ((price - cost) / cost) * 100;
                } else {
                    margin = price - cost;
                }
                $('#quick-item-margin').val(margin.toFixed(2));
            }
        }

        $('#quick-item-cost, #quick-item-margin').on('input change', calculateQuickPrice);
        $('#quick-item-margin-type').on('change', calculateQuickPrice);
        $('#quick-item-price').on('input change', calculateQuickMargin);

        // Auto Generate Code button
        $('#btn-quick-gen-code').on('click', function (e) {
            e.preventDefault();
            var code = 'ITM-' + Math.floor(100000 + Math.random() * 900000);
            $('#quick-item-code').val(code);
        });

        // Refresh selectpickers and spans when modal opens
        $('#quick-create-item-modal').on('shown.bs.modal', function () {
            if ($.fn.selectpicker) {
                $(this).find('.selectpicker').selectpicker('render');
            }
            if (!$('#quick-item-code').val()) {
                $('#quick-item-code').val('ITM-' + Math.floor(100000 + Math.random() * 900000));
            }
            applyQuickModalDynamicGridSpans();
            setTimeout(function () {
                $('#quick-item-name').focus();
            }, 100);
        });

        window.applyQuickModalDynamicGridSpans = applyQuickModalDynamicGridSpans;
    });
})(window.jQuery);
