<?php
namespace ConsentForge\Scanner;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KnownCookiesDb {

    private static ?self $instance = null;
    private array $db             = [];
    private array $patterns       = [];

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct() {
        $this->db       = $this->load();
        $this->patterns = $this->load_patterns();
    }

    public function get_category( string $cookie_name, string $domain = '' ): string {
        // Exact match
        if ( isset( $this->db[ $cookie_name ] ) ) {
            return $this->db[ $cookie_name ]['category'];
        }

        // Pattern match
        foreach ( $this->patterns as $p ) {
            if ( preg_match( $p['pattern'], $cookie_name ) ) {
                return $p['category'];
            }
        }

        return 'unclassified';
    }

    public function get_all(): array {
        return array_values( $this->db );
    }

    public function get_known_patterns(): array {
        return $this->patterns;
    }

    // phpcs:disable Generic.Files.LineLength
    private function load(): array {
        $cookies = [
            // Google Analytics
            '_ga'                     => [ 'category' => 'analytics',    'provider' => 'Google Analytics', 'purpose' => 'Distinguishes users', 'duration' => '2 years' ],
            '_gid'                    => [ 'category' => 'analytics',    'provider' => 'Google Analytics', 'purpose' => 'Distinguishes users', 'duration' => '24 hours' ],
            '_gat'                    => [ 'category' => 'analytics',    'provider' => 'Google Analytics', 'purpose' => 'Throttles request rate', 'duration' => '1 minute' ],
            '_gat_UA-'                => [ 'category' => 'analytics',    'provider' => 'Google Analytics', 'purpose' => 'Throttles request rate', 'duration' => '1 minute' ],
            '_gali'                   => [ 'category' => 'analytics',    'provider' => 'Google Analytics', 'purpose' => 'Link attribution', 'duration' => '30 seconds' ],
            '__utma'                  => [ 'category' => 'analytics',    'provider' => 'Google Analytics (Legacy)', 'purpose' => 'User/session tracking', 'duration' => '2 years' ],
            '__utmb'                  => [ 'category' => 'analytics',    'provider' => 'Google Analytics (Legacy)', 'purpose' => 'Session tracking', 'duration' => '30 minutes' ],
            '__utmc'                  => [ 'category' => 'analytics',    'provider' => 'Google Analytics (Legacy)', 'purpose' => 'Session tracking', 'duration' => 'Session' ],
            '__utmz'                  => [ 'category' => 'analytics',    'provider' => 'Google Analytics (Legacy)', 'purpose' => 'Traffic source', 'duration' => '6 months' ],
            '__utmt'                  => [ 'category' => 'analytics',    'provider' => 'Google Analytics (Legacy)', 'purpose' => 'Throttle request rate', 'duration' => '10 minutes' ],
            // Hotjar
            '_hjid'                   => [ 'category' => 'analytics',    'provider' => 'Hotjar', 'purpose' => 'Unique visitor ID', 'duration' => '1 year' ],
            '_hjFirstSeen'            => [ 'category' => 'analytics',    'provider' => 'Hotjar', 'purpose' => 'First visit marker', 'duration' => 'Session' ],
            '_hjAbsoluteSessionInProgress' => [ 'category' => 'analytics', 'provider' => 'Hotjar', 'purpose' => 'Session tracking', 'duration' => '30 minutes' ],
            '_hjTLDTest'              => [ 'category' => 'analytics',    'provider' => 'Hotjar', 'purpose' => 'TLD testing', 'duration' => 'Session' ],
            '_hjIncludedInPageviewSample' => [ 'category' => 'analytics', 'provider' => 'Hotjar', 'purpose' => 'Pageview sampling', 'duration' => '2 minutes' ],
            '_hjIncludedInSessionSample' => [ 'category' => 'analytics',  'provider' => 'Hotjar', 'purpose' => 'Session sampling', 'duration' => '2 minutes' ],
            // Meta / Facebook
            '_fbp'                    => [ 'category' => 'marketing',    'provider' => 'Meta (Facebook)', 'purpose' => 'Ad tracking', 'duration' => '3 months' ],
            '_fbc'                    => [ 'category' => 'marketing',    'provider' => 'Meta (Facebook)', 'purpose' => 'Ad click ID', 'duration' => '2 years' ],
            'fr'                      => [ 'category' => 'marketing',    'provider' => 'Meta (Facebook)', 'purpose' => 'Ad targeting', 'duration' => '3 months' ],
            // TikTok
            '_ttp'                    => [ 'category' => 'marketing',    'provider' => 'TikTok', 'purpose' => 'Ad tracking', 'duration' => '13 months' ],
            '_tt_enable_cookie'       => [ 'category' => 'marketing',    'provider' => 'TikTok', 'purpose' => 'Cookie consent', 'duration' => '1 year' ],
            // Google Ads
            '_gcl_au'                 => [ 'category' => 'marketing',    'provider' => 'Google Ads', 'purpose' => 'Conversion tracking', 'duration' => '3 months' ],
            '_gcl_aw'                 => [ 'category' => 'marketing',    'provider' => 'Google Ads', 'purpose' => 'Click tracking', 'duration' => '90 days' ],
            'IDE'                     => [ 'category' => 'marketing',    'provider' => 'Google DoubleClick', 'purpose' => 'Ad targeting', 'duration' => '1 year' ],
            'test_cookie'             => [ 'category' => 'marketing',    'provider' => 'Google DoubleClick', 'purpose' => 'Cookie support check', 'duration' => '15 minutes' ],
            // Microsoft / Bing
            '_uetsid'                 => [ 'category' => 'marketing',    'provider' => 'Microsoft Advertising', 'purpose' => 'Session tracking', 'duration' => '1 day' ],
            '_uetvid'                 => [ 'category' => 'marketing',    'provider' => 'Microsoft Advertising', 'purpose' => 'User tracking', 'duration' => '16 days' ],
            'MUID'                    => [ 'category' => 'marketing',    'provider' => 'Microsoft', 'purpose' => 'User ID', 'duration' => '1 year' ],
            // HubSpot
            '__hssc'                  => [ 'category' => 'marketing',    'provider' => 'HubSpot', 'purpose' => 'Session tracking', 'duration' => '30 minutes' ],
            '__hssrc'                 => [ 'category' => 'marketing',    'provider' => 'HubSpot', 'purpose' => 'Source tracking', 'duration' => 'Session' ],
            '__hstc'                  => [ 'category' => 'marketing',    'provider' => 'HubSpot', 'purpose' => 'Visitor tracking', 'duration' => '13 months' ],
            'hubspotutk'              => [ 'category' => 'marketing',    'provider' => 'HubSpot', 'purpose' => 'Visitor deduplication', 'duration' => '13 months' ],
            // YouTube
            'YSC'                     => [ 'category' => 'marketing',    'provider' => 'YouTube', 'purpose' => 'Tracks embedded video views', 'duration' => 'Session' ],
            'VISITOR_INFO1_LIVE'      => [ 'category' => 'marketing',    'provider' => 'YouTube', 'purpose' => 'Bandwidth estimation', 'duration' => '6 months' ],
            'GPS'                     => [ 'category' => 'marketing',    'provider' => 'YouTube', 'purpose' => 'Location tracking', 'duration' => '30 minutes' ],
            'PREF'                    => [ 'category' => 'marketing',    'provider' => 'YouTube/Google', 'purpose' => 'Preferences', 'duration' => '2 years' ],
            // Cloudflare
            '__cfruid'                => [ 'category' => 'essential',    'provider' => 'Cloudflare', 'purpose' => 'Rate limiting', 'duration' => 'Session' ],
            '__cf_bm'                 => [ 'category' => 'essential',    'provider' => 'Cloudflare', 'purpose' => 'Bot management', 'duration' => '30 minutes' ],
            'cf_clearance'            => [ 'category' => 'essential',    'provider' => 'Cloudflare', 'purpose' => 'CAPTCHA clearance', 'duration' => '30 minutes' ],
            // WordPress core
            'wordpress_test_cookie'   => [ 'category' => 'essential',    'provider' => 'WordPress', 'purpose' => 'Cookie support check', 'duration' => 'Session' ],
            'wp_lang'                 => [ 'category' => 'performance',  'provider' => 'WordPress', 'purpose' => 'Language preference', 'duration' => '1 year' ],
            // WooCommerce
            'woocommerce_cart_hash'   => [ 'category' => 'essential',    'provider' => 'WooCommerce', 'purpose' => 'Cart hash', 'duration' => 'Session' ],
            'woocommerce_items_in_cart' => [ 'category' => 'essential',  'provider' => 'WooCommerce', 'purpose' => 'Cart contents', 'duration' => 'Session' ],
            // Stripe
            '__stripe_mid'            => [ 'category' => 'essential',    'provider' => 'Stripe', 'purpose' => 'Fraud prevention', 'duration' => '1 year' ],
            '__stripe_sid'            => [ 'category' => 'essential',    'provider' => 'Stripe', 'purpose' => 'Session ID', 'duration' => '30 minutes' ],
            // Intercom
            'intercom-*'              => [ 'category' => 'marketing',    'provider' => 'Intercom', 'purpose' => 'Live chat', 'duration' => '9 months' ],
            // LinkedIn
            'li_sugr'                 => [ 'category' => 'marketing',    'provider' => 'LinkedIn', 'purpose' => 'Browser identification', 'duration' => '3 months' ],
            'UserMatchHistory'        => [ 'category' => 'marketing',    'provider' => 'LinkedIn', 'purpose' => 'Ad frequency', 'duration' => '30 days' ],
            'bcookie'                 => [ 'category' => 'marketing',    'provider' => 'LinkedIn', 'purpose' => 'Browser ID', 'duration' => '2 years' ],
            'lidc'                    => [ 'category' => 'marketing',    'provider' => 'LinkedIn', 'purpose' => 'Data routing', 'duration' => '1 day' ],
            // Twitter / X
            '_twitter_sess'           => [ 'category' => 'marketing',    'provider' => 'X (Twitter)', 'purpose' => 'Session', 'duration' => 'Session' ],
            'guest_id'                => [ 'category' => 'marketing',    'provider' => 'X (Twitter)', 'purpose' => 'Guest tracking', 'duration' => '2 years' ],
            'personalization_id'      => [ 'category' => 'marketing',    'provider' => 'X (Twitter)', 'purpose' => 'Ad personalisation', 'duration' => '2 years' ],
            // Mailchimp
            '_mc_vid'                 => [ 'category' => 'marketing',    'provider' => 'Mailchimp', 'purpose' => 'Visitor ID', 'duration' => '2 years' ],
            // Segment
            'ajs_anonymous_id'        => [ 'category' => 'analytics',    'provider' => 'Segment', 'purpose' => 'Anonymous user ID', 'duration' => '1 year' ],
            'ajs_user_id'             => [ 'category' => 'analytics',    'provider' => 'Segment', 'purpose' => 'User ID', 'duration' => '1 year' ],
            // Mixpanel
            'mp_*'                    => [ 'category' => 'analytics',    'provider' => 'Mixpanel', 'purpose' => 'Analytics', 'duration' => '1 year' ],
            // Cookie notice
            'cookielawinfo-*'         => [ 'category' => 'essential',    'provider' => 'Cookie Law Info', 'purpose' => 'Consent storage', 'duration' => '1 year' ],
            'viewed_cookie_policy'    => [ 'category' => 'essential',    'provider' => 'Cookie Law Info', 'purpose' => 'Consent storage', 'duration' => '1 year' ],
            // Elementor
            'elementor'               => [ 'category' => 'essential',    'provider' => 'Elementor', 'purpose' => 'Experiment tracking', 'duration' => 'Never' ],
        ];

        $out = [];
        foreach ( $cookies as $name => $data ) {
            $out[ $name ] = array_merge( [ 'name' => $name ], $data );
        }
        return $out;
    }
    // phpcs:enable Generic.Files.LineLength

    private function load_patterns(): array {
        return [
            [ 'pattern' => '/^_ga_/', 'category' => 'analytics', 'provider' => 'Google Analytics 4' ],
            [ 'pattern' => '/^_gat_/', 'category' => 'analytics', 'provider' => 'Google Analytics' ],
            [ 'pattern' => '/^_hj/', 'category' => 'analytics', 'provider' => 'Hotjar' ],
            [ 'pattern' => '/^hj/', 'category' => 'analytics', 'provider' => 'Hotjar' ],
            [ 'pattern' => '/^_hjSession/', 'category' => 'analytics', 'provider' => 'Hotjar' ],
            [ 'pattern' => '/^_fb/', 'category' => 'marketing', 'provider' => 'Meta' ],
            [ 'pattern' => '/^_tt/', 'category' => 'marketing', 'provider' => 'TikTok' ],
            [ 'pattern' => '/^_gcl/', 'category' => 'marketing', 'provider' => 'Google Ads' ],
            [ 'pattern' => '/^__hs/', 'category' => 'marketing', 'provider' => 'HubSpot' ],
            [ 'pattern' => '/^_ue/', 'category' => 'marketing', 'provider' => 'Microsoft Advertising' ],
            [ 'pattern' => '/^wordpress_/', 'category' => 'essential', 'provider' => 'WordPress' ],
            [ 'pattern' => '/^wp-settings-/', 'category' => 'essential', 'provider' => 'WordPress' ],
            [ 'pattern' => '/^woocommerce_/', 'category' => 'essential', 'provider' => 'WooCommerce' ],
            [ 'pattern' => '/^wp_woocommerce_/', 'category' => 'essential', 'provider' => 'WooCommerce' ],
            [ 'pattern' => '/^__stripe/', 'category' => 'essential', 'provider' => 'Stripe' ],
            [ 'pattern' => '/^cf_consent/', 'category' => 'essential', 'provider' => 'ConsentForge' ],
        ];
    }
}
