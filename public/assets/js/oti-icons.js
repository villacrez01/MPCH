/**
 * OTI — Inicialización global de iconos Lucide
 */
(function () {
    'use strict';

    var DEFAULT_ATTRS = { 'stroke-width': '1.75' };

    function refreshIcons(root) {
        if (typeof lucide === 'undefined' || !lucide.createIcons) return;
        try {
            lucide.createIcons({
                attrs: DEFAULT_ATTRS,
                nameAttr: 'data-lucide',
                root: root || document
            });
        } catch (e) {
            console.warn('OTI icons:', e);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshIcons();
    });

    window.OTI = window.OTI || {};
    window.OTI.refreshIcons = refreshIcons;
})();
