<?php
namespace ConsentForge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( 'cf_daily_scan' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cf_daily_scan' );
        }

        $timestamp = wp_next_scheduled( 'cf_cleanup_old_data' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cf_cleanup_old_data' );
        }

        flush_rewrite_rules();
    }
}
