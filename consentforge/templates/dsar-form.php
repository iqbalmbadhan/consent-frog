<?php
/**
 * Frontend DSAR request form template.
 * Usage: [consentforge_dsar_form]
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="cf-dsar-form-wrap">
    <h2><?php esc_html_e( 'Submit a Data Request', 'consentforge' ); ?></h2>
    <p class="cf-dsar-intro"><?php esc_html_e( 'Under GDPR, you have the right to access, delete, or export your personal data. Please complete this form and verify your email address to proceed.', 'consentforge' ); ?></p>

    <form id="cf-dsar-form" class="cf-dsar-form" novalidate>
        <div class="cf-dsar-field">
            <label for="cf-dsar-name"><?php esc_html_e( 'Your Name', 'consentforge' ); ?></label>
            <input type="text" id="cf-dsar-name" name="name" autocomplete="name" />
        </div>
        <div class="cf-dsar-field">
            <label for="cf-dsar-email"><?php esc_html_e( 'Email Address', 'consentforge' ); ?> <span style="color:#dc2626">*</span></label>
            <input type="email" id="cf-dsar-email" name="email" required autocomplete="email" />
        </div>
        <div class="cf-dsar-field">
            <label for="cf-dsar-type"><?php esc_html_e( 'Request Type', 'consentforge' ); ?> <span style="color:#dc2626">*</span></label>
            <select id="cf-dsar-type" name="request_type" required>
                <option value="access"><?php esc_html_e( 'Access — What data do you hold about me?', 'consentforge' ); ?></option>
                <option value="deletion"><?php esc_html_e( 'Deletion — Please delete my data (Right to Erasure)', 'consentforge' ); ?></option>
                <option value="portability"><?php esc_html_e( 'Portability — Export my data in a readable format', 'consentforge' ); ?></option>
                <option value="rectification"><?php esc_html_e( 'Rectification — Correct inaccurate data', 'consentforge' ); ?></option>
                <option value="objection"><?php esc_html_e( 'Objection — Object to data processing', 'consentforge' ); ?></option>
            </select>
        </div>
        <button type="submit" class="cf-dsar-submit"><?php esc_html_e( 'Submit Request', 'consentforge' ); ?></button>
    </form>
    <div id="cf-dsar-message" class="cf-dsar-message" style="display:none" role="alert"></div>
</div>
