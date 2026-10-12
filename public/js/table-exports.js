(function ($) {
    'use strict';

    var source = document.currentScript;
    var base = source.dataset.base;
    var arabic = source.dataset.arabic === '1';
    var loads = {};
    var buttons = $.fn.dataTable.ext.buttons;

    function load(file) {
        if (!loads[file]) {
            loads[file] = new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = base + '/' + file;
                script.onload = resolve;
                script.onerror = function () {
                    delete loads[file];
                    script.remove();
                    reject(new Error('Could not load export tools. Please try again.'));
                };
                document.head.appendChild(script);
            });
        }
        return loads[file];
    }

    ['excelHtml5', 'pdfHtml5'].forEach(function (name) {
        var button = buttons[name];
        var action = button.action;
        button.available = function () {
            if (!window.FileReader) return false;
            if (name === 'pdfHtml5') return true;
            // Keep the installed Buttons version's exclusion of old Safari Excel exports.
            var safari = /Safari/.test(navigator.userAgent) && !/Chrome|Opera/.test(navigator.userAgent);
            var webkit = navigator.userAgent.match(/AppleWebKit\/(\d+\.\d+)/);
            return !!window.XMLSerializer && !(safari && webkit && Number(webkit[1]) < 603.1);
        };
        button.action = function () {
            var api = this;
            var args = arguments;
            api.processing(true);
            api.disable();
            var ready = name === 'excelHtml5'
                ? load('jszip.min.js')
                : load(arabic ? 'pdfmake_arabic.min.js' : 'pdfmake.min.js')
                    .then(function () { return load(arabic ? 'vfs_fonts_arabic.js' : 'vfs_fonts.js'); });
            return ready.then(function () {
                return action.apply(api, args);
            }).catch(function (error) {
                api.processing(false);
                window.alert(error.message);
            }).finally(function () {
                api.enable();
            });
        };
    });
})(jQuery);
