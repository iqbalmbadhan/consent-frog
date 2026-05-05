/**
 * ConsentForge — Client-side Script Controller
 *
 * Responsibilities:
 *  1. On DOMContentLoaded, unblock scripts whose category has consent.
 *  2. MutationObserver: neutralise dynamically-injected scripts that lack consent.
 *  3. On 'consentforge:consent_update', unblock newly-consented categories.
 *
 * Blocking convention (set by PHP output buffering or theme authors):
 *   <script type="text/plain" data-cf-category="analytics" src="..."></script>
 *   <script type="text/plain" data-cf-category="marketing">...</script>
 *
 * Unblocking: clone the element, set type="text/javascript", replace original.
 * Re-blocking is not possible once a script has executed — callers should
 * reload the page when consent is downgraded.
 */
(function () {
    'use strict';

    // Categories recognised by ConsentForge.
    var CATEGORIES = ['essential', 'analytics', 'performance', 'marketing'];

    // Internal state: which categories are currently consented.
    var _consented = {
        essential:   true,
        analytics:   false,
        performance: false,
        marketing:   false,
    };

    // Guard against double-processing the same node.
    var _processed = new WeakSet();

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Read the current consent state from CFConfig (PHP-injected) or from
     * window.ConsentForge (runtime), whichever is available.
     *
     * @return {Object}  Category → boolean map.
     */
    function resolveCurrentState() {
        // Prefer the live runtime object if the banner has already initialised.
        if (window.ConsentForge && typeof window.ConsentForge.getConsentState === 'function') {
            return window.ConsentForge.getConsentState();
        }

        var cfg     = window.CFConfig || {};
        var current = cfg.currentConsent || {};

        return {
            essential:   true,
            analytics:   typeof current.analytics   !== 'undefined' ? !!current.analytics   : true,
            performance: typeof current.performance !== 'undefined' ? !!current.performance : true,
            marketing:   typeof current.marketing   !== 'undefined' ? !!current.marketing   : false,
        };
    }

    /**
     * Execute a blocked script node by cloning it with the correct MIME type.
     * The original node is replaced so it cannot be activated twice.
     *
     * @param {HTMLScriptElement} node
     */
    function activateScript(node) {
        if (_processed.has(node)) {
            return;
        }
        _processed.add(node);

        var clone = document.createElement('script');

        // Copy all attributes except type.
        for (var i = 0; i < node.attributes.length; i++) {
            var attr = node.attributes[i];
            if (attr.name === 'type') {
                continue;
            }
            clone.setAttribute(attr.name, attr.value);
        }

        clone.type = 'text/javascript';

        // Copy inline content.
        if (node.textContent) {
            clone.textContent = node.textContent;
        }

        // Mark as already processed so the MutationObserver ignores it.
        _processed.add(clone);

        if (node.parentNode) {
            node.parentNode.replaceChild(clone, node);
        }
    }

    /**
     * Neutralise a script that has been injected without consent by converting
     * it to type="text/plain" before the browser can execute it.
     *
     * NOTE: This only works if we observe the node *before* the parser / JS
     * runtime compiles it. For synchronous scripts this is generally reliable
     * when using a MutationObserver in the <head>.
     *
     * @param {HTMLScriptElement} node
     */
    function blockScript(node) {
        if (_processed.has(node)) {
            return;
        }
        _processed.add(node);
        node.type = 'text/plain';
    }

    /**
     * Scan the document for blocked scripts in a given category and activate
     * those that now have consent.
     *
     * @param {string} category
     */
    function unblockByCategory(category) {
        var nodes = document.querySelectorAll(
            'script[type="text/plain"][data-cf-category="' + category + '"]'
        );

        for (var i = 0; i < nodes.length; i++) {
            activateScript(nodes[i]);
        }
    }

    /**
     * Apply the full current consent state: unblock every category that is
     * now consented.
     */
    function applyCurrentConsent() {
        var state = resolveCurrentState();

        for (var i = 0; i < CATEGORIES.length; i++) {
            var cat = CATEGORIES[i];
            if (state[cat]) {
                _consented[cat] = true;
                unblockByCategory(cat);
            }
        }
    }

    // -----------------------------------------------------------------------
    // MutationObserver — intercept dynamically-injected scripts
    // -----------------------------------------------------------------------

    var _observer = new MutationObserver(function (mutations) {
        for (var m = 0; m < mutations.length; m++) {
            var added = mutations[m].addedNodes;
            for (var n = 0; n < added.length; n++) {
                var node = added[n];

                if (node.nodeName !== 'SCRIPT') {
                    continue;
                }

                var category = node.getAttribute('data-cf-category');
                if (!category) {
                    continue; // Not managed by ConsentForge.
                }

                if (_processed.has(node)) {
                    continue; // Already handled (e.g. our own clone).
                }

                if (_consented[category]) {
                    // Consent already granted — let it run but mark as seen.
                    _processed.add(node);
                } else {
                    // No consent — block it.
                    blockScript(node);
                }
            }
        }
    });

    _observer.observe(document.documentElement, {
        childList: true,
        subtree:   true,
    });

    // -----------------------------------------------------------------------
    // Event listener — react to banner consent changes
    // -----------------------------------------------------------------------

    document.addEventListener('consentforge:consent_update', function (e) {
        if (!e.detail || !e.detail.state) {
            return;
        }

        var state = e.detail.state;

        for (var i = 0; i < CATEGORIES.length; i++) {
            var cat = CATEGORIES[i];

            if (state[cat] && !_consented[cat]) {
                // Newly consented — unblock.
                _consented[cat] = true;
                unblockByCategory(cat);
            } else if (!state[cat] && _consented[cat]) {
                // Consent withdrawn — cannot un-execute scripts, so just track state.
                // The PHP layer will stop serving them on the next page load.
                _consented[cat] = false;
            }
        }
    });

    // -----------------------------------------------------------------------
    // Bootstrap
    // -----------------------------------------------------------------------

    function bootstrap() {
        // Sync _consented with whatever PHP resolved server-side.
        var state = resolveCurrentState();

        for (var i = 0; i < CATEGORIES.length; i++) {
            _consented[CATEGORIES[i]] = !!state[CATEGORIES[i]];
        }

        applyCurrentConsent();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    // Expose for debugging / external tooling.
    window.CFScriptController = {
        unblockByCategory: unblockByCategory,
        getConsentedCategories: function () {
            return Object.assign({}, _consented);
        },
    };
})();
