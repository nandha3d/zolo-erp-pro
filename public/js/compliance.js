(() => {
    'use strict';
    const form = document.getElementById('gst-lookup-form');
    if (!form) return;
    const output = document.getElementById('gst-lookup-result');
    form.addEventListener('submit', async event => {
        event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
        output.textContent = 'Checking registration…';
        try {
            const response = await fetch('/compliance/gst/lookup', {method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({gstin: form.elements.gstin.value})});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
            const data = result.data;
            output.textContent = data.verified ? `${data.legal_name} · ${data.status} · Verified ${data.verified_at}. Review before saving.`
                : `Registration not verified (${data.status}). Enter reviewed details manually.`;
        } catch (error) { output.textContent = error.message || 'Lookup unavailable. Enter reviewed details manually.'; }
        finally { button.disabled = false; }
    });
})();
