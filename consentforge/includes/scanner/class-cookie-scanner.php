<?php
namespace ConsentForge\Scanner;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use ConsentForge\Core\CookieRegistry;

class CookieScanner {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function run_scan( string $url = '', string $scan_type = 'manual' ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'cf_scan_results';

        $urls = $url ? [ $url ] : $this->get_urls_to_scan();

        $scan_id = (int) $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            [
                'scan_type'   => $scan_type,
                'scanned_url' => implode( ', ', array_slice( $urls, 0, 3 ) ),
                'scan_status' => 'running',
                'created_at'  => \current_time( 'mysql' ),
            ]
        );
        $scan_id = (int) $wpdb->insert_id;

        $start         = microtime( true );
        $all_cookies   = [];
        $all_scripts   = [];
        $all_storage   = [];
        $all_pixels    = [];
        $error_message = '';

        foreach ( $urls as $scan_url ) {
            $html = $this->fetch_page( $scan_url );
            if ( false === $html ) {
                continue;
            }
            $all_scripts  = array_merge( $all_scripts, $this->detect_scripts( $html ) );
            $all_cookies  = array_merge( $all_cookies, $this->detect_cookies_from_html( $html ) );
            $all_storage  = array_merge( $all_storage, $this->detect_local_storage( $html ) );
            $all_pixels   = array_merge( $all_pixels, $this->detect_pixels( $html ) );
        }

        // Deduplicate by name
        $all_cookies = array_values( array_column( array_reverse( $all_cookies ), null, 'name' ) );
        $all_scripts = array_values( array_column( array_reverse( $all_scripts ), null, 'src' ) );

        // Bulk-upsert into cookie registry
        $new_count = 0;
        if ( ! empty( $all_cookies ) || ! empty( $all_scripts ) ) {
            $to_upsert = array_merge( $all_cookies, $all_scripts );
            $new_count = CookieRegistry::instance()->bulk_upsert( $to_upsert );
        }

        $uncategorized = count( array_filter( $all_cookies, fn( $c ) => 'unclassified' === ( $c['category'] ?? 'unclassified' ) ) );
        $duration_ms   = (int) ( ( microtime( true ) - $start ) * 1000 );

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            [
                'cookies_found'       => \wp_json_encode( $all_cookies ),
                'scripts_found'       => \wp_json_encode( $all_scripts ),
                'local_storage_found' => \wp_json_encode( $all_storage ),
                'pixels_found'        => \wp_json_encode( $all_pixels ),
                'new_cookies_count'   => $new_count,
                'categorized_count'   => count( $all_cookies ) - $uncategorized,
                'uncategorized_count' => $uncategorized,
                'scan_duration_ms'    => $duration_ms,
                'scan_status'         => 'completed',
                'error_message'       => $error_message,
            ],
            [ 'id' => $scan_id ]
        );

        \do_action( 'consentforge/scan_completed', $scan_id, [] );

        return $scan_id;
    }

    public function fetch_page( string $url ): string|false {
        $response = \wp_remote_get( $url, [
            'timeout'    => 30,
            'user-agent' => 'ConsentForge-Scanner/1.0 (WordPress Cookie Scanner)',
            'sslverify'  => false,
        ] );

        if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        return \wp_remote_retrieve_body( $response );
    }

    public function detect_scripts( string $html ): array {
        $scripts    = [];
        $categorizer = Categorizer::instance();

        // Match <script src="..."> tags
        preg_match_all( '/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches );
        foreach ( $matches[1] as $src ) {
            if ( str_starts_with( $src, 'data:' ) ) {
                continue;
            }
            $src    = \esc_url_raw( $src );
            $result = $categorizer->categorize_script( $src );
            if ( 'unclassified' !== $result['category'] || $this->is_third_party_src( $src ) ) {
                $scripts[] = [
                    'src'      => $src,
                    'category' => $result['category'],
                    'provider' => '',
                ];
            }
        }

        return $scripts;
    }

    public function detect_cookies_from_html( string $html ): array {
        $cookies     = [];
        $categorizer = Categorizer::instance();

        // document.cookie = "name=..."
        preg_match_all( '/document\.cookie\s*=\s*["\']([^"\'=;]+)/', $html, $m1 );
        // Cookies.set("name", ...)
        preg_match_all( '/Cookies\.set\(["\']([^"\']+)["\']/', $html, $m2 );

        $names = array_merge( $m1[1] ?? [], $m2[1] ?? [] );
        foreach ( $names as $name ) {
            $name   = trim( \sanitize_text_field( $name ) );
            $result = $categorizer->categorize( $name );
            $cookies[] = [
                'name'     => $name,
                'category' => $result['category'],
                'provider' => '',
                'duration' => '',
            ];
        }

        return $cookies;
    }

    public function detect_local_storage( string $html ): array {
        $items = [];
        preg_match_all( '/localStorage\.setItem\(["\']([^"\']+)["\']/', $html, $matches );
        foreach ( $matches[1] as $key ) {
            $items[] = [ 'key' => \sanitize_text_field( $key ), 'type' => 'localStorage' ];
        }
        preg_match_all( '/sessionStorage\.setItem\(["\']([^"\']+)["\']/', $html, $matches );
        foreach ( $matches[1] as $key ) {
            $items[] = [ 'key' => \sanitize_text_field( $key ), 'type' => 'sessionStorage' ];
        }
        return $items;
    }

    public function detect_pixels( string $html ): array {
        $pixels = [];
        preg_match_all( '/<img[^>]+(?:width=["\']0["\']|height=["\']0["\']|style=["\'][^"\']*display:\s*none)[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches );
        foreach ( $matches[1] as $src ) {
            $pixels[] = [ 'src' => \esc_url_raw( $src ) ];
        }
        return $pixels;
    }

    public function get_scan_results( int $scan_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'cf_scan_results'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $scan_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    public function get_recent_scans( int $limit = 10 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'cf_scan_results';
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d", $limit ),
            ARRAY_A
        ) ?: [];
    }

    public function get_scan_status( int $scan_id ): string {
        $result = $this->get_scan_results( $scan_id );
        return $result['scan_status'] ?? 'unknown';
    }

    private function get_urls_to_scan(): array {
        $urls   = [ \home_url( '/' ) ];
        $policy = \get_privacy_policy_url();
        if ( $policy ) {
            $urls[] = $policy;
        }

        // A recent post
        $posts = \get_posts( [ 'numberposts' => 1, 'post_status' => 'publish' ] );
        if ( ! empty( $posts ) ) {
            $urls[] = \get_permalink( $posts[0]->ID );
        }

        return array_unique( $urls );
    }

    private function is_third_party_src( string $src ): bool {
        $site_host = \wp_parse_url( \home_url(), PHP_URL_HOST );
        $src_host  = \wp_parse_url( $src, PHP_URL_HOST );
        return $src_host && $src_host !== $site_host;
    }
}
