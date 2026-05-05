<?php
namespace ConsentForge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConsentForge {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_textdomain();

        new Core\SettingsManager();
        new Core\ScriptController();

        new Consent\GpcDetector();
        new Consent\ConsentApi();

        if ( is_admin() ) {
            new Admin\AdminMenu();
            new Admin\AdminNotices();
            new Admin\AdminAjax();
            new Admin\AdminRestApi();
        }

        new Compliance\DataRetention();

        $this->check_competing_plugins();
    }

    private function load_textdomain(): void {
        load_plugin_textdomain(
            'consentforge',
            false,
            dirname( CF_PLUGIN_BASENAME ) . '/languages'
        );
    }

    private function check_competing_plugins(): void {
        if ( ! is_admin() ) {
            return;
        }

        $competing = [
            'complianz-gdpr/complianz-gdpr.php'           => 'Complianz',
            'complianz-gdpr-premium/complianz-gdpr-premium.php' => 'Complianz Premium',
            'cookie-law-info/cookie-law-info.php'          => 'CookieYes',
            'cookiebot/cookiebot.php'                      => 'Cookiebot',
            'gdpr-cookie-compliance/moove-gdpr.php'        => 'GDPR Cookie Compliance',
            'uk-cookie-consent/uk-cookie-consent.php'      => 'UK Cookie Consent',
            'cookie-notice/cookie-notice.php'              => 'Cookie Notice',
            'wp-gdpr-compliance/wp-gdpr-compliance.php'    => 'WP GDPR Compliance',
        ];

        $active_conflicts = [];

        foreach ( $competing as $plugin_file => $plugin_name ) {
            if ( is_plugin_active( $plugin_file ) ) {
                $active_conflicts[] = $plugin_name;
            }
        }

        if ( ! empty( $active_conflicts ) ) {
            add_action( 'admin_notices', function () use ( $active_conflicts ): void {
                $names = implode( ', ', $active_conflicts );
                printf(
                    '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
                    esc_html__( 'ConsentForge:', 'consentforge' ),
                    sprintf(
                        /* translators: %s: comma-separated list of plugin names */
                        esc_html__( 'The following consent plugin(s) are also active and may conflict: %s. Please deactivate them to avoid duplicate banners and consent logs.', 'consentforge' ),
                        '<strong>' . esc_html( $names ) . '</strong>'
                    )
                );
            } );
        }
    }

    public static function get_version(): string {
        return CF_VERSION;
    }

    public static function get_plugin_url(): string {
        return CF_PLUGIN_URL;
    }

    public static function get_plugin_dir(): string {
        return CF_PLUGIN_DIR;
    }
}
