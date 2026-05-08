/**
 * ConsentForge — Google Consent Mode v2 Integration
 *
 * CRITICAL: This script must be enqueued BEFORE gtag.js loads so that
 * the consent defaults are in place before any measurement fires.
 *
 * Category → signal mapping (Digital Omnibus 2026 / DO26):
 *   essential   → functionality_storage: granted, security_storage: granted (always)
 *   analytics   → analytics_storage
 *   performance → personalization_storage
 *   marketing   → ad_storage, ad_user_data, ad_personalization
 */
(function () {
    'use strict';

    // Initialise dataLayer / gtag stub if not already present.
    window.dataLayer = window.dataLayer || [];

    // Intentional function declaration — required for arguments object (gtag spec).
    function gtag() {
        // eslint-disable-next-line prefer-rest-params
        dataLayer.push(arguments);
    }

    window.CFConsentModeV2 = {
        initialized: false,

        /**
         * Set consent defaults synchronously.
         * Call once before gtag.js loads with the best-available initial state.
         *
         * @param {Object} initialState  ConsentForge state object (categories keyed by name).
         */
        init: function (initialState) {
            if (this.initialized) {
                return;
            }

            var signals = this.stateToSignals(initialState || {});

            // 'default' must be the very first consent call.
            gtag('consent', 'default', signals);

            // Timestamp so GA4 can attribute the signal correctly.
            gtag('set', 'ads_data_redaction', !signals.ad_storage || signals.ad_storage === 'denied');

            this.initialized = true;
        },

        /**
         * Push a consent update after the user has interacted with the banner.
         *
         * @param {Object} state  ConsentForge state object.
         */
        update: function (state) {
            if (!this.initialized) {
                this.init(state);
                return;
            }

            var signals = this.stateToSignals(state);
            gtag('consent', 'update', signals);
            gtag('set', 'ads_data_redaction', signals.ad_storage === 'denied');
        },

        /**
         * Convert a ConsentForge category state object to Consent Mode v2 signals.
         *
         * @param  {Object} state  e.g. { analytics: true, marketing: false, performance: true, essential: true }
         * @return {Object}        Consent Mode v2 signal map.
         */
        stateToSignals: function (state) {
            var granted = 'granted';
            var denied  = 'denied';

            return {
                // Marketing (opt-in — denied by default)
                ad_storage:            state.marketing  ? granted : denied,
                ad_user_data:          state.marketing  ? granted : denied,
                ad_personalization:    state.marketing  ? granted : denied,
                // Analytics (opt-out — granted by default unless GPC)
                analytics_storage:     state.analytics  ? granted : denied,
                // Performance
                personalization_storage: state.performance ? granted : denied,
                // Essential — always granted
                functionality_storage: granted,
                security_storage:      granted,
            };
        },
    };

    // -----------------------------------------------------------------------
    // Bootstrap: set defaults immediately using whatever state is available.
    // If the main banner script hasn't parsed the cookie yet, we fall back to
    // the safe defaults shipped by PHP in window.CFConfig.
    // -----------------------------------------------------------------------
    (function bootstrap() {
        var cfg     = window.CFConfig || {};
        var current = cfg.currentConsent || {};

        // If PHP already resolved a valid consent cookie, use it.
        // Otherwise use the banner defaults: analytics/performance ON, marketing OFF.
        var initialState = {
            essential:   true,
            analytics:   typeof current.analytics   !== 'undefined' ? !!current.analytics   : true,
            performance: typeof current.performance !== 'undefined' ? !!current.performance : true,
            marketing:   typeof current.marketing   !== 'undefined' ? !!current.marketing   : false,
        };

        // GPC overrides everything to denied.
        if (current.gpc || (window.CFGPCDetector && window.CFGPCDetector.detect())) {
            initialState.analytics   = false;
            initialState.performance = false;
            initialState.marketing   = false;
        }

        CFConsentModeV2.init(initialState);
    })();

    // -----------------------------------------------------------------------
    // Listen for runtime consent changes from the banner.
    // -----------------------------------------------------------------------
    document.addEventListener('consentforge:consent_update', function (e) {
        if (e.detail && e.detail.state) {
            CFConsentModeV2.update(e.detail.state);
        }
    });
})();
