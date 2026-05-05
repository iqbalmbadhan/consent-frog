<?php
namespace ConsentForge\Migration;

use WP_Error;

class MigrationManager {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function detect_plugins(): array {
        $detected = [];

        if ( ComplianzImport::instance()->can_import() ) {
            $detected[] = 'complianz';
        }
        if ( CookieYesImport::instance()->can_import() ) {
            $detected[] = 'cookieyes';
        }
        if ( WpConsentImport::instance()->can_import() ) {
            $detected[] = 'wpconsent';
        }

        return $detected;
    }

    public function can_migrate( string $plugin ): bool {
        return match ( $plugin ) {
            'complianz' => ComplianzImport::instance()->can_import(),
            'cookieyes' => CookieYesImport::instance()->can_import(),
            'wpconsent' => WpConsentImport::instance()->can_import(),
            default     => false,
        };
    }

    public function migrate_from( string $plugin ): array|WP_Error {
        if ( ! $this->can_migrate( $plugin ) ) {
            return new WP_Error( 'not_found', sprintf( __( 'Plugin %s not detected.', 'consentforge' ), $plugin ) );
        }

        return match ( $plugin ) {
            'complianz' => ComplianzImport::instance()->import(),
            'cookieyes' => CookieYesImport::instance()->import(),
            'wpconsent' => WpConsentImport::instance()->import(),
            default     => new WP_Error( 'unsupported', __( 'Unsupported plugin.', 'consentforge' ) ),
        };
    }

    public function get_migration_status(): array {
        $plugins = $this->detect_plugins();
        return [
            'detected'   => $plugins,
            'can_import' => ! empty( $plugins ),
        ];
    }
}
