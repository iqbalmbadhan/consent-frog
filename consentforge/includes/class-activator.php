<?php
namespace ConsentForge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate(): void {
        // PHP version check.
        if ( version_compare( PHP_VERSION, CF_MIN_PHP, '<' ) ) {
            deactivate_plugins( CF_PLUGIN_BASENAME );
            wp_die(
                sprintf(
                    /* translators: 1: required PHP version, 2: current PHP version */
                    esc_html__( 'ConsentForge requires PHP %1$s or higher. Your server is running PHP %2$s.', 'consentforge' ),
                    esc_html( CF_MIN_PHP ),
                    esc_html( PHP_VERSION )
                ),
                esc_html__( 'Plugin Activation Error', 'consentforge' ),
                [ 'back_link' => true ]
            );
        }

        // WordPress version check.
        global $wp_version;
        if ( version_compare( $wp_version, CF_MIN_WP, '<' ) ) {
            deactivate_plugins( CF_PLUGIN_BASENAME );
            wp_die(
                sprintf(
                    /* translators: 1: required WP version, 2: current WP version */
                    esc_html__( 'ConsentForge requires WordPress %1$s or higher. Your installation is running WordPress %2$s.', 'consentforge' ),
                    esc_html( CF_MIN_WP ),
                    esc_html( $wp_version )
                ),
                esc_html__( 'Plugin Activation Error', 'consentforge' ),
                [ 'back_link' => true ]
            );
        }

        self::create_tables();
        self::set_default_settings();

        // Schedule cron events.
        if ( ! wp_next_scheduled( 'cf_daily_scan' ) ) {
            wp_schedule_event( time(), 'daily', 'cf_daily_scan' );
        }
        if ( ! wp_next_scheduled( 'cf_cleanup_old_data' ) ) {
            wp_schedule_event( time(), 'daily', 'cf_cleanup_old_data' );
        }

        update_option( 'cf_activated', true );
        update_option( 'cf_version', CF_VERSION );

        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE {$wpdb->prefix}cf_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_group VARCHAR(100) NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT NOT NULL,
            autoload TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_group_key (setting_group, setting_key)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}cf_cookies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cookie_name VARCHAR(255) NOT NULL,
            cookie_domain VARCHAR(255) NOT NULL DEFAULT '',
            category ENUM('necessary','analytics','performance','marketing','functional','unknown') NOT NULL DEFAULT 'unknown',
            provider VARCHAR(255) NOT NULL DEFAULT '',
            purpose TEXT NOT NULL,
            duration VARCHAR(100) NOT NULL DEFAULT '',
            is_third_party TINYINT(1) NOT NULL DEFAULT 0,
            script_pattern TEXT NOT NULL,
            is_auto_detected TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            consent_model ENUM('opt_in','opt_out','exempt') NOT NULL DEFAULT 'opt_in',
            legal_basis VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY consent_model (consent_model),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}cf_consent_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            consent_id VARCHAR(64) NOT NULL,
            visitor_hash VARCHAR(64) NOT NULL,
            consent_type ENUM('banner','gpc','api','import','renewal') NOT NULL DEFAULT 'banner',
            categories_accepted JSON NOT NULL,
            categories_rejected JSON NOT NULL,
            gpc_detected TINYINT(1) NOT NULL DEFAULT 0,
            consent_mode_signals JSON NOT NULL,
            ip_country VARCHAR(2) NOT NULL DEFAULT '',
            user_agent_hash VARCHAR(64) NOT NULL DEFAULT '',
            banner_version VARCHAR(20) NOT NULL DEFAULT '',
            receipt_hash VARCHAR(128) NOT NULL DEFAULT '',
            plugin_version VARCHAR(20) NOT NULL DEFAULT '',
            wordpress_user_id BIGINT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY consent_id (consent_id),
            KEY visitor_hash (visitor_hash),
            KEY gpc_detected (gpc_detected),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}cf_receipts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            receipt_id VARCHAR(64) NOT NULL,
            consent_log_id BIGINT UNSIGNED NOT NULL,
            receipt_data JSON NOT NULL,
            receipt_hash VARCHAR(128) NOT NULL,
            previous_hash VARCHAR(128) NOT NULL DEFAULT '',
            receipt_version VARCHAR(10) NOT NULL DEFAULT '1.0',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY receipt_id (receipt_id),
            KEY consent_log_id (consent_log_id),
            CONSTRAINT fk_cf_receipts_consent_log FOREIGN KEY (consent_log_id) REFERENCES {$wpdb->prefix}cf_consent_logs (id) ON DELETE CASCADE
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}cf_scan_results (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_type ENUM('full','page','scheduled','manual') NOT NULL DEFAULT 'full',
            scanned_url VARCHAR(2048) NOT NULL DEFAULT '',
            cookies_found JSON NOT NULL,
            scripts_found JSON NOT NULL,
            local_storage_found JSON NOT NULL,
            pixels_found JSON NOT NULL,
            new_cookies_count INT NOT NULL DEFAULT 0,
            categorized_count INT NOT NULL DEFAULT 0,
            uncategorized_count INT NOT NULL DEFAULT 0,
            scan_duration_ms INT NOT NULL DEFAULT 0,
            scan_status ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
            error_message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scan_type (scan_type),
            KEY scan_status (scan_status),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}cf_dsar_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id VARCHAR(64) NOT NULL,
            request_type ENUM('access','erasure','portability','rectification','restriction','objection') NOT NULL DEFAULT 'access',
            requester_email VARCHAR(255) NOT NULL,
            requester_name VARCHAR(255) NOT NULL DEFAULT '',
            requester_verified TINYINT(1) NOT NULL DEFAULT 0,
            verification_token VARCHAR(128) NOT NULL DEFAULT '',
            verification_expires DATETIME NULL DEFAULT NULL,
            status ENUM('pending','verified','in_progress','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
            data_sources_scanned JSON NOT NULL,
            data_found JSON NOT NULL,
            data_export_path VARCHAR(512) NOT NULL DEFAULT '',
            completed_at DATETIME NULL DEFAULT NULL,
            notes TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY request_id (request_id),
            KEY requester_email (requester_email),
            KEY status (status),
            KEY request_type (request_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }
    }

    private static function set_default_settings(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'cf_settings';

        $defaults = [
            [ 'general',    'consent_model',      'digital_omnibus' ],
            [ 'general',    'analytics_model',    'opt_out' ],
            [ 'general',    'performance_model',  'opt_out' ],
            [ 'general',    'marketing_model',    'opt_in' ],
            [ 'general',    'gpc_enabled',        '1' ],
            [ 'general',    'respect_dnt',        '0' ],
            [ 'general',    'cookie_lifetime',    '365' ],
            [ 'banner',     'position',           'bottom_bar' ],
            [ 'banner',     'show_reject_button', '1' ],
            [ 'banner',     'animation',          'slide_up' ],
            [ 'banner',     'accept_label',       'Accept All' ],
            [ 'banner',     'reject_label',       'Reject All' ],
            [ 'banner',     'customize_label',    'Customize' ],
            [ 'banner',     'save_label',         'Save Preferences' ],
            [ 'banner',     'primary_color',      '#2563eb' ],
            [ 'banner',     'background_color',   '#ffffff' ],
            [ 'banner',     'text_color',         '#1f2937' ],
            [ 'compliance', 'dsar_enabled',           '0' ],
            [ 'compliance', 'ai_act_enabled',          '0' ],
            [ 'compliance', 'data_retention_days',     '1095' ],
        ];

        foreach ( $defaults as [ $group, $key, $value ] ) {
            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prepare(
                    "INSERT IGNORE INTO `{$table}` (setting_group, setting_key, setting_value) VALUES (%s, %s, %s)",
                    $group,
                    $key,
                    $value
                )
            );
        }
    }
}
