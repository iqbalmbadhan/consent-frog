<?php
namespace ConsentForge\Consent;

class GpcDetector {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( 'init', [ $this, 'detect_and_apply' ] );
    }

    public function detect(): bool {
        // Sec-GPC: 1 header (authoritative signal)
        if ( isset( $_SERVER['HTTP_SEC_GPC'] ) && '1' === $_SERVER['HTTP_SEC_GPC'] ) {
            return true;
        }

        // DNT as fallback if site opts in to respecting it
        if ( isset( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ) {
            $settings = \ConsentForge\Core\SettingsManager::instance();
            if ( '1' === $settings->get( 'respect_dnt', '0' ) ) {
                return true;
            }
        }

        return false;
    }

    public function detect_and_apply(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        if ( ! $this->detect() ) {
            return;
        }

        $visitor_hash = $this->get_visitor_hash();

        // Set transient so PHP can act on subsequent requests
        \set_transient( 'cf_gpc_' . $visitor_hash, '1', HOUR_IN_SECONDS );

        // Set JS-readable cookie
        if ( ! headers_sent() ) {
            setcookie( 'cf_gpc', '1', [
                'expires'  => time() + DAY_IN_SECONDS,
                'path'     => '/',
                'secure'   => \is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ] );
        }

        \do_action( 'consentforge/gpc_detected', $visitor_hash );
    }

    public function is_active_for_visitor(): bool {
        if ( isset( $_COOKIE['cf_gpc'] ) && '1' === $_COOKIE['cf_gpc'] ) {
            return true;
        }
        return (bool) \get_transient( 'cf_gpc_' . $this->get_visitor_hash() );
    }

    public function get_visitor_hash(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash( 'sha256', $ip . $ua );
    }
}
