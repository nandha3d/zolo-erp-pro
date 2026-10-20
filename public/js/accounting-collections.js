(function () {
    'use strict';
    const kind = document.getElementById('collection-kind');
    function togglePdc() {
        document.querySelectorAll('[data-pdc]').forEach(section => {
            const active = kind && kind.value === 'pdc';
            section.hidden = !active;
            section.querySelectorAll('input').forEach(input => { input.disabled = !active; input.required = active; });
        });
    }
    if (kind) { kind.addEventListener('change', togglePdc); togglePdc(); }
    document.querySelectorAll('[data-collection-form]').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        if (form.dataset.saving === 'true') return;
        form.dataset.saving = 'true';
        let error = form.querySelector('.collection-error');
        if (!error) { error = document.createElement('span'); error.className = 'collection-error'; error.setAttribute('role', 'alert'); form.append(error); }
        error.textContent = '';
        const button = form.querySelector('button[type="submit"],button:not([type])');
        if (button) button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const body = await response.json();
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'Collection could not be saved.');
            window.location.reload();
        } catch (failure) { error.textContent = failure.message; }
        finally { form.dataset.saving = 'false'; if (button) button.disabled = false; }
    }));
    const preview = document.getElementById('collection-preview');
    if (preview) preview.addEventListener('submit', async event => {
        event.preventDefault();
        const output = document.getElementById('collection-preview-result');
        output.textContent = '';
        try {
            const response = await fetch(preview.action, { method: 'POST', body: new FormData(preview), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const body = await response.json();
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message);
            output.textContent = `Principal ${body.principal}; overdue ${body.days} days; annual interest ${body.interest_rate}% / ${body.day_basis}: ${body.interest_preview}; discount ${body.discount_rate}%: ${body.discount_preview} (${body.discount_eligible ? 'eligible' : 'not eligible'}). ${body.effects}`;
        } catch (failure) { output.textContent = failure.message; }
    });
}());
