<?php
namespace ConsentForge\Consent;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use ConsentForge\Core\ConsentEngine;

class ConsentMode {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        // Must run at priority 1 so it fires before any GTM/GA script output
        \add_action( 'wp_head', [ $this, 'output_gtag_defaults' ], 1 );
    }

    public function output_gtag_defaults(): void {
        $state   = ConsentEngine::instance()->get_consent_state();
        $signals = $this->state_to_signals( $state );
        $type    = $state['is_set'] ? 'update' : 'default';
        ?>
<script id="cf-consent-mode-defaults">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', '<?php echo \esc_js( $type ); ?>', <?php echo \wp_json_encode( $signals ); ?>);
</script>
        <?php
    }

    public function state_to_signals( array $state ): array {
        return [
            'ad_storage'             => ! empty( $state['marketing'] )   ? 'granted' : 'denied',
            'ad_user_data'           => ! empty( $state['marketing'] )   ? 'granted' : 'denied',
            'ad_personalization'     => ! empty( $state['marketing'] )   ? 'granted' : 'denied',
            'analytics_storage'      => ! empty( $state['analytics'] )   ? 'granted' : 'denied',
            'functionality_storage'  => 'granted',
            'personalization_storage'=> ! empty( $state['performance'] ) ? 'granted' : 'denied',
            'security_storage'       => 'granted',
        ];
    }
}
