<?php
namespace ConsentForge\Scanner;

class Categorizer {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function categorize( string $cookie_name, string $domain = '', string $duration = '', bool $is_third_party = false ): array {
        // Try known DB first
        $known_category = KnownCookiesDb::instance()->get_category( $cookie_name, $domain );
        if ( 'unclassified' !== $known_category ) {
            return $this->build_result( $known_category, 0.95, 'Known cookie database match' );
        }

        // Name-based patterns
        $name_category = $this->check_name_patterns( $cookie_name );
        if ( $name_category ) {
            return $this->build_result( $name_category, 0.80, 'Cookie name pattern match' );
        }

        // Duration heuristic
        $dur_category = $this->check_duration( $duration );
        if ( $dur_category ) {
            return $this->build_result( $dur_category, 0.40, 'Duration-based heuristic' );
        }

        // Third-party domain with unknown name — assume marketing (conservative)
        if ( $is_third_party ) {
            return $this->build_result( 'marketing', 0.30, 'Third-party unknown cookie' );
        }

        return $this->build_result( 'unclassified', 0.0, 'No match found' );
    }

    public function categorize_script( string $script_url ): array {
        foreach ( $this->script_url_patterns() as $entry ) {
            if ( false !== strpos( $script_url, $entry['host'] ) ) {
                return $this->build_result( $entry['category'], 0.90, 'Script URL host match' );
            }
        }
        return $this->build_result( 'unclassified', 0.0, 'Unknown script URL' );
    }

    private function build_result( string $category, float $confidence, string $reason ): array {
        $models = [
            'essential'    => [ 'basis' => 'necessary',            'model' => 'exempt' ],
            'analytics'    => [ 'basis' => 'legitimate_interest',  'model' => 'opt_out' ],
            'performance'  => [ 'basis' => 'legitimate_interest',  'model' => 'opt_out' ],
            'marketing'    => [ 'basis' => 'consent',              'model' => 'opt_in' ],
            'unclassified' => [ 'basis' => 'consent',              'model' => 'opt_in' ],
        ];
        return [
            'category'   => $category,
            'confidence' => $confidence,
            'basis'      => $models[ $category ]['basis'] ?? 'consent',
            'model'      => $models[ $category ]['model'] ?? 'opt_in',
            'reason'     => $reason,
        ];
    }

    private function check_name_patterns( string $name ): ?string {
        $rules = [
            '/^_ga($|_)/'                     => 'analytics',
            '/^_gid$/'                        => 'analytics',
            '/^__utm/'                        => 'analytics',
            '/^_hj/'                          => 'analytics',
            '/^hj_/'                          => 'analytics',
            '/^ajs_/'                         => 'analytics',
            '/^mp_/'                          => 'analytics',
            '/^_fb/'                          => 'marketing',
            '/^_tt/'                          => 'marketing',
            '/^_gcl/'                         => 'marketing',
            '/^_ue/'                          => 'marketing',
            '/^IDE$|^test_cookie$/'           => 'marketing',
            '/^__hs/'                         => 'marketing',
            '/hubspot/i'                      => 'marketing',
            '/^fr$/'                          => 'marketing',
            '/^YSC$|^GPS$|^PREF$/'           => 'marketing',
            '/^VISITOR_INFO/'                 => 'marketing',
            '/^li_|^bcookie$|^lidc$/'         => 'marketing',
            '/^personalization_id$/'          => 'marketing',
            '/^__stripe/'                     => 'essential',
            '/^__cf/'                         => 'essential',
            '/^cf_clearance$/'                => 'essential',
            '/^wordpress_|^wp-settings-/'     => 'essential',
            '/^woocommerce_|^wp_woocommerce/' => 'essential',
            '/^PHPSESSID$|^JSESSIONID$/'      => 'essential',
            '/sess(ion)?/i'                   => 'performance',
            '/^lang$|^locale$|^currency$/'    => 'performance',
            '/^pref/i'                        => 'performance',
        ];
        foreach ( $rules as $pattern => $category ) {
            if ( preg_match( $pattern, $name ) ) {
                return $category;
            }
        }
        return null;
    }

    private function check_duration( string $duration ): ?string {
        if ( ! $duration ) {
            return null;
        }
        $lower = strtolower( $duration );
        if ( str_contains( $lower, 'session' ) ) {
            return 'performance';
        }
        return null;
    }

    private function script_url_patterns(): array {
        return [
            [ 'host' => 'google-analytics.com',      'category' => 'analytics' ],
            [ 'host' => 'googletagmanager.com/gtm',  'category' => 'marketing' ],
            [ 'host' => 'googletagmanager.com/gtag', 'category' => 'analytics' ],
            [ 'host' => 'connect.facebook.net',      'category' => 'marketing' ],
            [ 'host' => 'analytics.tiktok.com',      'category' => 'marketing' ],
            [ 'host' => 'snap.licdn.com',            'category' => 'marketing' ],
            [ 'host' => 'static.hotjar.com',         'category' => 'analytics' ],
            [ 'host' => 'script.hotjar.com',         'category' => 'analytics' ],
            [ 'host' => 'js.hs-scripts.com',         'category' => 'marketing' ],
            [ 'host' => 'js.hsforms.net',            'category' => 'marketing' ],
            [ 'host' => 'cdn.segment.com',           'category' => 'analytics' ],
            [ 'host' => 'cdn.mxpnl.com',             'category' => 'analytics' ],
            [ 'host' => 'bat.bing.com',              'category' => 'marketing' ],
            [ 'host' => 'uet.bing.com',              'category' => 'marketing' ],
            [ 'host' => 'sc-static.net',             'category' => 'marketing' ],
            [ 'host' => 'js.stripe.com',             'category' => 'essential' ],
            [ 'host' => 'js.braintreegateway.com',   'category' => 'essential' ],
            [ 'host' => 'cdn.jsdelivr.net',          'category' => 'performance' ],
            [ 'host' => 'cdnjs.cloudflare.com',      'category' => 'performance' ],
            [ 'host' => 'widget.intercom.io',        'category' => 'marketing' ],
            [ 'host' => 'js.intercomcdn.com',        'category' => 'marketing' ],
            [ 'host' => 'static.zdassets.com',       'category' => 'marketing' ],
        ];
    }
}
