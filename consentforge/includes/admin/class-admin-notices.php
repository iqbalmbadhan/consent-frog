<?php
namespace ConsentForge\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use ConsentForge\Migration\MigrationManager;

class AdminNotices {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( 'admin_notices', [ $this, 'show_notices' ] );
        \add_action( 'wp_ajax_cf_dismiss_notice', [ $this, 'dismiss_notice' ] );
    }

    public function show_notices(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        $this->notice_competing_plugins();
        $this->notice_setup_incomplete();
    }

    public function notice_competing_plugins(): void {
        if ( $this->is_dismissed( 'competing_plugins' ) ) {
            return;
        }
        $detected = MigrationManager::instance()->detect_plugins();
        if ( empty( $detected ) ) {
            return;
        }
        $names = array_map( fn( $p ) => ucfirst( $p ), $detected );
        $msg   = sprintf(
            /* translators: 1: plugin names, 2: migration URL */
            \__( '<strong>ConsentForge:</strong> We detected %1$s on your site. <a href="%2$s">Migrate your settings in one click</a> to upgrade to Digital Omnibus compliance.', 'consentforge' ),
            implode( ', ', $names ),
            \esc_url( \admin_url( 'admin.php?page=consentforge#settings' ) )
        );
        $this->render_notice( $msg, 'warning', true, 'competing_plugins' );
    }

    public function notice_setup_incomplete(): void {
        if ( $this->is_dismissed( 'setup_incomplete' ) ) {
            return;
        }

        // Only show on ConsentForge pages
        $screen = get_current_screen();
        if ( ! $screen || ! str_contains( $screen->id, 'consentforge' ) ) {
            return;
        }

        global $wpdb;
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}cf_cookies`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        if ( $count > 0 ) {
            return;
        }

        $msg = sprintf(
            /* translators: %s: scanner URL */
            \__( '<strong>ConsentForge:</strong> No cookies have been scanned yet. <a href="%s">Run your first scan</a> to discover and categorize cookies on your site.', 'consentforge' ),
            \esc_url( \admin_url( 'admin.php?page=consentforge#scanner' ) )
        );
        $this->render_notice( $msg, 'info', true, 'setup_incomplete' );
    }

    public function dismiss_notice(): void {
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

    public function is_dismissed( string $notice_id ): bool {
        return '1' === get_user_meta( \get_current_user_id(), 'cf_dismissed_' . $notice_id, true );
    }

    public function render_notice( string $message, string $type = 'info', bool $dismissible = true, string $notice_id = '' ): void {
        $classes  = 'notice notice-' . \esc_attr( $type );
        $classes .= $dismissible ? ' is-dismissible' : '';
        $dismiss  = '';
        if ( $notice_id && $dismissible ) {
            $dismiss = sprintf(
                '<button type="button" class="notice-dismiss" onclick="cfDismissNotice(\'%s\')"><span class="screen-reader-text">%s</span></button>',
                \esc_attr( $notice_id ),
                \esc_html__( 'Dismiss this notice.', 'consentforge' )
            );
        }
        printf( '<div class="%s"><p>%s</p>%s</div>', \esc_attr( $classes ), \wp_kses_post( $message ), \wp_kses_post( $dismiss ) );
    }
}
