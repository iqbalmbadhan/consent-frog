<?php
namespace ConsentForge\Compliance;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use ConsentForge\Core\SettingsManager;

class DataRetention {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( 'cf_cleanup_old_data', [ $this, 'cleanup' ] );
    }

    public function cleanup(): void {
        $this->cleanup_consent_logs();
        $this->cleanup_scan_results();
        $this->cleanup_dsar_requests();
    }

    public function cleanup_consent_logs(): int {
        global $wpdb;
        $days  = \absint( SettingsManager::instance()->get( 'data_retention_days', '1095', 'compliance' ) );
        $table = $wpdb->prefix . 'cf_consent_logs';
        return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    public function cleanup_scan_results(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'cf_scan_results';
        return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)"
        );
    }

    public function cleanup_dsar_requests(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'cf_dsar_requests';
        return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "DELETE FROM `{$table}` WHERE status = 'completed' AND completed_at < DATE_SUB(NOW(), INTERVAL 1095 DAY)"
        );
    }
}
