<?php
namespace ConsentForge\Compliance;

class AiActDisclosure {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        \add_action( 'init', [ $this, 'register_block' ] );
        \add_action( 'wp_footer', [ $this, 'detect_chatbots' ], 99 );
        \add_filter( 'the_content', [ $this, 'auto_tag_content' ] );
    }

    public function register_block(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }
        register_block_type( 'consentforge/ai-disclosure', [
            'render_callback' => [ $this, 'render_block' ],
            'attributes'      => [
                'aiType'   => [ 'type' => 'string', 'default' => 'generated' ],
                'provider' => [ 'type' => 'string', 'default' => '' ],
            ],
        ] );
    }

    public function render_block( array $attributes, string $content ): string {
        $type     = \sanitize_text_field( $attributes['aiType'] ?? 'generated' );
        $provider = \sanitize_text_field( $attributes['provider'] ?? '' );
        return '<div class="cf-ai-disclosure-wrap">' . $content . $this->get_disclosure_html( $type, $provider ) . '</div>';
    }

    public function detect_chatbots(): void {
        // Check for common chatbot widget scripts in page HTML (heuristic via known handles)
        global $wp_scripts;
        if ( ! $wp_scripts ) {
            return;
        }
        $chatbot_hosts = [
            'tidio'     => 'code.tidio.co',
            'intercom'  => 'widget.intercom.io',
            'drift'     => 'js.driftt.com',
            'zendesk'   => 'static.zdassets.com',
            'freshdesk' => 'wchat.freshchat.com',
        ];
        $registered_src = array_filter(
            array_map( fn( $h ) => $h->src ?? '', (array) $wp_scripts->registered )
        );
        foreach ( $chatbot_hosts as $provider => $host ) {
            foreach ( $registered_src as $src ) {
                if ( str_contains( (string) $src, $host ) ) {
                    $this->output_chatbot_disclosure( $provider );
                    break;
                }
            }
        }
    }

    public function output_chatbot_disclosure( string $provider ): void {
        echo \wp_kses_post( $this->get_disclosure_html( 'chatbot', $provider ) );
    }

    public function auto_tag_content( string $content ): string {
        $post = get_post();
        if ( ! $post || ! \get_post_meta( $post->ID, '_cf_ai_generated', true ) ) {
            return $content;
        }
        $type     = \sanitize_text_field( \get_post_meta( $post->ID, '_cf_ai_type', true ) ?: 'generated' );
        $provider = \sanitize_text_field( \get_post_meta( $post->ID, '_cf_ai_provider', true ) ?: '' );
        return $this->get_disclosure_html( $type, $provider ) . $content;
    }

    public function get_disclosure_html( string $type, string $provider = '' ): string {
        $labels = [
            'generated'      => \__( 'AI-generated content', 'consentforge' ),
            'assisted'       => \__( 'AI-assisted content', 'consentforge' ),
            'chatbot'        => \__( 'AI-powered chat assistant', 'consentforge' ),
            'synthetic_media'=> \__( 'Synthetic media', 'consentforge' ),
        ];
        $label    = \esc_html( $labels[ $type ] ?? $labels['generated'] );
        $provider = $provider ? ' (' . \esc_html( $provider ) . ')' : '';

        return sprintf(
            '<div class="cf-ai-disclosure" role="note" aria-label="%1$s"><span class="cf-ai-disclosure-icon">🤖</span> <strong>%2$s</strong>: %3$s%4$s</div>',
            \esc_attr__( 'EU AI Act Disclosure', 'consentforge' ),
            \esc_html__( 'EU AI Act Disclosure', 'consentforge' ),
            $label,
            $provider
        );
    }
}
