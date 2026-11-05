(() => {
    'use strict';
    const form = document.getElementById('mapping-form');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-mapping-row]')];
    const search = document.getElementById('mapping-search');
    const status = document.getElementById('mapping-status');
    const initial = new Map([...form.querySelectorAll('select')].map(select => [select, select.value]));
    const filter = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        rows.forEach(row => {
            const select = row.querySelector('select');
            const text = `${row.querySelector('th').textContent} ${row.querySelector('.mapping-purpose').textContent} ${select.selectedOptions[0]?.textContent || ''}`.toLocaleLowerCase();
            row.hidden = !text.includes(query) || (status.value !== 'all' && row.dataset.status !== status.value);
            if (!row.hidden) visible++;
        });
        document.getElementById('mapping-results').textContent = `${visible} of ${rows.length} roles`;
        document.getElementById('mapping-no-results').hidden = visible > 0 || !rows.length;
    };
    search.addEventListener('input', filter);
    status.addEventListener('change', filter);
    form.addEventListener('change', () => {
        const changed = [...initial].filter(([select, value]) => select.value !== value).length;
        document.getElementById('mapping-changes').textContent = changed ? `${changed} unsaved mapping change${changed === 1 ? '' : 's'}.` : 'Changes apply only after saving.';
        filter();
    });
    form.addEventListener('invalid', event => {
        const row = event.target.closest('[data-mapping-row]');
        if (row?.hidden) { search.value = ''; status.value = 'all'; filter(); }
    }, true);
})();
