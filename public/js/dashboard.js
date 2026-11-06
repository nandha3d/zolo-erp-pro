/* Dashboard filters and read-only tables. */
jQuery(function ($) {
    const dashboard = document.querySelector('[data-dashboard]');
    if (!dashboard) return;

    const {currency, decimals, dateFormat, saleUrl, purchaseUrl} = dashboard.dataset;
    const locale = currency === '₹' ? 'en-IN' : document.documentElement.lang || 'en';
    const exact = new Intl.NumberFormat(locale, {minimumFractionDigits: Number(decimals), maximumFractionDigits: Number(decimals)});
    const compact = new Intl.NumberFormat(locale, {notation: 'compact', maximumFractionDigits: 2});
    const money = value => Number.isFinite(Number(value)) && value !== null ? currency + exact.format(value) : '—';

    function setMetric(element, value) {
        element.title = money(value);
        element.textContent = Math.abs(value) >= 100000 ? currency + compact.format(value) : element.title;
    }

    dashboard.querySelectorAll('[data-metric]').forEach(element => setMetric(element, element.dataset.amount));
    dashboard.querySelectorAll('.dash-filters select').forEach(select => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    function addCell(row, text, className) {
        const cell = row.insertCell();
        cell.textContent = text ?? '—';
        if (className) cell.className = className;
        return cell;
    }

    function loadTable(table, url, renderRow, {loading, empty, error}) {
        const body = table.tBodies[0];
        function showMessage(message) {
            const row = document.createElement('tr');
            const cell = addCell(row, message, 'dash-table-message');
            cell.colSpan = table.tHead.querySelectorAll('th:not([hidden])').length;
            cell.setAttribute('role', 'status');
            body.replaceChildren(row);
        }

        showMessage(loading);
        return $.getJSON(url).done(items => {
            if (!Array.isArray(items) || !items.length) {
                showMessage(Array.isArray(items) ? empty : error);
                return;
            }
            const rows = document.createDocumentFragment();
            items.forEach(item => {
                const row = document.createElement('tr');
                renderRow(row, item);
                rows.append(row);
            });
            body.replaceChildren(rows);
        }).fail((_, status) => {
            if (status !== 'abort') showMessage(error);
        });
    }

    function dateLabel(value) {
        if (!value) return '—';
        const [Y, m, d] = value.slice(0, 10).split('-');
        return dateFormat.replace(/[Ymd]/g, part => ({Y, m, d})[part]);
    }

    const activityType = dashboard.querySelector('#dash-activity-kind');
    const activity = dashboard.querySelector('#dash-activity-table');
    const saleStatuses = {1: ['Completed', 'success'], 2: ['Pending', 'warning']};
    const purchaseStatuses = {1: ['Received', 'success'], 2: ['Partial', 'warning'], 3: ['Pending', 'warning']};
    const statusFor = {
        sale: item => saleStatuses[item.sale_status] ?? ['Draft', 'info'],
        purchase: item => purchaseStatuses[item.status] ?? ['Ordered', 'info'],
        quotation: item => Number(item.quotation_status) === 1 ? ['Pending', 'warning'] : ['Sent', 'info'],
        payment: item => [item.paying_method ?? '—', 'info'],
    };
    let activityRequest;

    function loadActivity() {
        activityRequest?.abort();
        const kind = activityType.value;
        const payment = kind === 'payment';
        const party = activity.querySelector('[data-activity-party]');
        party.hidden = payment;
        party.textContent = kind === 'purchase' ? 'Supplier' : 'Customer';
        activity.querySelector('[data-activity-status]').textContent = payment ? 'Payment method' : 'Status';

        activityRequest = loadTable(activity, activityType.selectedOptions[0].dataset.url, (row, item) => {
            const reference = addCell(row, payment ? item.payment_reference : item.reference_no, 'dash-reference');
            const register = {sale: saleUrl, purchase: purchaseUrl}[kind];
            if (register && item.id) {
                const link = document.createElement('a');
                const url = new URL(register, window.location.href);
                url.searchParams.set('view_id', item.id);
                link.href = url.href;
                link.textContent = reference.textContent;
                reference.replaceChildren(link);
            }
            addCell(row, dateLabel(item.business_date || item.created_at));
            if (!payment) addCell(row, item.name);
            const [status, tone] = statusFor[kind](item);
            const pill = document.createElement('span');
            pill.className = 'dash-status dash-tone-' + tone;
            pill.textContent = status;
            row.insertCell().append(pill);
            const amount = (payment ? item.amount : item.grand_total) / (Number(item.exchange_rate) || 1);
            addCell(row, money(amount), 'dash-number');
        }, {
            loading: 'Loading transactions…',
            empty: 'No recent transactions of this type.',
            error: 'Could not load recent transactions.',
        });
    }

    activityType.addEventListener('change', loadActivity);
    loadActivity();

    dashboard.querySelectorAll('[data-ranking-url]').forEach(table => {
        const byValue = table.hasAttribute('data-ranking-value');
        loadTable(table, table.dataset.rankingUrl, (row, item) => {
            const product = addCell(row, item.product_name);
            const code = document.createElement('small');
            code.textContent = item.product_code;
            product.append(code);
            addCell(row, byValue ? money(item.total_price) : item.sold_qty, 'dash-number');
        }, {
            loading: 'Loading best sellers…',
            empty: 'No sales in this period.',
            error: 'Could not load best sellers.',
        });
    });
});
