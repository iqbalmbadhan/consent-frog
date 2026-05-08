<?php
namespace ConsentForge\Licensing;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LicenseManager {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function get_plan(): string {
        // If Freemius is loaded, defer to it
        if ( function_exists( 'cf_fs' ) ) {
            $plan = cf_fs()->get_plan_name();
            if ( $plan ) {
                return strtolower( $plan );
            }
        }
        return strtolower( \get_option( 'cf_license_plan', 'free' ) );
    }

    public function is_pro(): bool {
        return in_array( $this->get_plan(), [ 'pro', 'business', 'agency' ], true );
    }

    public function is_business(): bool {
        return in_array( $this->get_plan(), [ 'business', 'agency' ], true );
    }

    public function is_agency(): bool {
        return 'agency' === $this->get_plan();
    }

    public function validate_license( string $key ): bool|\WP_Error {
        $key = \sanitize_text_field( $key );
        if ( empty( $key ) ) {
            return new \WP_Error( 'empty_key', \__( 'License key cannot be empty.', 'consentforge' ) );
        }
        // Stub: real validation would call the licensing API
        \update_option( 'cf_license_key', $key );
        return true;
    }

    public function get_license_info(): array {
        return [
            'plan'       => $this->get_plan(),
            'is_pro'     => $this->is_pro(),
            'is_business'=> $this->is_business(),
            'is_agency'  => $this->is_agency(),
            'key'        => \get_option( 'cf_license_key', '' ) ? '***-' . substr( \get_option( 'cf_license_key', '' ), -4 ) : '',
        ];
    }
}
