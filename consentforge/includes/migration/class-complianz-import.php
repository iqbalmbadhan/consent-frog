<?php
namespace ConsentForge\Migration;

use ConsentForge\Core\CookieRegistry;
use ConsentForge\Core\SettingsManager;

class ComplianzImport {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function can_import(): bool {
        return (bool) get_option( 'cmplz_version' );
    }

    public function import(): array {
        $result = [ 'cookies_migrated' => 0, 'settings_migrated' => 0, 'warnings' => [] ];

        $result['cookies_migrated']  += $this->import_cookies( $result['warnings'] );
        $result['settings_migrated'] += $this->import_settings( $result['warnings'] );

        return $result;
    }

    private function import_cookies( array &$warnings ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'cmplz_cookies';
        if ( ! $this->table_exists( $table ) ) {
            $warnings[] = 'Complianz cookie table not found; skipping cookie import.';
            return 0;
        }

        $cookies = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $count   = 0;
        $map     = [ 'statistics' => 'analytics', 'marketing' => 'marketing', 'functional' => 'performance', 'preferences' => 'performance' ];

        foreach ( (array) $cookies as $c ) {
            $category = $map[ strtolower( $c['category'] ?? '' ) ] ?? 'unclassified';
            $inserted = CookieRegistry::instance()->add( [
                'cookie_name'      => sanitize_text_field( $c['name'] ?? '' ),
                'cookie_domain'    => sanitize_text_field( $c['domain'] ?? '' ),
                'category'         => $category,
                'provider'         => sanitize_text_field( $c['service'] ?? '' ),
                'purpose'          => sanitize_textarea_field( $c['description'] ?? '' ),
                'duration'         => sanitize_text_field( $c['retention_period'] ?? '' ),
                'is_auto_detected' => 0,
            ] );
            if ( $inserted ) {
                $count++;
            }
        }
        return $count;
    }

    private function import_settings( array &$warnings ): int {
        $options = get_option( 'cmplz_options', [] );
        if ( empty( $options ) ) {
            $warnings[] = 'No Complianz options found; skipping settings import.';
            return 0;
        }

        $settings = SettingsManager::instance();
        $count    = 0;

        if ( ! empty( $options['color_button_accent'] ) ) {
            $settings->set( 'primary_color', sanitize_hex_color( $options['color_button_accent'] ), 'banner' );
            $count++;
        }
        if ( ! empty( $options['color_background'] ) ) {
            $settings->set( 'background_color', sanitize_hex_color( $options['color_background'] ), 'banner' );
            $count++;
        }

        return $count;
    }

    private function table_exists( string $table ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
}
