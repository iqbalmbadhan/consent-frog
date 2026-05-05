<?php
namespace ConsentForge\Admin;

use ConsentForge\Core\CookieRegistry;
use ConsentForge\Core\ConsentLogger;
use ConsentForge\Core\ReceiptGenerator;
use ConsentForge\Scanner\CookieScanner;
use ConsentForge\Migration\MigrationManager;

class AdminAjax {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        $actions = [
            'cf_run_scan',
            'cf_dismiss_notice',
            'cf_update_cookie',
            'cf_delete_cookie',
            'cf_export_logs',
            'cf_process_dsar',
            'cf_verify_receipt_chain',
            'cf_run_migration',
        ];
        foreach ( $actions as $action ) {
            \add_action( 'wp_ajax_' . $action, [ $this, 'handle_' . $action ] );
        }
    }

    private function verify(): void {
        check_ajax_referer( 'cf_admin_nonce', 'nonce' );
        if ( ! \current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'consentforge' ) ], 403 );
        }
    }

    public function handle_cf_run_scan(): void {
        $this->verify();
        $scan_id = CookieScanner::instance()->run_scan();
        $results = CookieScanner::instance()->get_scan_results( $scan_id );
        wp_send_json_success( [ 'scan_id' => $scan_id, 'results' => $results ] );
    }

    public function handle_cf_dismiss_notice(): void {
        check_ajax_referer( 'cf_admin_nonce', 'nonce' );
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( -1 );
        }
        $notice_id = \sanitize_key( $_POST['notice_id'] ?? '' );
        if ( $notice_id ) {
            update_user_meta( \get_current_user_id(), 'cf_dismissed_' . $notice_id, '1' );
        }
        wp_send_json_success();
    }

    public function handle_cf_update_cookie(): void {
        $this->verify();
        $id   = \absint( $_POST['id'] ?? 0 );
        $data = [
            'category' => \sanitize_text_field( $_POST['category'] ?? '' ),
            'provider' => \sanitize_text_field( $_POST['provider'] ?? '' ),
            'purpose'  => \sanitize_textarea_field( $_POST['purpose'] ?? '' ),
        ];
        $result = CookieRegistry::instance()->update( $id, $data );
        $result ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Update failed.' ] );
    }

    public function handle_cf_delete_cookie(): void {
        $this->verify();
        $id     = \absint( $_POST['id'] ?? 0 );
        $result = CookieRegistry::instance()->delete( $id );
        $result ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Delete failed.' ] );
    }

    public function handle_cf_export_logs(): void {
        $this->verify();
        $logs = ConsentLogger::instance()->get_logs( [ 'per_page' => 10000, 'page' => 1 ] );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="consent-logs-' . \gmdate( 'Y-m-d' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        fputcsv( $out, [ 'ID', 'Consent ID', 'Type', 'Visitor Hash', 'Categories Accepted', 'GPC', 'Country', 'Date' ] );
        foreach ( $logs as $row ) {
            fputcsv( $out, [
                $row['id'],
                $row['consent_id'],
                $row['consent_type'],
                substr( $row['visitor_hash'], 0, 12 ) . '…',
                $row['categories_accepted'],
                $row['gpc_detected'] ? 'Yes' : 'No',
                $row['ip_country'],
                $row['created_at'],
            ] );
        }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    public function handle_cf_process_dsar(): void {
        $this->verify();
        $id = \absint( $_POST['request_id'] ?? 0 );
        \ConsentForge\Compliance\DsarHandler::instance()->process_request( $id );
        wp_send_json_success();
    }

    public function handle_cf_verify_receipt_chain(): void {
        $this->verify();
        $valid = ReceiptGenerator::instance()->verify_chain();
        wp_send_json_success( [ 'valid' => $valid ] );
    }

    public function handle_cf_run_migration(): void {
        $this->verify();
        $plugin = \sanitize_key( $_POST['plugin'] ?? '' );
        $result = MigrationManager::instance()->migrate_from( $plugin );
        if ( \is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        } else {
            wp_send_json_success( $result );
        }
    }
}
