<?php
namespace ConsentForge\Compliance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class DsarHandler {

    private static ?self $instance = null;
    private const CRON_HOOK = 'cf_process_dsar';

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        \add_action( self::CRON_HOOK, [ $this, 'process_request' ] );
    }

    public function submit_request( array $data ): string|WP_Error {
        global $wpdb;

        $email = \sanitize_email( $data['requester_email'] ?? '' );
        if ( ! \is_email( $email ) ) {
            return new WP_Error( 'invalid_email', \__( 'A valid email address is required.', 'consentforge' ) );
        }

        $type = \sanitize_text_field( $data['request_type'] ?? '' );
        $allowed_types = [ 'access', 'deletion', 'rectification', 'portability', 'objection' ];
        if ( ! in_array( $type, $allowed_types, true ) ) {
            return new WP_Error( 'invalid_type', \__( 'Invalid request type.', 'consentforge' ) );
        }

        $request_id = \wp_generate_uuid4();
        $token      = bin2hex( random_bytes( 32 ) );
        $expires    = \gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prefix . 'cf_dsar_requests',
            [
                'request_id'           => $request_id,
                'request_type'         => $type,
                'requester_email'      => $email,
                'requester_name'       => \sanitize_text_field( $data['requester_name'] ?? '' ),
                'requester_verified'   => 0,
                'verification_token'   => hash( 'sha256', $token ),
                'verification_expires' => $expires,
                'status'               => 'pending',
                'created_at'           => \current_time( 'mysql' ),
                'updated_at'           => \current_time( 'mysql' ),
            ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', \__( 'Could not save request.', 'consentforge' ) );
        }

        $this->send_verification_email( $email, $token );
        \do_action( 'consentforge/dsar_submitted', $request_id );

        return $request_id;
    }

    public function verify_request( string $token ): bool|WP_Error {
        global $wpdb;
        $table      = $wpdb->prefix . 'cf_dsar_requests';
        $token_hash = hash( 'sha256', $token );

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT id, verification_expires FROM `{$table}` WHERE verification_token = %s AND status = 'pending' LIMIT 1",
                $token_hash
            )
        );

        if ( ! $row ) {
            return new WP_Error( 'invalid_token', \__( 'Invalid or expired verification link.', 'consentforge' ) );
        }

        if ( strtotime( $row->verification_expires ) < time() ) {
            return new WP_Error( 'token_expired', \__( 'Verification link has expired.', 'consentforge' ) );
        }

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            [ 'status' => 'verified', 'requester_verified' => 1, 'updated_at' => \current_time( 'mysql' ) ],
            [ 'id' => $row->id ]
        );

        \wp_schedule_single_event( time() + 60, self::CRON_HOOK, [ (int) $row->id ] );

        return true;
    }

    public function process_request( int $request_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'cf_dsar_requests';

        $row = $this->get_request( $request_id );
        if ( ! $row || 'verified' !== $row['status'] ) {
            return;
        }

        $wpdb->update( $table, [ 'status' => 'processing', 'updated_at' => \current_time( 'mysql' ) ], [ 'id' => $request_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        $finder    = DsarDataFinder::instance();
        $data      = $finder->find_all_data( $row['requester_email'] );
        $file_path = $this->generate_export( $request_id, $data );
        $token     = bin2hex( random_bytes( 32 ) );

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            [
                'status'             => 'completed',
                'data_found'         => \wp_json_encode( $data ),
                'data_export_path'   => $file_path,
                'verification_token' => hash( 'sha256', $token ),
                'completed_at'       => \current_time( 'mysql' ),
                'updated_at'         => \current_time( 'mysql' ),
            ],
            [ 'id' => $request_id ]
        );

        $download_url = \rest_url( 'consentforge/v1/dsar/download/' . $token );
        $this->send_completion_email( $row['requester_email'], $download_url );

        \do_action( 'consentforge/dsar_completed', $row['request_id'], $file_path );
    }

    public function get_requests( array $args = [] ): array {
        global $wpdb;
        $table    = $wpdb->prefix . 'cf_dsar_requests';
        $per_page = \absint( $args['per_page'] ?? 20 );
        $page     = max( 1, \absint( $args['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;
        $status   = \sanitize_text_field( $args['status'] ?? '' );

        $where = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM `{$table}` {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
                $per_page, $offset
            ),
            ARRAY_A
        ) ?: [];
    }

    public function get_request( int $id ): ?array {
        global $wpdb;
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}cf_dsar_requests` WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public function register_rest_routes(): void {
        $ns = 'consentforge/v1';

        \register_rest_route( $ns, '/dsar/request', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_submit' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'requester_email' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
                'requester_name'  => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'request_type'    => [ 'required' => true, 'type' => 'string', 'enum' => [ 'access', 'deletion', 'rectification', 'portability', 'objection' ] ],
            ],
        ] );

        \register_rest_route( $ns, '/dsar/verify/(?P<token>[a-f0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_verify' ],
            'permission_callback' => '__return_true',
        ] );

        \register_rest_route( $ns, '/dsar/download/(?P<token>[a-f0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_download' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function rest_submit( WP_REST_Request $request ): WP_REST_Response {
        // Rate limit: 3 per hour per IP
        $ip_hash = hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' );
        $key     = 'cf_dsar_rate_' . $ip_hash;
        $count   = (int) \get_transient( $key );
        if ( $count >= 3 ) {
            return new WP_REST_Response( [ 'message' => \__( 'Too many requests.', 'consentforge' ) ], 429 );
        }
        \set_transient( $key, $count + 1, HOUR_IN_SECONDS );

        $result = $this->submit_request( $request->get_params() );
        if ( \is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }
        return new WP_REST_Response( [ 'success' => true, 'request_id' => $result ], 201 );
    }

    public function rest_verify( WP_REST_Request $request ): WP_REST_Response {
        $result = $this->verify_request( \sanitize_text_field( $request->get_param( 'token' ) ) );
        if ( \is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }
        return new WP_REST_Response( [ 'success' => true, 'message' => \__( 'Your request has been verified and is being processed.', 'consentforge' ) ], 200 );
    }

    public function rest_download( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $token_hash = hash( 'sha256', \sanitize_text_field( $request->get_param( 'token' ) ) );
        $row        = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT data_export_path FROM `{$wpdb->prefix}cf_dsar_requests` WHERE verification_token = %s AND status = 'completed' LIMIT 1",
                $token_hash
            )
        );

        if ( ! $row || ! $row->data_export_path || ! file_exists( $row->data_export_path ) ) {
            return new WP_REST_Response( [ 'message' => \__( 'Export not found.', 'consentforge' ) ], 404 );
        }

        // Stream file
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="data-export.json"' );
        readfile( $row->data_export_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    private function send_verification_email( string $email, string $token ): void {
        $verify_url = \rest_url( 'consentforge/v1/dsar/verify/' . $token );
        $site_name  = \get_bloginfo( 'name' );
        \wp_mail(
            $email,
            /* translators: %s: site name */
            sprintf( \__( '[%s] Verify your data request', 'consentforge' ), $site_name ),
            sprintf(
                /* translators: %s: verification URL */
                \__( "Please verify your data request by clicking the link below:\n\n%s\n\nThis link expires in 24 hours.", 'consentforge' ),
                \esc_url( $verify_url )
            )
        );
    }

    private function send_completion_email( string $email, string $download_url ): void {
        $site_name = \get_bloginfo( 'name' );
        \wp_mail(
            $email,
            /* translators: %s: site name */
            sprintf( \__( '[%s] Your data request is ready', 'consentforge' ), $site_name ),
            sprintf(
                /* translators: %s: download URL */
                \__( "Your data export is ready. Download it here (link valid for 24 hours):\n\n%s", 'consentforge' ),
                \esc_url( $download_url )
            )
        );
    }

    private function generate_export( int $request_id, array $data ): string {
        $upload_dir = \wp_upload_dir();
        $dir        = \trailingslashit( $upload_dir['basedir'] ) . 'cf-dsar/';
        \wp_mkdir_p( $dir );

        // Protect directory
        $htaccess = $dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, 'deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        $filename = 'dsar-' . $request_id . '-' . time() . '.json';
        $path     = $dir . $filename;
        file_put_contents( $path, \wp_json_encode( $data, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

        return $path;
    }
}
