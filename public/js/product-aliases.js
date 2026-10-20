(function () {
    'use strict';
    const form = document.querySelector('[data-alias-form]');
    if (!form) return;
    const type = form.elements.party_type, party = form.elements.party_id;
    function scope() {
        party.value = '';
        party.disabled = type.value === 'company';
        party.required = !party.disabled;
        party.closest('[data-alias-party]').hidden = party.disabled;
        Array.from(party.options).forEach(option => { option.hidden = !!option.dataset.kind && option.dataset.kind !== type.value; option.disabled = option.hidden; });
    }
    type.addEventListener('change', scope);
    scope();
}());
