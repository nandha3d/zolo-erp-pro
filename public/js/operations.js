(() => {
    'use strict';
    
    // Delegated event listeners for dynamic add/remove lines across all forms
    document.addEventListener('click', event => {
        const add = event.target.closest('[data-add-line]');
        if (add) {
            const container = add.closest('.border') || add.previousElementSibling;
            const table = container?.querySelector('[data-lines]');
            const body = table?.querySelector('tbody');
            if (!body) return;
            const row = body.firstElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(input => { 
                input.value = input.type === 'number' && input.name.endsWith('[cost_weight]') ? '1' : ''; 
                input.checked = false; 
            });
            row.querySelectorAll('select').forEach(select => { select.selectedIndex = 0; });
            row.querySelectorAll('[data-dimension-fields]').forEach(group => { 
                group.hidden = true; 
                group.querySelectorAll('input, select').forEach(input => { input.disabled = true; }); 
            });
            body.append(row);
            renumber(body);
            row.querySelector('select, input')?.focus();
        }
        const remove = event.target.closest('[data-remove-line]');
        if (remove) {
            const row = remove.closest('[data-line]'); 
            const body = row?.parentElement;
            if (body && body.children.length > 1) { 
                row.remove(); 
                renumber(body); 
                body.lastElementChild.querySelector('select, input')?.focus(); 
            }
        }
    });

    function renumber(body) {
        Array.from(body.children).forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => { 
                input.name = input.name.replace(/\[\d+\]/, `[${index}]`); 
            });
        });
    }

    document.addEventListener('change', event => {
        if (event.target.matches('[data-optional-lines]')) {
            const group = event.target.closest('section')?.querySelector('[data-optional-group]');
            if (group) {
                group.hidden = !event.target.checked;
                group.disabled = !event.target.checked;
            }
        }
        if (event.target.matches('[data-dimensions]')) {
            const group = event.target.closest('details')?.querySelector('[data-dimension-fields]');
            if (group) {
                group.hidden = !event.target.checked;
                group.querySelectorAll('input, select').forEach(input => { input.disabled = !event.target.checked; });
            }
        }
    });

    // Reusable initialization function for initial load and SPA navigation
    window.initOperations = function() {
        document.querySelectorAll('form:not(#attribute-form)').forEach(form => {
            if (form.dataset.bound) return;
            form.dataset.bound = 'true';
            form.addEventListener('submit', () => { 
                form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = true; }); 
            });
            form.addEventListener('keydown', event => {
                if (event.ctrlKey && event.key === 'Enter') { 
                    event.preventDefault(); 
                    form.requestSubmit(); 
                }
            });
        });

        const product = document.getElementById('attribute-product');
        const form = document.getElementById('attribute-form');
        if (product && form && !product.dataset.bound) {
            product.dataset.bound = 'true';
            const fields = document.getElementById('attribute-fields'); 
            const status = document.getElementById('attribute-status');
            let sequence = 0;
            
            async function request(values) {
                const response = await fetch(`/operations/product/${product.value}/attributes`, {
                    method: values ? 'POST' : 'GET', 
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json', 
                        'Content-Type': 'application/json', 
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    ...(values ? {body: JSON.stringify({attributes: values})} : {})
                });
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
                return result.data;
            }

            product.addEventListener('change', async () => {
                const current = ++sequence; 
                fields.replaceChildren(); 
                form.querySelector('button').disabled = true;
                if (!product.value) return;
                status.textContent = 'Loading attributes…';
                try {
                    const attributes = await request(); 
                    if (current !== sequence) return;
                    attributes.forEach(attribute => {
                        const label = document.createElement('label'); 
                        label.textContent = attribute.label;
                        const input = document.createElement('input'); 
                        input.name = attribute.key; 
                        input.value = attribute.value ?? '';
                        input.type = attribute.type === 'text' ? 'text' : 'number'; 
                        if (input.type === 'number') { 
                            input.min = '0'; 
                            input.step = attribute.type === 'integer' ? '1' : 'any'; 
                        }
                        label.append(input); 
                        fields.append(label);
                    });
                    form.querySelector('button').disabled = attributes.length === 0;
                    status.textContent = attributes.length ? 'Edit attributes, then save.' : 'No profile attributes enabled.';
                } catch (error) { 
                    if (current === sequence) status.textContent = error.message; 
                }
            });

            form.addEventListener('submit', async event => {
                event.preventDefault(); 
                const values = Object.fromEntries(Array.from(new FormData(form)).filter(([,value]) => value !== ''));
                form.querySelector('button').disabled = true;
                try { 
                    await request(values); 
                    status.textContent = 'Attributes saved.'; 
                } catch(error) { 
                    status.textContent = error.message; 
                } finally { 
                    form.querySelector('button').disabled = false; 
                }
            });
        }
        document.querySelector('.ops-errors')?.focus();
    };

    // Auto-init on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initOperations);
    } else {
        window.initOperations();
    }
})();
