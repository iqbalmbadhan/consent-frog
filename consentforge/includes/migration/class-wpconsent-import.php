<?php
namespace ConsentForge\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use ConsentForge\Core\CookieRegistry;

class WpConsentImport {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function can_import(): bool {
        return (bool) \get_option( 'wpconsent_cookies' ) || (bool) \get_option( 'wpconsent_version' );
    }

    public function import(): array {
        $result  = [ 'cookies_migrated' => 0, 'settings_migrated' => 0, 'warnings' => [] ];
        $cookies = \get_option( 'wpconsent_cookies', [] );

        if ( empty( $cookies ) ) {
            $result['warnings'][] = 'No WPConsent cookie data found.';
            return $result;
        }

        $map = [ 'statistics' => 'analytics', 'marketing' => 'marketing', 'preferences' => 'performance', 'necessary' => 'essential' ];

        foreach ( (array) $cookies as $c ) {
            $cat      = $map[ strtolower( $c['category'] ?? '' ) ] ?? 'unclassified';
            $inserted = CookieRegistry::instance()->add( [
                'cookie_name'      => \sanitize_text_field( $c['name'] ?? '' ),
                'category'         => $cat,
                'provider'         => \sanitize_text_field( $c['plugin'] ?? '' ),
                'purpose'          => \sanitize_textarea_field( $c['description'] ?? '' ),
                'duration'         => \sanitize_text_field( $c['expiration'] ?? '' ),
                'is_auto_detected' => 0,
            ] );
            if ( $inserted ) {
                $result['cookies_migrated']++;
            }
        }

        return $result;
    }
}
