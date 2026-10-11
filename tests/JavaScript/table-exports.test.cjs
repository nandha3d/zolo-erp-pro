const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');

const code = readFileSync(require('node:path').join(__dirname, '../../public/js/table-exports.js'), 'utf8');
function fixture(arabic = false) {
    const files = [], calls = [], alerts = [];
    let fail;
    const buttons = Object.fromEntries(['excelHtml5', 'pdfHtml5'].map(name => [name, {
        action(...args) { calls.push({ name, api: this, args }); }
    }]));
    const context = {
        jQuery: { fn: { dataTable: { ext: { buttons } } } },
        navigator: { userAgent: 'Chrome Safari AppleWebKit/537.36' },
        window: { FileReader() {}, XMLSerializer() {}, alert(message) { alerts.push(message); } },
        document: {
            currentScript: { dataset: { base: '/vendor/datatable', arabic: arabic ? '1' : '0' } },
            createElement() { return { remove() {} }; },
            head: { appendChild(script) {
                files.push(script.src);
                queueMicrotask(() => script.src.endsWith(fail) ? script.onerror() : script.onload());
            } }
        }
    };
    runInNewContext(code, context);
    const api = { processing() {}, disable() {}, enable() {} };
    return { buttons, files, calls, alerts, api, context, fail(file) { fail = file; } };
}

test('initial load stays small; Excel loads once and keeps native action arguments', async () => {
    const f = fixture();
    assert.equal(f.files.length, 0);
    assert.equal(f.buttons.excelHtml5.available(), true);
    const args = [{ type: 'click' }, {}, {}, { title: 'My report' }];
    await Promise.all([f.buttons.excelHtml5.action.apply(f.api, args), f.buttons.excelHtml5.action.apply(f.api, args)]);
    assert.deepEqual(f.files, ['/vendor/datatable/jszip.min.js']);
    assert.equal(f.calls.length, 2);
    assert.equal(f.calls[0].api, f.api);
    assert.equal(f.calls[0].args[3], args[3]);
});

test('PDF loads library before fonts, including Arabic fonts', async () => {
    for (const arabic of [false, true]) {
        const f = fixture(arabic);
        await f.buttons.pdfHtml5.action.call(f.api);
        const suffix = arabic ? '_arabic' : '';
        assert.deepEqual(f.files, [`/vendor/datatable/pdfmake${suffix}.min.js`, `/vendor/datatable/vfs_fonts${suffix}.js`]);
        assert.equal(f.calls[0].name, 'pdfHtml5');
    }
});

test('failed tools report an error, restore button state, and can retry', async () => {
    const f = fixture();
    const states = [];
    f.api.processing = value => states.push(value);
    f.api.enable = () => states.push('enabled');
    f.fail('vfs_fonts.js');
    await f.buttons.pdfHtml5.action.call(f.api);
    assert.equal(f.calls.length, 0);
    assert.equal(f.alerts.length, 1);
    assert.deepEqual(states, [true, false, 'enabled']);
    f.fail(undefined);
    await f.buttons.pdfHtml5.action.call(f.api);
    assert.equal(f.calls.length, 1);
    assert.equal(f.files.filter(file => file.endsWith('pdfmake.min.js')).length, 1);
    assert.equal(f.files.filter(file => file.endsWith('vfs_fonts.js')).length, 2);
});

test('unsupported export browsers remain excluded', () => {
    const f = fixture();
    f.context.navigator.userAgent = 'Safari AppleWebKit/602.1';
    assert.equal(f.buttons.excelHtml5.available(), false);
    delete f.context.window.FileReader;
    assert.equal(f.buttons.pdfHtml5.available(), false);
});
