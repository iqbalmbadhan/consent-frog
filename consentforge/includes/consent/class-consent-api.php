<?php
namespace ConsentForge\Consent;

use ConsentForge\Core\ConsentEngine;
use ConsentForge\Core\ConsentLogger;
use ConsentForge\Core\ReceiptGenerator;
use ConsentForge\Core\SettingsManager;
use ConsentForge\Licensing\FeatureGate;
use WP_REST_Request;
use WP_REST_Response;

class ConsentApi {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $ns = 'consentforge/v1';

        register_rest_route( $ns, '/consent', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'record_consent' ],
            'permission_callback' => [ $this, 'check_rate_limit' ],
            'args'                => [
                'categories' => [ 'required' => true, 'type' => 'object' ],
                'source'     => [ 'required' => false, 'type' => 'string', 'default' => 'banner_accept' ],
            ],
        ] );

        register_rest_route( $ns, '/consent/state', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_state' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/settings/banner', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_banner_config' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/cookies/categories', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_categories' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function check_rate_limit( WP_REST_Request $request ): bool {
        $ip_hash  = hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' );
        $key      = 'cf_rate_' . $ip_hash;
        $count    = (int) get_transient( $key );
        if ( $count >= 10 ) {
            return false;
        }
        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
        return true;
    }

    public function record_consent( WP_REST_Request $request ): WP_REST_Response {
        $categories_raw = $request->get_param( 'categories' );
        $source         = sanitize_text_field( $request->get_param( 'source' ) ?? 'banner_accept' );
        $gpc            = (bool) ( $request->get_param( 'gpc' ) ?? false );

        $allowed = [ 'essential', 'analytics', 'performance', 'marketing' ];
        $categories = [];
        foreach ( $allowed as $cat ) {
            $categories[ $cat ] = ! empty( $categories_raw[ $cat ] );
        }
        $categories['essential'] = true; // Always true

        $engine     = ConsentEngine::instance();
        $consent_id = $engine->set_consent_state( $categories, $source );

        $accepted = array_keys( array_filter( $categories ) );
        $rejected = array_keys( array_filter( $categories, fn( $v ) => ! $v ) );

        $log_id     = ConsentLogger::instance()->log( $consent_id, $source, $accepted, $rejected, $gpc );
        $receipt_id = null;

        if ( $log_id && FeatureGate::can( 'consent_receipts' ) ) {
            $receipt_id = ReceiptGenerator::instance()->generate( $log_id, $categories, $source );
        }

        return new WP_REST_Response( [
            'success'    => true,
            'consent_id' => $consent_id,
            'receipt_id' => $receipt_id,
        ], 200 );
    }

    public function get_state( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( ConsentEngine::instance()->get_consent_state(), 200 );
    }

    public function get_banner_config( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( SettingsManager::instance()->get_banner_config(), 200 );
    }

    public function get_categories( WP_REST_Request $request ): WP_REST_Response {
        $settings = SettingsManager::instance();
        return new WP_REST_Response( [
            'essential'   => [ 'label' => __( 'Essential', 'consentforge' ),   'model' => 'exempt',   'default' => true ],
            'analytics'   => [ 'label' => __( 'Analytics', 'consentforge' ),   'model' => 'opt_out',  'default' => true ],
            'performance' => [ 'label' => __( 'Performance', 'consentforge' ), 'model' => 'opt_out',  'default' => true ],
            'marketing'   => [ 'label' => __( 'Marketing', 'consentforge' ),   'model' => 'opt_in',   'default' => false ],
        ], 200 );
    }
}
