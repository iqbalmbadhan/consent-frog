/**
 * ConsentForge — Consent Banner
 *
 * Vanilla JS, zero dependencies. Handles:
 *  - GPC detection (skip banner, auto-apply opt-out)
 *  - Cookie read/write (base64-JSON cf_consent, matches PHP ConsentEngine format)
 *  - Banner UI: bottom_bar | center_modal | sidebar | minimal
 *  - WCAG 2.2 AA: role="dialog", aria-modal, focus trap, Escape key
 *  - Google Consent Mode v2 via CFConsentModeV2 (consent-mode-v2.js)
 *  - Script unblocking via CFScriptController (script-controller.js)
 *  - REST API consent logging
 *
 * window.CFConfig shape (injected by PHP ScriptController):
 * {
 *   bannerConfig: { position, title, description, acceptLabel, rejectLabel,
 *                   customizeLabel, saveLabel, showRejectButton, animation,
 *                   primaryColor, backgroundColor, textColor },
 *   currentConsent: { essential, analytics, performance, marketing, gpc,
 *                     timestamp, consent_id, is_set },
 *   nonce: '...',
 *   apiUrl: '...',
 *   version: '1.0.0'
 * }
 */
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    var COOKIE_NAME    = 'cf_consent';
    var COOKIE_DAYS    = 365;
    var COOKIE_VERSION = '1.0';
    var EVENT_NS       = 'consentforge:';

    // -------------------------------------------------------------------------
    // ConsentForge singleton
    // -------------------------------------------------------------------------

    var ConsentForge = {
        config:      window.CFConfig || {},
        _state:      null,
        _banner:     null,
        _panel:      null,
        _prevFocus:  null,

        // ---------------------------------------------------------------------
        // Bootstrap
        // ---------------------------------------------------------------------

        init: function () {
            var cfg = this.config;

            // 1. GPC check — legally binding under Digital Omnibus 2026.
            //    If detected, apply full opt-out and skip banner entirely.
            if (this.detectGPC()) {
                var gpcState = {
                    essential:   true,
                    analytics:   false,
                    performance: false,
                    marketing:   false,
                    gpc:         true,
                };
                // Only persist + log if not already stored from a prior GPC visit.
                var existing = this.parseConsentCookie();
                if (!existing || !existing.gpc) {
                    this.setConsentState(gpcState, 'gpc');
                } else {
                    // Restore existing GPC state so scripts are unblocked correctly.
                    this._state = existing;
                    this.fire('consent_restore', { state: this._state });
                }
                return;
            }

            // 2. Valid cookie already present — restore state.
            var saved = this.parseConsentCookie();
            if (saved && saved.is_set) {
                this._state = saved;
                this.fire('consent_restore', { state: this._state });
                return;
            }

            // 3. No valid cookie — show banner.
            //    But first seed Consent Mode v2 with safe defaults so GA4 doesn't
            //    fire before the user chooses (the consent-mode-v2.js bootstrap
            //    already did this; ConsentForge just needs to not double-init).
            this.showBanner();
        },

        // ---------------------------------------------------------------------
        // GPC detection
        // ---------------------------------------------------------------------

        detectGPC: function () {
            // Delegate to dedicated module if available, then fall back.
            if (window.CFGPCDetector) {
                return window.CFGPCDetector.detect();
            }
            return navigator.globalPrivacyControl === true;
        },

        // ---------------------------------------------------------------------
        // Cookie helpers
        // ---------------------------------------------------------------------

        getCookie: function (name) {
            var prefix = name + '=';
            var pairs  = document.cookie.split(';');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].replace(/^\s+/, '');
                if (pair.indexOf(prefix) === 0) {
                    return decodeURIComponent(pair.slice(prefix.length));
                }
            }
            return null;
        },

        setCookie: function (name, value, days) {
            var expires = '';
            if (days) {
                var d = new Date();
                d.setTime(d.getTime() + days * 864e5);
                expires = '; expires=' + d.toUTCString();
            }
            var secure   = location.protocol === 'https:' ? '; Secure' : '';
            var sameSite = '; SameSite=Lax';
            document.cookie = name + '=' + encodeURIComponent(value) + expires +
                '; path=/' + secure + sameSite;
        },

        /**
         * Decode the cf_consent cookie.
         * Cookie format mirrors PHP ConsentEngine::encode_cookie():
         *   base64( JSON({ v, s, c:{e,a,p,m}, g, t, id }) )
         *
         * Returns a normalised state object or null on failure.
         */
        parseConsentCookie: function () {
            var raw = this.getCookie(COOKIE_NAME);
            if (!raw) { return null; }

            try {
                var decoded = atob(raw);
                var data    = JSON.parse(decoded);

                // Validate required keys.
                if (!data.v || !data.c || !data.t || !data.id) { return null; }

                var c = data.c;
                return {
                    essential:   true,
                    analytics:   !!c.a,
                    performance: !!c.p,
                    marketing:   !!c.m,
                    gpc:         !!data.g,
                    timestamp:   data.t,
                    consent_id:  data.id,
                    is_set:      true,
                };
            } catch (e) {
                return null;
            }
        },

        // ---------------------------------------------------------------------
        // State management
        // ---------------------------------------------------------------------

        getConsentState: function () {
            return this._state || this.getDefaultState();
        },

        getDefaultState: function () {
            // Digital Omnibus 2026: analytics & performance opt-OUT (on by default),
            // marketing opt-IN (off by default).
            var current = (this.config.currentConsent) || {};
            return {
                essential:   true,
                analytics:   typeof current.analytics   !== 'undefined' ? !!current.analytics   : true,
                performance: typeof current.performance !== 'undefined' ? !!current.performance : true,
                marketing:   typeof current.marketing   !== 'undefined' ? !!current.marketing   : false,
                gpc:         false,
                timestamp:   0,
                consent_id:  '',
                is_set:      false,
            };
        },

        /**
         * Persist consent, fire events, update Consent Mode v2, log to API.
         *
         * @param {Object} categories  { essential, analytics, performance, marketing, gpc }
         * @param {string} source      e.g. 'banner_accept_all', 'banner_reject_all', 'banner_partial', 'gpc'
         */
        setConsentState: function (categories, source) {
            var id  = this._generateUUID();
            var gpc = categories.gpc || false;

            // Build cookie payload (mirrors PHP ConsentEngine format).
            var payload = {
                v:  COOKIE_VERSION,
                s:  this._computeStatus(categories),
                c:  {
                    e: 1,
                    a: categories.analytics   ? 1 : 0,
                    p: categories.performance ? 1 : 0,
                    m: categories.marketing   ? 1 : 0,
                },
                g:  gpc ? 1 : 0,
                t:  Math.floor(Date.now() / 1000),
                id: id,
            };

            this.setCookie(COOKIE_NAME, btoa(JSON.stringify(payload)), COOKIE_DAYS);

            this._state = {
                essential:   true,
                analytics:   !!payload.c.a,
                performance: !!payload.c.p,
                marketing:   !!payload.c.m,
                gpc:         gpc,
                timestamp:   payload.t,
                consent_id:  id,
                is_set:      true,
            };

            // Fire custom event — picked up by consent-mode-v2.js & script-controller.js.
            this.fire('consent_update', { state: this._state, source: source });

            // Log to REST API asynchronously (best-effort, non-blocking).
            this.logConsent(this._state, source);
        },

        _computeStatus: function (cats) {
            var opts    = ['analytics', 'performance', 'marketing'];
            var granted = 0;
            for (var i = 0; i < opts.length; i++) {
                if (cats[opts[i]]) { granted++; }
            }
            if (granted === 0)          { return 'denied'; }
            if (granted === opts.length) { return 'granted'; }
            return 'partial';
        },

        _generateUUID: function () {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                var v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        // ---------------------------------------------------------------------
        // Consent actions
        // ---------------------------------------------------------------------

        acceptAll: function () {
            this.setConsentState({
                essential:   true,
                analytics:   true,
                performance: true,
                marketing:   true,
            }, 'banner_accept_all');
            this.hideBanner();
        },

        rejectAll: function () {
            this.setConsentState({
                essential:   true,
                analytics:   false,
                performance: false,
                marketing:   false,
            }, 'banner_reject_all');
            this.hideBanner();
        },

        saveCustom: function () {
            if (!this._panel) { return; }

            var state = {
                essential:   true,
                analytics:   !!this._panel.querySelector('#cf-toggle-analytics')   && this._panel.querySelector('#cf-toggle-analytics').checked,
                performance: !!this._panel.querySelector('#cf-toggle-performance') && this._panel.querySelector('#cf-toggle-performance').checked,
                marketing:   !!this._panel.querySelector('#cf-toggle-marketing')   && this._panel.querySelector('#cf-toggle-marketing').checked,
            };

            // Determine source granularity.
            var allOn  = state.analytics && state.performance && state.marketing;
            var allOff = !state.analytics && !state.performance && !state.marketing;
            var src    = allOn ? 'banner_accept_all' : (allOff ? 'banner_reject_all' : 'banner_partial');

            this.setConsentState(state, src);
            this.hideBanner();
        },

        // ---------------------------------------------------------------------
        // Banner UI construction
        // ---------------------------------------------------------------------

        createBanner: function () {
            var cfg      = this.config.bannerConfig || {};
            var position = cfg.position || 'bottom_bar';
            var def      = this.getDefaultState();

            var wrap = document.createElement('div');
            wrap.id              = 'cf-banner';
            wrap.className       = 'cf-banner cf-pos-' + position;
            wrap.setAttribute('role', 'dialog');
            wrap.setAttribute('aria-modal', 'true');
            wrap.setAttribute('aria-label', cfg.title || 'Cookie Consent');
            wrap.setAttribute('aria-describedby', 'cf-banner-desc');

            // ---- Summary view -----------------------------------------------
            var summary = document.createElement('div');
            summary.className = 'cf-summary';

            var titleEl = document.createElement('p');
            titleEl.className = 'cf-title';
            titleEl.textContent = cfg.title || 'We value your privacy';

            var descEl = document.createElement('p');
            descEl.id          = 'cf-banner-desc';
            descEl.className   = 'cf-desc';
            descEl.textContent = cfg.description ||
                'We use cookies to enhance your browsing experience, analyse site traffic, and serve personalised content.';

            var actions = document.createElement('div');
            actions.className = 'cf-actions';

            var btnAccept = document.createElement('button');
            btnAccept.type      = 'button';
            btnAccept.className = 'cf-btn cf-btn-primary';
            btnAccept.textContent = cfg.acceptLabel || 'Accept All';

            var btnReject = document.createElement('button');
            btnReject.type      = 'button';
            btnReject.className = 'cf-btn cf-btn-secondary';
            btnReject.textContent = cfg.rejectLabel || 'Reject All';
            if (cfg.showRejectButton === false || cfg.showRejectButton === '0') {
                btnReject.style.display = 'none';
            }

            var btnCustomize = document.createElement('button');
            btnCustomize.type      = 'button';
            btnCustomize.className = 'cf-btn cf-btn-ghost';
            btnCustomize.textContent = cfg.customizeLabel || 'Customize';
            btnCustomize.setAttribute('aria-expanded', 'false');
            btnCustomize.setAttribute('aria-controls', 'cf-customize-panel');

            actions.appendChild(btnAccept);
            actions.appendChild(btnReject);
            actions.appendChild(btnCustomize);

            summary.appendChild(titleEl);
            summary.appendChild(descEl);
            summary.appendChild(actions);

            // ---- Customize panel -------------------------------------------
            var panel = document.createElement('div');
            panel.id              = 'cf-customize-panel';
            panel.className       = 'cf-panel';
            panel.setAttribute('hidden', '');
            panel.setAttribute('role', 'region');
            panel.setAttribute('aria-label', 'Cookie preferences');

            var panelInner = document.createElement('div');
            panelInner.className = 'cf-panel-inner';

            // Essential (always on, disabled toggle)
            panelInner.appendChild(this.createToggle(
                'essential',
                'Essential',
                'Required for the website to function. Cannot be disabled.',
                true,
                true
            ));

            // Analytics (opt-out — on by default)
            panelInner.appendChild(this.createToggle(
                'analytics',
                'Analytics',
                'Help us understand how visitors interact with the website. Opt-out available under Digital Omnibus 2026.',
                def.analytics,
                false
            ));

            // Performance (opt-out — on by default)
            panelInner.appendChild(this.createToggle(
                'performance',
                'Performance',
                'Enable enhanced site performance and personalisation features.',
                def.performance,
                false
            ));

            // Marketing (opt-in — off by default)
            panelInner.appendChild(this.createToggle(
                'marketing',
                'Marketing',
                'Allow us to personalise advertisements and measure their effectiveness. Opt-in required.',
                def.marketing,
                false
            ));

            var btnSave = document.createElement('button');
            btnSave.type      = 'button';
            btnSave.className = 'cf-btn cf-btn-primary cf-btn-save';
            btnSave.textContent = cfg.saveLabel || 'Save Preferences';

            panelInner.appendChild(btnSave);
            panel.appendChild(panelInner);

            // ---- Overlay (modal/sidebar only) --------------------------------
            var overlay = null;
            if (position === 'center_modal' || position === 'sidebar') {
                overlay = document.createElement('div');
                overlay.className = 'cf-overlay';
                overlay.setAttribute('aria-hidden', 'true');
            }

            // ---- Assemble ---------------------------------------------------
            wrap.appendChild(summary);
            wrap.appendChild(panel);

            // ---- Event listeners -------------------------------------------
            var self = this;

            btnAccept.addEventListener('click', function () { self.acceptAll(); });
            btnReject.addEventListener('click', function () { self.rejectAll(); });
            btnSave.addEventListener('click',   function () { self.saveCustom(); });

            btnCustomize.addEventListener('click', function () {
                self.showCustomizePanel();
            });

            // Keyboard: Escape = reject all (safe default), Tab = trapped within banner.
            wrap.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    self.rejectAll();
                } else if (e.key === 'Tab') {
                    self._trapFocus(e, wrap);
                }
            });

            // Click overlay to dismiss (reject all).
            if (overlay) {
                overlay.addEventListener('click', function () { self.rejectAll(); });
            }

            this._banner  = wrap;
            this._panel   = panel;
            this._overlay = overlay;

            return wrap;
        },

        createToggle: function (category, label, description, defaultValue, disabled) {
            var row = document.createElement('div');
            row.className = 'cf-toggle-row';

            var labelEl = document.createElement('label');
            labelEl.className = 'cf-toggle-label';
            labelEl.setAttribute('for', 'cf-toggle-' + category);

            var info = document.createElement('span');
            info.className = 'cf-toggle-info';

            var name = document.createElement('span');
            name.className   = 'cf-toggle-name';
            name.textContent = label;

            var desc = document.createElement('span');
            desc.className   = 'cf-toggle-desc';
            desc.textContent = description;

            info.appendChild(name);
            info.appendChild(desc);

            var input = document.createElement('input');
            input.type      = 'checkbox';
            input.id        = 'cf-toggle-' + category;
            input.name      = 'cf-' + category;
            input.checked   = !!defaultValue;
            input.disabled  = !!disabled;
            input.setAttribute('aria-describedby', 'cf-toggle-desc-' + category);

            desc.id = 'cf-toggle-desc-' + category;

            var track = document.createElement('span');
            track.className     = 'cf-switch';
            track.setAttribute('aria-hidden', 'true');

            labelEl.appendChild(info);
            labelEl.appendChild(input);
            labelEl.appendChild(track);

            row.appendChild(labelEl);

            if (disabled) {
                row.classList.add('cf-toggle-disabled');
                var badge = document.createElement('span');
                badge.className   = 'cf-badge';
                badge.textContent = 'Always Active';
                row.appendChild(badge);
            }

            return row;
        },

        showBanner: function () {
            if (this._banner) { return; }

            this._prevFocus = document.activeElement;

            var wrap    = this.createBanner();
            var cfg     = this.config.bannerConfig || {};
            var overlay = this._overlay;

            if (overlay) {
                document.body.appendChild(overlay);
                // Tiny rAF so CSS transition fires.
                requestAnimationFrame(function () {
                    overlay.classList.add('cf-overlay-visible');
                });
            }

            document.body.appendChild(wrap);
            document.body.classList.add('cf-banner-open');

            requestAnimationFrame(function () {
                wrap.classList.add('cf-visible');
            });

            // Move focus into banner — first interactive element.
            var firstBtn = wrap.querySelector('button:not([disabled])');
            if (firstBtn) {
                setTimeout(function () { firstBtn.focus(); }, 60);
            }

            this.fire('banner_show', {});
        },

        hideBanner: function () {
            var self    = this;
            var wrap    = this._banner;
            var overlay = this._overlay;

            if (!wrap) { return; }

            wrap.classList.remove('cf-visible');
            wrap.classList.add('cf-hiding');

            if (overlay) {
                overlay.classList.remove('cf-overlay-visible');
            }

            var done = function () {
                if (wrap.parentNode) { wrap.parentNode.removeChild(wrap); }
                if (overlay && overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
                document.body.classList.remove('cf-banner-open');
                self._banner  = null;
                self._panel   = null;
                self._overlay = null;
                if (self._prevFocus && self._prevFocus.focus) {
                    self._prevFocus.focus();
                }
                self.fire('banner_hide', {});
            };

            // Wait for CSS transition (300 ms) before removing from DOM.
            wrap.addEventListener('transitionend', done, { once: true });
            // Fallback if transitionend never fires.
            setTimeout(done, 400);
        },

        showCustomizePanel: function () {
            var panel    = this._panel;
            var btnCust  = this._banner && this._banner.querySelector('.cf-btn-ghost');

            if (!panel) { return; }

            var isHidden = panel.hasAttribute('hidden');

            if (isHidden) {
                panel.removeAttribute('hidden');
                if (btnCust) { btnCust.setAttribute('aria-expanded', 'true'); }
                // Focus first input in panel.
                var firstInput = panel.querySelector('input:not([disabled])');
                if (firstInput) { firstInput.focus(); }
            } else {
                panel.setAttribute('hidden', '');
                if (btnCust) { btnCust.setAttribute('aria-expanded', 'false'); }
            }
        },

        // ---------------------------------------------------------------------
        // Focus trap
        // ---------------------------------------------------------------------

        _trapFocus: function (e, container) {
            var focusable = container.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (!focusable.length) { return; }

            var first = focusable[0];
            var last  = focusable[focusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        },

        // ---------------------------------------------------------------------
        // REST API logging
        // ---------------------------------------------------------------------

        logConsent: function (state, source) {
            var cfg = this.config;
            if (!cfg.apiUrl || !cfg.nonce) { return; }

            var accepted = [];
            var rejected = [];
            var cats     = ['essential', 'analytics', 'performance', 'marketing'];

            for (var i = 0; i < cats.length; i++) {
                if (state[cats[i]]) { accepted.push(cats[i]); }
                else                { rejected.push(cats[i]); }
            }

            var signals = null;
            if (window.CFConsentModeV2) {
                signals = window.CFConsentModeV2.stateToSignals(state);
            }

            var body = JSON.stringify({
                consent_id:           state.consent_id,
                consent_type:         source || 'banner',
                categories_accepted:  accepted,
                categories_rejected:  rejected,
                gpc:                  !!state.gpc,
                consent_mode_signals: signals,
                banner_version:       cfg.version || '1.0.0',
            });

            // fetch is available in all browsers ConsentForge targets (WP 6.4+).
            fetch(cfg.apiUrl + '/consent', {
                method:      'POST',
                headers:     {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   cfg.nonce,
                },
                body:        body,
                credentials: 'same-origin',
            }).catch(function () {
                // Silent fail — consent is already stored client-side in cookie.
            });
        },

        // ---------------------------------------------------------------------
        // Custom events
        // ---------------------------------------------------------------------

        fire: function (eventName, detail) {
            try {
                document.dispatchEvent(new CustomEvent(EVENT_NS + eventName, {
                    detail:  detail || {},
                    bubbles: false,
                }));
            } catch (e) { /* IE11 fallback omitted — WP 6.4 target */ }
        },

        on: function (event, callback) {
            document.addEventListener(EVENT_NS + event, callback);
        },
    };

    // -------------------------------------------------------------------------
    // Expose globally
    // -------------------------------------------------------------------------

    window.ConsentForge = ConsentForge;

    // -------------------------------------------------------------------------
    // Auto-init
    // -------------------------------------------------------------------------

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { ConsentForge.init(); });
    } else {
        ConsentForge.init();
    }

})();
