<?php
namespace ConsentForge\Migration;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use ConsentForge\Core\CookieRegistry;
use ConsentForge\Core\SettingsManager;

class CookieYesImport {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function can_import(): bool {
        return (bool) \get_option( 'cky_consent_state' ) || (bool) \get_option( 'cookieyes_settings' );
    }

    public function import(): array {
        $result   = [ 'cookies_migrated' => 0, 'settings_migrated' => 0, 'warnings' => [] ];
        $raw      = \get_option( 'cky_cookie_list', [] );

        if ( empty( $raw ) ) {
            $result['warnings'][] = 'No CookieYes cookie list found.';
            return $result;
        }

        $map = [ 'analytics' => 'analytics', 'advertisement' => 'marketing', 'functional' => 'performance', 'necessary' => 'essential' ];

        foreach ( (array) $raw as $category => $cookies ) {
            $cf_cat = $map[ strtolower( (string) $category ) ] ?? 'unclassified';
            foreach ( (array) $cookies as $c ) {
                $inserted = CookieRegistry::instance()->add( [
                    'cookie_name'      => \sanitize_text_field( $c['name'] ?? (string) $c ),
                    'category'         => $cf_cat,
                    'provider'         => \sanitize_text_field( $c['domain'] ?? '' ),
                    'duration'         => \sanitize_text_field( $c['duration'] ?? '' ),
                    'is_auto_detected' => 0,
                ] );
                if ( $inserted ) {
                    $result['cookies_migrated']++;
                }
            }
        }

        // Import basic banner colors
        $settings = \get_option( 'cookieyes_settings', [] );
        if ( ! empty( $settings['button_color'] ) ) {
            SettingsManager::instance()->set( 'primary_color', sanitize_hex_color( $settings['button_color'] ), 'banner' );
            $result['settings_migrated']++;
        }

        return $result;
    }
}
