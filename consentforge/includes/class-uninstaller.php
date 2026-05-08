<?php
namespace ConsentForge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Uninstaller {

    public static function uninstall(): void {
        if ( get_option( 'cf_remove_data_on_uninstall' ) !== '1' ) {
            return;
        }

        self::drop_tables();
        self::delete_options();
        self::delete_user_meta();
    }

    private static function drop_tables(): void {
        global $wpdb;

        // Drop in reverse dependency order so foreign keys do not block.
        $tables = [
            $wpdb->prefix . 'cf_receipts',
            $wpdb->prefix . 'cf_consent_logs',
            $wpdb->prefix . 'cf_scan_results',
            $wpdb->prefix . 'cf_dsar_requests',
            $wpdb->prefix . 'cf_cookies',
            $wpdb->prefix . 'cf_settings',
        ];

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    private static function delete_options(): void {
        global $wpdb;

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE 'cf\_%'"
        );
    }

    private static function delete_user_meta(): void {
        global $wpdb;

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE 'cf\_%'"
        );
    }
}
