<?php

declare(strict_types=1);

namespace ConsentForge\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ScriptController {

	private static ?self $instance = null;

	private const BUILTIN_PATTERNS = [
		'google-analytics.com'    => 'analytics',
		'googletagmanager.com'    => 'marketing',
		'connect.facebook.net'    => 'marketing',
		'analytics.tiktok.com'    => 'marketing',
		'js.hs-scripts.com'       => 'marketing',
		'static.hotjar.com'       => 'analytics',
		'cdn.segment.com'         => 'analytics',
	];

	public static function instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		\add_action( 'wp_enqueue_scripts', [ $this, 'register_deferred_scripts' ], 1 );
		\add_filter( 'script_loader_tag', [ $this, 'filter_script_tag' ], 10, 3 );
		\add_action( 'wp_head', [ $this, 'output_config' ] );
		\add_action( 'wp_footer', [ $this, 'enqueue_banner' ] );
	}

	public function register_deferred_scripts(): void {
		// Intentionally empty hook point at priority 1 so other plugins enqueue first.
		// ScriptController operates on already-enqueued scripts via filter_script_tag.
	}

	public function get_script_category( string $src ): string {
		$src_lower = strtolower( $src );

		foreach ( self::BUILTIN_PATTERNS as $domain => $category ) {
			if ( str_contains( $src_lower, $domain ) ) {
				return $category;
			}
		}

		$db_patterns = CookieRegistry::instance()->get_script_patterns();

		foreach ( $db_patterns as $entry ) {
			$pattern = $entry['pattern'] ?? '';
			if ( $pattern !== '' && str_contains( $src_lower, strtolower( $pattern ) ) ) {
				return $entry['category'];
			}
		}

		return '';
	}

	public function should_block_script( string $src, string $category ): bool {
		if ( $category === '' || $category === 'essential' ) {
			return false;
		}

		$engine = ConsentEngine::instance();

		return ! $engine->has_consent( $category );
	}

	public function filter_script_tag( string $tag, string $handle, string $src ): string {
		$category = $this->get_script_category( $src );

		$should_block = \apply_filters(
			'consentforge/should_block_script',
			$this->should_block_script( $src, $category ),
			$src,
			$category,
			$handle
		);

		if ( ! $should_block ) {
			return $tag;
		}

		$tag = preg_replace(
			'/\btype=["\']text\/javascript["\']/i',
			'type="text/plain"',
			$tag
		);

		if ( $tag === null ) {
			return $tag ?? '';
		}

		if ( ! str_contains( $tag, 'type=' ) ) {
			$tag = str_replace( '<script ', '<script type="text/plain" ', $tag );
		}

		$escaped_category = \esc_attr( $category );

		if ( ! str_contains( $tag, 'data-cf-category' ) ) {
			$tag = str_replace(
				'<script ',
				"<script data-cf-category=\"{$escaped_category}\" ",
				$tag
			);
		}

		return $tag;
	}

	public function output_config(): void {
		$settings       = SettingsManager::instance();
		$engine         = ConsentEngine::instance();
		$consent_state  = $engine->get_consent_state();
		$banner_config  = $settings->get_banner_config();
		$general_config = $settings->get_general_config();

		$db_patterns = CookieRegistry::instance()->get_script_patterns();

		$all_patterns = [];

		foreach ( self::BUILTIN_PATTERNS as $domain => $category ) {
			$all_patterns[] = [
				'pattern'  => $domain,
				'category' => $category,
			];
		}

		foreach ( $db_patterns as $entry ) {
			$all_patterns[] = [
				'pattern'  => $entry['pattern'],
				'category' => $entry['category'],
			];
		}

		$config = [
			'version'       => '1.0',
			'cookieName'    => 'cf_consent',
			'cookieExpiry'  => \absint( $general_config['cookie_lifetime_days'] ?? 365 ),
			'gpcBinding'    => true,
			'defaultState'  => [
				'essential'   => true,
				'analytics'   => true,
				'performance' => true,
				'marketing'   => false,
			],
			'consentState'  => [
				'essential'   => $consent_state['essential'],
				'analytics'   => $consent_state['analytics'],
				'performance' => $consent_state['performance'],
				'marketing'   => $consent_state['marketing'],
				'gpc'         => $consent_state['gpc'],
				'isSet'       => $consent_state['is_set'],
				'consentId'   => $consent_state['consent_id'],
				'timestamp'   => $consent_state['timestamp'],
			],
			'banner'        => [
				'title'          => \wp_kses_post( $banner_config['title'] ?? '' ),
				'description'    => \wp_kses_post( $banner_config['description'] ?? '' ),
				'acceptAllText'  => \sanitize_text_field( $banner_config['accept_all_text'] ?? '' ),
				'rejectAllText'  => \sanitize_text_field( $banner_config['reject_all_text'] ?? '' ),
				'customizeText'  => \sanitize_text_field( $banner_config['customize_text'] ?? '' ),
				'position'       => \sanitize_text_field( $banner_config['position'] ?? 'bottom' ),
				'layout'         => \sanitize_text_field( $banner_config['layout'] ?? 'bar' ),
				'primaryColor'   => sanitize_hex_color( $banner_config['primary_color'] ?? '#0a6e4f' ) ?? '#0a6e4f',
				'showOnRevisit'  => (bool) ( $banner_config['show_on_revisit'] ?? false ),
			],
			'scriptPatterns' => $all_patterns,
			'ajaxUrl'        => \admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'consentforge_consent' ),
		];

		$json = \wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP );

		echo "<script id=\"cf-config\">\n";
		echo "window.CFConfig = " . $json . ";\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "</script>\n";
	}

	public function enqueue_banner(): void {
		if ( ! defined( 'CF_PLUGIN_URL' ) ) {
			return;
		}

		$base    = rtrim( CF_PLUGIN_URL, '/' ) . '/frontend/banner/';
		$version = defined( 'CF_VERSION' ) ? CF_VERSION : '1.0.0';

		wp_enqueue_script(
			'consentforge-banner',
			$base . 'consent-banner.js',
			[],
			$version,
			true
		);

		wp_enqueue_style(
			'consentforge-banner',
			$base . 'consent-banner.css',
			[],
			$version
		);
	}
}
