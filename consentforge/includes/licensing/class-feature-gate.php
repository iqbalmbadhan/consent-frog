<?php
namespace ConsentForge\Licensing;

class FeatureGate {

    private static array $gates = [
        'auto_scanner'        => [ 'pro', 'business', 'agency' ],
        'ml_categorization'   => [ 'pro', 'business', 'agency' ],
        'consent_receipts'    => [ 'pro', 'business', 'agency' ],
        'consent_mode_v2'     => [ 'pro', 'business', 'agency' ],
        'consent_analytics'   => [ 'pro', 'business', 'agency' ],
        'dsar_handling'       => [ 'business', 'agency' ],
        'ai_act_disclosure'   => [ 'business', 'agency' ],
        'woo_integration'     => [ 'business', 'agency' ],
        'migration_tools'     => [ 'business', 'agency' ],
        'white_label'         => [ 'agency' ],
        'multisite'           => [ 'agency' ],
        'client_dashboard'    => [ 'agency' ],
    ];

    public static function can( string $feature ): bool {
        if ( ! isset( static::$gates[ $feature ] ) ) {
            return true; // Unknown features are open
        }
        $plan = LicenseManager::instance()->get_plan();
        return in_array( $plan, static::$gates[ $feature ], true );
    }

    public static function require_plan( string $feature ): void {
        if ( ! static::can( $feature ) ) {
            $required = static::$gates[ $feature ][0] ?? 'pro';
            wp_die( sprintf(
                /* translators: 1: feature name, 2: required plan */
                esc_html__( 'The feature "%1$s" requires the %2$s plan or higher.', 'consentforge' ),
                esc_html( $feature ),
                esc_html( ucfirst( $required ) )
            ) );
        }
    }

    public static function get_upgrade_url( string $feature ): string {
        return 'https://consentforge.com/pricing/?utm_source=plugin&utm_medium=gate&utm_campaign=' . rawurlencode( $feature );
    }
}
