document.querySelectorAll('.product-usage').forEach(function (options) {
    const form = options.closest('form');
    const { cost, price, profit_margin_type: marginType, type } = form.elements;

    function updateUsage() {
        const usage = form.elements.product_usage.value;
        form.dataset.productUsage = usage;
        cost.required = usage !== 'sale' && (form.id === 'quick-create-item-form' || type.value === 'standard');
        price.required = marginType.required = usage !== 'purchase';
    }

    form.addEventListener('change', function (event) {
        if (event.target.name === 'product_usage' || event.target === type) updateUsage();
    });
    form.addEventListener('reset', function () {
        requestAnimationFrame(updateUsage);
    });
    updateUsage();
});
