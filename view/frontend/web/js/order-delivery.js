/**
 * Copyright © Perspective. All rights reserved.
 *
 * Success page: when Zaraz accepts the browser "Order Completed", tell the server, so the server-side fallback
 * of Perspective_ZarazServerSide is not sent. Framework-free (ES5), needs PerspectiveZaraz.onDelivered (core 0.2+).
 */
(function (window, document) {
    'use strict';

    /**
     * @return {Object|null}
     */
    function readConfig() {
        var element = document.getElementById('perspective-zaraz-server-delivery');

        if (!element) {
            return null;
        }

        try {
            return JSON.parse(element.textContent || '{}');
        } catch (error) {
            return null;
        }
    }

    /**
     * @param {Object} config
     */
    function report(config) {
        var data = new window.FormData(),
            request;

        data.append('order', config.order);
        data.append('form_key', config.formKey);

        if (window.navigator.sendBeacon && window.navigator.sendBeacon(config.url, data)) {
            return;
        }
        request = new window.XMLHttpRequest();
        request.open('POST', config.url, true);
        request.send(data);
    }

    function init() {
        var config = readConfig(),
            reported = false,
            core = window.PerspectiveZaraz;

        if (!config || !core || typeof core.onDelivered !== 'function') {
            return;
        }
        core.onDelivered(function (event) {
            if (reported || event.code !== 'order_completed' || String(event.id) !== config.eventId) {
                return;
            }
            reported = true;
            report(config);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(window, document));
