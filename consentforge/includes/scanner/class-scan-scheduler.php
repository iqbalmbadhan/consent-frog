<?php
namespace ConsentForge\Scanner;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ScanScheduler {

    private static ?self $instance = null;
    private const CRON_HOOK = 'cf_daily_scan';

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( self::CRON_HOOK, [ $this, 'run_scheduled_scan' ] );
    }

    public function schedule_scan(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    public function unschedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    public function run_scheduled_scan(): void {
        $scanner = CookieScanner::instance();
        $scan_id = $scanner->run_scan( '', 'scheduled' );

        $results = $scanner->get_scan_results( $scan_id );
        if ( $results && (int) $results['uncategorized_count'] > 0 ) {
            $admin_email = \get_option( 'admin_email' );
            $site_name   = \get_bloginfo( 'name' );
            $count       = (int) $results['uncategorized_count'];

            \wp_mail(
                $admin_email,
                /* translators: %s: site name */
                sprintf( \__( '[%s] ConsentForge: New unclassified cookies detected', 'consentforge' ), $site_name ),
                sprintf(
                    /* translators: 1: cookie count 2: admin URL */
                    \__( "ConsentForge found %d new unclassified cookie(s) during today's scan.\n\nPlease review and categorize them here: %s", 'consentforge' ),
                    $count,
                    \admin_url( 'admin.php?page=consentforge-scanner' )
                )
            );
        }
    }

    public function is_scheduled(): bool {
        return (bool) wp_next_scheduled( self::CRON_HOOK );
    }
}
