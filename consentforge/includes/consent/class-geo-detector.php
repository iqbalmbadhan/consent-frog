<?php
namespace ConsentForge\Consent;

class GeoDetector {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function get_country(): string {
        // Cloudflare header
        if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
            return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
        }
        // Generic CDN header
        if ( ! empty( $_SERVER['HTTP_X_COUNTRY_CODE'] ) ) {
            return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_COUNTRY_CODE'] ) ) );
        }
        // Fastly/other
        if ( ! empty( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) {
            return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) );
        }
        return '';
    }

    public function is_eu_visitor(): bool {
        $country = $this->get_country();
        if ( ! $country ) {
            return true; // Default to EU rules when unknown
        }
        return in_array( $country, $this->get_eu_countries(), true );
    }

    public function get_eu_countries(): array {
        return [
            'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR',
            'HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK',
            // EEA
            'IS','LI','NO',
            // UK GDPR
            'GB',
        ];
    }
}
