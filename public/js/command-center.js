(() => {
    'use strict';
    document.addEventListener('keydown', event => {
        if (event.defaultPrevented || event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;
        if (event.key !== 'F2' && event.key !== 'F12') return;
        if (document.querySelector('dialog[open], .modal.show')) return;
        const link = document.querySelector('[data-command-shortcut="' + event.key + '"]');
        if (!link) return;
        event.preventDefault();
        const workspace = document.getElementById('commercial-entry-workspace');
        if (workspace && workspace.dataset.kind === (event.key === 'F2' ? 'sale' : 'purchase')) {
            document.getElementById('product-search')?.focus();
            return;
        }
        window.location.href = link.href;
    });
})();
