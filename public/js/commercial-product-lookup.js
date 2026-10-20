(function (root) {
    'use strict';
    const normalize = value => String(value || '').normalize('NFKC').replace(/[\s\u00a0]+/gu, ' ').trim().toLocaleLowerCase();
    root.CommercialProductLookup = {
        normalize,
        async search(term, products, kind, mode) {
            const party = document.querySelector(kind === 'sale' ? '[name="customer_id"]' : '[name="supplier_id"]');
            const selectedParty = party?.value || '';
            const params = new URLSearchParams({q: term});
            if (selectedParty) params.set('party_id', selectedParty);
            const response = root.CommercialProductLookup.enabled === false ? null
                : await fetch('/commercial/' + kind + '/search/products?' + params, {headers: {Accept: 'application/json'}});
            if (response && !response.ok) throw new Error('Product lookup failed. Refresh and try again.');
            const data = response ? (await response.json()).data : [];
            if ((party?.value || '') !== selectedParty) return [];
            const metadata = new Map(data.map(product => [String(product.id), product]));
            const query = normalize(term);
            return products.filter(product => mode(product) && (metadata.has(String(product.id)) || normalize(product.code).includes(query) || normalize(product.name).includes(query)))
                .map(product => ({...product, match_rank: normalize(product.code) === query ? 0 : (metadata.get(String(product.id))?.match_rank ?? (normalize(product.name) === query ? 3 : 4))}))
                .sort((a, b) => a.match_rank - b.match_rank || String(a.code).localeCompare(String(b.code))).slice(0, 50);
        },
        exact(matches, term) {
            const query = normalize(term);
            const rank = matches[0]?.match_rank;
            const exact = matches.filter(product => product.match_rank === rank && (rank <= 2 || normalize(product.name) === query));
            return exact.length === 1 ? exact[0] : null;
        }
    };
}(window));
