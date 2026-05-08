<?php
/**
 * Bottom bar banner template.
 * Variables available: $settings (array), $categories (array), $texts (array).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div id="cf-banner" class="cf-bottom-bar" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Cookie consent', 'consentforge' ); ?>" style="display:none">
    <div class="cf-banner-body">
        <div class="cf-banner-title"><?php echo esc_html( $texts['title'] ?? __( 'We value your privacy', 'consentforge' ) ); ?></div>
        <p class="cf-banner-text"><?php echo wp_kses_post( $texts['description'] ?? '' ); ?></p>
    </div>
    <div class="cf-banner-actions">
        <button type="button" class="cf-btn cf-btn-primary" id="cf-accept-all"><?php echo esc_html( $settings['accept_label'] ?? __( 'Accept All', 'consentforge' ) ); ?></button>
        <?php if ( ! empty( $settings['show_reject_button'] ) && '1' === $settings['show_reject_button'] ) : ?>
        <button type="button" class="cf-btn cf-btn-secondary" id="cf-reject-all"><?php echo esc_html( $settings['reject_label'] ?? __( 'Reject All', 'consentforge' ) ); ?></button>
        <?php endif; ?>
        <button type="button" class="cf-btn cf-btn-link" id="cf-customize"><?php echo esc_html( $settings['customize_label'] ?? __( 'Customize', 'consentforge' ) ); ?></button>
    </div>

    <div id="cf-customize-panel" role="region" aria-label="<?php esc_attr_e( 'Cookie preferences', 'consentforge' ); ?>">
        <?php foreach ( $categories as $key => $cat ) : ?>
        <div class="cf-category">
            <div class="cf-category-info">
                <div class="cf-category-name"><?php echo esc_html( $cat['label'] ); ?></div>
                <div class="cf-category-desc"><?php echo esc_html( $cat['description'] ?? '' ); ?></div>
                <span class="cf-category-model cf-model-<?php echo esc_attr( str_replace( '_', '-', $cat['model'] ) ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', '-', $cat['model'] ) ) ); ?></span>
            </div>
            <label class="cf-toggle" aria-label="<?php echo esc_attr( $cat['label'] ); ?>">
                <input type="checkbox"
                    name="cf_cat_<?php echo esc_attr( $key ); ?>"
                    data-cf-category="<?php echo esc_attr( $key ); ?>"
                    <?php checked( $cat['default'], true ); ?>
                    <?php disabled( 'essential' === $key ); ?>
                >
                <span class="cf-toggle-track"></span>
            </label>
        </div>
        <?php endforeach; ?>

        <div class="cf-save-row">
            <button type="button" class="cf-btn cf-btn-primary" id="cf-save-custom"><?php echo esc_html( $settings['save_label'] ?? __( 'Save Preferences', 'consentforge' ) ); ?></button>
        </div>
    </div>
</div>
