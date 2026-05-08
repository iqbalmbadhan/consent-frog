(function () {
    'use strict';

    const DSARForm = {
        form: null,
        messageEl: null,

        init() {
            this.form = document.getElementById('cf-dsar-form');
            if (!this.form) return;
            this.messageEl = document.getElementById('cf-dsar-message');
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        },

        async handleSubmit(e) {
            e.preventDefault();
            const submitBtn = this.form.querySelector('[type="submit"]');
            const data = {
                requester_email: this.form.querySelector('[name="email"]').value.trim(),
                requester_name:  this.form.querySelector('[name="name"]').value.trim(),
                request_type:    this.form.querySelector('[name="request_type"]').value,
                nonce:           window.CFDsarData ? window.CFDsarData.nonce : '',
            };

            if (!data.requester_email) {
                this.showMessage(window.CFDsarData.i18n.emailRequired, 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = window.CFDsarData.i18n.submitting;

            try {
                const response = await fetch(window.CFDsarData.apiUrl + '/consentforge/v1/dsar/request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
                    body: JSON.stringify(data),
                });
                const result = await response.json();
                if (result.success !== false) {
                    this.showMessage(window.CFDsarData.i18n.success, 'success');
                    this.form.reset();
                } else {
                    this.showMessage(result.message || window.CFDsarData.i18n.error, 'error');
                }
            } catch {
                this.showMessage(window.CFDsarData.i18n.error, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = window.CFDsarData.i18n.submit;
            }
        },

        showMessage(text, type) {
            if (!this.messageEl) return;
            this.messageEl.textContent = text;
            this.messageEl.className = 'cf-dsar-message ' + type;
            this.messageEl.style.display = 'block';
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => DSARForm.init());
    } else {
        DSARForm.init();
    }
})();
