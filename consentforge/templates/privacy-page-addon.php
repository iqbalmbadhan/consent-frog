<?php
/**
 * Auto-appended to the Privacy Policy page.
 * Renders cookie categories table and DSAR form link.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<h2 id="cookies"><?php esc_html_e( 'Cookie Policy', 'consentforge' ); ?></h2>
<p><?php esc_html_e( 'This site uses cookies in accordance with the EU Digital Omnibus framework and GDPR. The categories below describe what we use and why.', 'consentforge' ); ?></p>

<table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
        <tr style="background:#f9fafb">
            <th style="text-align:left;padding:10px;border-bottom:2px solid #e5e7eb"><?php esc_html_e( 'Category', 'consentforge' ); ?></th>
            <th style="text-align:left;padding:10px;border-bottom:2px solid #e5e7eb"><?php esc_html_e( 'Purpose', 'consentforge' ); ?></th>
            <th style="text-align:left;padding:10px;border-bottom:2px solid #e5e7eb"><?php esc_html_e( 'Legal Basis', 'consentforge' ); ?></th>
            <th style="text-align:left;padding:10px;border-bottom:2px solid #e5e7eb"><?php esc_html_e( 'Model', 'consentforge' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><strong><?php esc_html_e( 'Essential', 'consentforge' ); ?></strong></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Required for basic site functionality (sessions, security)', 'consentforge' ); ?></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Necessary (Art. 6(1)(b) GDPR)', 'consentforge' ); ?></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Exempt — cannot be disabled', 'consentforge' ); ?></td>
        </tr>
        <tr>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><strong><?php esc_html_e( 'Analytics', 'consentforge' ); ?></strong></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Understand how visitors use the site (page views, sessions)', 'consentforge' ); ?></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Legitimate Interest (Art. 6(1)(f) GDPR — Digital Omnibus)', 'consentforge' ); ?></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Opt-out (enabled by default)', 'consentforge' ); ?></td>
        </tr>
        <tr>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><strong><?php esc_html_e( 'Performance', 'consentforge' ); ?></strong></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Improve page speed and user experience', 'consentforge' ); ?></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Legitimate Interest (Digital Omnibus)', 'consentforge' ); ?></td>
            <td style="padding:10px;border-bottom:1px solid #f3f4f6"><?php esc_html_e( 'Opt-out (enabled by default)', 'consentforge' ); ?></td>
        </tr>
        <tr>
            <td style="padding:10px"><strong><?php esc_html_e( 'Marketing', 'consentforge' ); ?></strong></td>
            <td style="padding:10px"><?php esc_html_e( 'Personalised advertising and retargeting', 'consentforge' ); ?></td>
            <td style="padding:10px"><?php esc_html_e( 'Consent (Art. 6(1)(a) GDPR)', 'consentforge' ); ?></td>
            <td style="padding:10px"><?php esc_html_e( 'Opt-in (disabled by default)', 'consentforge' ); ?></td>
        </tr>
    </tbody>
</table>

<h2 id="your-rights"><?php esc_html_e( 'Your Data Rights (GDPR)', 'consentforge' ); ?></h2>
<p><?php esc_html_e( 'You have the right to access, correct, delete, or export your personal data. To submit a data request:', 'consentforge' ); ?></p>
<p><a href="<?php echo esc_url( home_url( '/data-request/' ) ); ?>" class="button"><?php esc_html_e( 'Submit a Data Request', 'consentforge' ); ?></a></p>
<p><?php esc_html_e( 'You may also withdraw your cookie consent at any time by clicking the "Manage cookies" link in the footer.', 'consentforge' ); ?></p>
