const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {runInNewContext} = require('node:vm');
const script = readFileSync(require('node:path').join(__dirname, '../../public/js/commercial-product-lookup.js'), 'utf8');

function fixture(data, partyValue = '7') {
    const party = {value: partyValue};
    let requested;
    const window = {};
    runInNewContext(script, {window, URLSearchParams, document: {querySelector: () => party}, fetch: async url => {
        requested = url;
        return {ok: true, json: async () => ({data})};
    }});
    return {lookup: window.CommercialProductLookup, party, requested: () => requested};
}

test('selected party scope travels to server; variant and unit projections remain local', async () => {
    const fixtureData = fixture([{id: 1, match_rank: 1, cost: 999}]);
    const variants = [{id: 1, code: 'V1', name: 'Fabric', unit: 'MTR', price: 42, cost: 30}, {id: 1, code: 'V2', name: 'Fabric', unit: 'MTR', price: 44, cost: 32}];
    const matches = await fixtureData.lookup.search('Party alias', variants, 'sale', () => true);
    assert.match(fixtureData.requested(), /party_id=7/);
    assert.equal(matches.length, 2);
    assert.equal(matches[0].cost, 30);
    assert.equal(matches[0].unit, 'MTR');
    assert.equal(fixtureData.lookup.exact(matches, 'Party alias'), null);
});

test('shared alias ambiguity requires explicit selection, exact code retains priority', async () => {
    const {lookup} = fixture([{id: 1, match_rank: 2}, {id: 2, match_rank: 2}]);
    const products = [{id: 1, code: 'A', name: 'Cotton'}, {id: 2, code: 'B', name: 'Silk'}];
    assert.equal(lookup.exact(await lookup.search('Common', products, 'purchase', () => true), 'Common'), null);
    const matches = await lookup.search('B', products, 'purchase', () => true);
    assert.equal(lookup.exact(matches, 'B').id, 2);
});

test('party-specific alias outranks company alias and Unicode normalization matches browser input', async () => {
    const {lookup} = fixture([{id: 1, match_rank: 2}, {id: 2, match_rank: 1}]);
    const products = [{id: 1, code: 'A', name: 'Cotton'}, {id: 2, code: 'B', name: 'Silk'}];
    const matches = await lookup.search('Alias', products, 'sale', () => true);
    assert.equal(lookup.exact(matches, 'Alias').id, 2);
    assert.equal(lookup.normalize('  ＣＡＦＥ\u0301\u00a0Cotton  '), 'café cotton');
});

test('stale party response is discarded', async () => {
    const state = fixture([{id: 1, match_rank: 1}]);
    const pending = state.lookup.search('Alias', [{id: 1, code: 'A', name: 'Cotton'}], 'sale', () => true);
    state.party.value = '8';
    assert.equal((await pending).length, 0);
});

test('party changes while JSON body is pending discard the completed alias result', async () => {
    const party = {value: '7'};
    const window = {};
    let completeBody, bodyStarted;
    const started = new Promise(resolve => { bodyStarted = resolve; });
    const body = new Promise(resolve => { completeBody = resolve; });
    runInNewContext(script, {window, URLSearchParams, document: {querySelector: () => party},
        fetch: async () => ({ok: true, json: () => { bodyStarted(); return body; }})});
    const pending = window.CommercialProductLookup.search('Alias', [{id: 1, code: 'A', name: 'Cotton'}], 'sale', () => true);
    await started;
    party.value = '8';
    completeBody({data: [{id: 1, match_rank: 1}]});
    assert.equal((await pending).length, 0);
});
