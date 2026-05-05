<?php
namespace ConsentForge\Admin;

use ConsentForge\Core\SettingsManager;
use ConsentForge\Licensing\FeatureGate;
use ConsentForge\Licensing\LicenseManager;

class AdminMenu {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( 'admin_menu', [ $this, 'register_menus' ] );
        \add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    public function register_menus(): void {
        add_menu_page(
            \__( 'ConsentForge', 'consentforge' ),
            \__( 'ConsentForge', 'consentforge' ),
            'manage_options',
            'consentforge',
            [ $this, 'render_page' ],
            'dashicons-shield',
            80
        );

        add_submenu_page( 'consentforge', \__( 'Dashboard', 'consentforge' ), \__( 'Dashboard', 'consentforge' ), 'manage_options', 'consentforge', [ $this, 'render_page' ] );
        add_submenu_page( 'consentforge', \__( 'Cookie Scanner', 'consentforge' ), \__( 'Cookie Scanner', 'consentforge' ), 'manage_options', 'consentforge#scanner', [ $this, 'render_page' ] );
        add_submenu_page( 'consentforge', \__( 'Banner Designer', 'consentforge' ), \__( 'Banner Designer', 'consentforge' ), 'manage_options', 'consentforge#banner', [ $this, 'render_page' ] );

        if ( FeatureGate::can( 'dsar_handling' ) ) {
            add_submenu_page( 'consentforge', \__( 'DSAR Requests', 'consentforge' ), \__( 'DSAR Requests', 'consentforge' ), 'manage_options', 'consentforge#dsar', [ $this, 'render_page' ] );
        }

        add_submenu_page( 'consentforge', \__( 'Settings', 'consentforge' ), \__( 'Settings', 'consentforge' ), 'manage_options', 'consentforge#settings', [ $this, 'render_page' ] );
    }

    public function render_page(): void {
        ?>
        <div id="cf-admin-root"></div>
        <?php
    }

    public function enqueue_admin_scripts( string $hook ): void {
        if ( ! str_contains( $hook, 'consentforge' ) && 'toplevel_page_consentforge' !== $hook ) {
            return;
        }

        $build_path = CF_PLUGIN_DIR . 'admin-ui/build/index.js';

        if ( ! file_exists( $build_path ) ) {
            // Dev mode notice
            wp_enqueue_script( 'cf-admin-dev-notice', CF_PLUGIN_URL . 'admin-ui/dev-notice.js', [], CF_VERSION, true );
        } else {
            wp_enqueue_script( 'cf-admin', CF_PLUGIN_URL . 'admin-ui/build/index.js', [ 'wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-components' ], CF_VERSION, true );
        }

        if ( file_exists( CF_PLUGIN_DIR . 'admin-ui/build/index.css' ) ) {
            wp_enqueue_style( 'cf-admin', CF_PLUGIN_URL . 'admin-ui/build/index.css', [ 'wp-components' ], CF_VERSION );
        }

        $settings_manager = SettingsManager::instance();
        $plan             = LicenseManager::instance()->get_plan();

        wp_localize_script( 'cf-admin', 'CFAdminData', [
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'apiUrl'      => \rest_url(),
            'pluginUrl'   => CF_PLUGIN_URL,
            'version'     => CF_VERSION,
            'plan'        => $plan,
            'features'    => [
                'auto_scanner'      => FeatureGate::can( 'auto_scanner' ),
                'consent_receipts'  => FeatureGate::can( 'consent_receipts' ),
                'consent_analytics' => FeatureGate::can( 'consent_analytics' ),
                'dsar_handling'     => FeatureGate::can( 'dsar_handling' ),
                'ai_act_disclosure' => FeatureGate::can( 'ai_act_disclosure' ),
                'migration_tools'   => FeatureGate::can( 'migration_tools' ),
            ],
            'settings'    => $settings_manager->get_general_config(),
            'bannerConfig'=> $settings_manager->get_banner_config(),
            'i18n'        => [
                'dashboard'     => \__( 'Dashboard', 'consentforge' ),
                'scanner'       => \__( 'Cookie Scanner', 'consentforge' ),
                'banner'        => \__( 'Banner Designer', 'consentforge' ),
                'dsar'          => \__( 'DSAR Requests', 'consentforge' ),
                'settings'      => \__( 'Settings', 'consentforge' ),
                'save'          => \__( 'Save Changes', 'consentforge' ),
                'saving'        => \__( 'Saving…', 'consentforge' ),
                'saved'         => \__( 'Saved!', 'consentforge' ),
                'error'         => \__( 'An error occurred.', 'consentforge' ),
                'confirmDelete' => \__( 'Are you sure? This cannot be undone.', 'consentforge' ),
            ],
        ] );
    }
}
