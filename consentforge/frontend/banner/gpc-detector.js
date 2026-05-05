/**
 * ConsentForge GPC Detector
 * Standalone Global Privacy Control detection module.
 * Must load before the main banner script.
 */
(function () {
    'use strict';

    window.CFGPCDetector = {
        /**
         * Returns true if GPC signal is present and set to true.
         * navigator.globalPrivacyControl is a boolean per the GPC spec.
         */
        detect: function () {
            return navigator.globalPrivacyControl === true;
        },

        /**
         * Returns a structured signal object for logging / debugging.
         */
        getSignal: function () {
            return {
                detected: this.detect(),
                source: 'navigator.globalPrivacyControl',
                value: navigator.globalPrivacyControl,
            };
        },
    };
})();
