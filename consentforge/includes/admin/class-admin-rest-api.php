<?php
namespace ConsentForge\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use ConsentForge\Core\CookieRegistry;
use ConsentForge\Core\ConsentLogger;
use ConsentForge\Core\ReceiptGenerator;
use ConsentForge\Core\SettingsManager;
use ConsentForge\Scanner\CookieScanner;
use ConsentForge\Compliance\DsarHandler;
use WP_REST_Request;
use WP_REST_Response;

class AdminRestApi {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $ns   = 'consentforge/v1';
        $auth = [ $this, 'require_admin' ];

        $routes = [
            [ '/admin/dashboard/stats',                     'GET',    'get_dashboard_stats' ],
            [ '/admin/cookies',                             'GET',    'get_cookies' ],
            [ '/admin/cookies',                             'POST',   'create_cookie' ],
            [ '/admin/cookies/(?P<id>\d+)',                 'PUT',    'update_cookie' ],
            [ '/admin/cookies/(?P<id>\d+)',                 'DELETE', 'delete_cookie' ],
            [ '/admin/scanner/run',                         'POST',   'run_scanner' ],
            [ '/admin/scanner/results',                     'GET',    'get_scanner_results' ],
            [ '/admin/consent-logs',                        'GET',    'get_consent_logs' ],
            [ '/admin/receipts/(?P<id>[a-f0-9\\-]+)',       'GET',    'get_receipt' ],
            [ '/admin/receipts/verify',                     'POST',   'verify_receipts' ],
            [ '/admin/dsar',                                'GET',    'get_dsar_requests' ],
            [ '/admin/dsar/(?P<id>\d+)/process',            'POST',   'process_dsar' ],
            [ '/admin/settings/(?P<group>[a-z_]+)',         'GET',    'get_settings' ],
            [ '/admin/settings/(?P<group>[a-z_]+)',         'PUT',    'update_settings' ],
        ];

        foreach ( $routes as [ $path, $method, $callback ] ) {
            \register_rest_route( $ns, $path, [
                'methods'             => $method,
                'callback'            => [ $this, $callback ],
                'permission_callback' => $auth,
            ] );
        }
    }

    public function require_admin( WP_REST_Request $request ): bool {
        return \current_user_can( 'manage_options' );
    }

    public function get_dashboard_stats( WP_REST_Request $request ): WP_REST_Response {
        $logger = ConsentLogger::instance();
        return new WP_REST_Response( [
            'stats'            => $logger->get_stats(),
            'trend'            => $logger->get_trend( 30 ),
            'recent_logs'      => $logger->get_logs( [ 'per_page' => 10 ] ),
            'compliance_score' => $this->calculate_compliance_score(),
            'scan_count'       => count( CookieScanner::instance()->get_recent_scans( 1 ) ),
        ], 200 );
    }

    public function get_cookies( WP_REST_Request $request ): WP_REST_Response {
        $page     = \absint( $request->get_param( 'page' ) ?? 1 );
        $category = \sanitize_text_field( $request->get_param( 'category' ) ?? '' );
        $cookies  = CookieRegistry::instance()->get_all( false );

        if ( $category ) {
            $cookies = array_filter( $cookies, fn( $c ) => $c['category'] === $category );
        }

        $per_page = 50;
        $offset   = ( $page - 1 ) * $per_page;
        $total    = count( $cookies );
        $cookies  = array_slice( array_values( $cookies ), $offset, $per_page );

        return new WP_REST_Response( [
            'cookies'    => $cookies,
            'total'      => $total,
            'page'       => $page,
            'total_pages'=> (int) ceil( $total / $per_page ),
        ], 200 );
    }

    public function create_cookie( WP_REST_Request $request ): WP_REST_Response {
        $data = $this->sanitize_cookie_data( $request->get_params() );
        $id   = CookieRegistry::instance()->add( $data );
        if ( ! $id ) {
            return new WP_REST_Response( [ 'message' => \__( 'Could not create cookie.', 'consentforge' ) ], 400 );
        }
        return new WP_REST_Response( [ 'id' => $id ], 201 );
    }

    public function update_cookie( WP_REST_Request $request ): WP_REST_Response {
        $id   = \absint( $request->get_param( 'id' ) );
        $data = $this->sanitize_cookie_data( $request->get_params() );
        CookieRegistry::instance()->update( $id, $data );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function delete_cookie( WP_REST_Request $request ): WP_REST_Response {
        CookieRegistry::instance()->delete( \absint( $request->get_param( 'id' ) ) );
        return new WP_REST_Response( null, 204 );
    }

    public function run_scanner( WP_REST_Request $request ): WP_REST_Response {
        $scan_id = CookieScanner::instance()->run_scan();
        return new WP_REST_Response( [ 'scan_id' => $scan_id ], 202 );
    }

    public function get_scanner_results( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( CookieScanner::instance()->get_recent_scans( 20 ), 200 );
    }

    public function get_consent_logs( WP_REST_Request $request ): WP_REST_Response {
        $args = [
            'per_page'  => min( \absint( $request->get_param( 'per_page' ) ?? 20 ), 100 ),
            'page'      => max( 1, \absint( $request->get_param( 'page' ) ?? 1 ) ),
            'date_from' => \sanitize_text_field( $request->get_param( 'date_from' ) ?? '' ),
            'date_to'   => \sanitize_text_field( $request->get_param( 'date_to' ) ?? '' ),
        ];
        $logs = ConsentLogger::instance()->get_logs( $args );
        return new WP_REST_Response( $logs, 200 );
    }

    public function get_receipt( WP_REST_Request $request ): WP_REST_Response {
        $receipt = ReceiptGenerator::instance()->get_receipt( \sanitize_text_field( $request->get_param( 'id' ) ) );
        if ( ! $receipt ) {
            return new WP_REST_Response( [ 'message' => \__( 'Receipt not found.', 'consentforge' ) ], 404 );
        }
        return new WP_REST_Response( $receipt, 200 );
    }

    public function verify_receipts( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( [ 'valid' => ReceiptGenerator::instance()->verify_chain() ], 200 );
    }

    public function get_dsar_requests( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( DsarHandler::instance()->get_requests(), 200 );
    }

    public function process_dsar( WP_REST_Request $request ): WP_REST_Response {
        DsarHandler::instance()->process_request( \absint( $request->get_param( 'id' ) ) );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function get_settings( WP_REST_Request $request ): WP_REST_Response {
        $group = \sanitize_key( $request->get_param( 'group' ) );
        return new WP_REST_Response( SettingsManager::instance()->get_group( $group ), 200 );
    }

    public function update_settings( WP_REST_Request $request ): WP_REST_Response {
        $group    = \sanitize_key( $request->get_param( 'group' ) );
        $settings = $request->get_params();
        unset( $settings['group'] );
        SettingsManager::instance()->set_group( $group, $settings );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function calculate_compliance_score(): int {
        $score  = 0;
        global $wpdb;

        $cookie_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}cf_cookies`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        if ( $cookie_count > 0 ) {
            $score += 20;
        }

        $unclassified = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}cf_cookies` WHERE category = 'unclassified'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        if ( $cookie_count > 0 && 0 === $unclassified ) {
            $score += 20;
        }

        $settings = SettingsManager::instance();
        if ( $settings->get( 'primary_color', '', 'banner' ) ) {
            $score += 15;
        }
        if ( '1' === $settings->get( 'gpc_enabled', '1' ) ) {
            $score += 15;
        }
        if ( \ConsentForge\Licensing\FeatureGate::can( 'consent_receipts' ) ) {
            $score += 15;
        }
        if ( \ConsentForge\Licensing\FeatureGate::can( 'dsar_handling' ) ) {
            $score += 15;
        }

        return min( 100, $score );
    }

    private function sanitize_cookie_data( array $params ): array {
        return [
            'cookie_name'      => \sanitize_text_field( $params['cookie_name'] ?? '' ),
            'cookie_domain'    => \sanitize_text_field( $params['cookie_domain'] ?? '' ),
            'category'         => \sanitize_text_field( $params['category'] ?? 'unclassified' ),
            'provider'         => \sanitize_text_field( $params['provider'] ?? '' ),
            'purpose'          => \sanitize_textarea_field( $params['purpose'] ?? '' ),
            'duration'         => \sanitize_text_field( $params['duration'] ?? '' ),
            'is_third_party'   => (int) ( $params['is_third_party'] ?? 0 ),
            'script_pattern'   => \sanitize_text_field( $params['script_pattern'] ?? '' ),
            'is_auto_detected' => (int) ( $params['is_auto_detected'] ?? 0 ),
        ];
    }
}
