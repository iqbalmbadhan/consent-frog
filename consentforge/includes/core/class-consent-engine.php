<?php

declare(strict_types=1);

namespace ConsentForge\Core;

class ConsentEngine {

	private static ?self $instance = null;
	private ?array $current_state  = null;

	public static function instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {}

	public function get_consent_state(): array {
		if ( null !== $this->current_state ) {
			return $this->current_state;
		}

		$cookie_name  = $this->get_cookie_name();
		$cookie_value = $_COOKIE[ $cookie_name ] ?? null;

		if ( ! $cookie_value ) {
			$this->current_state = array_merge( $this->get_default_state(), [ 'is_set' => false ] );
			return $this->current_state;
		}

		$decoded = $this->decode_cookie( $cookie_value );

		if ( null === $decoded ) {
			$this->current_state = array_merge( $this->get_default_state(), [ 'is_set' => false ] );
			return $this->current_state;
		}

		$c = $decoded['c'] ?? [];

		$this->current_state = [
			'essential'   => true,
			'analytics'   => (bool) ( $c['a'] ?? true ),
			'performance' => (bool) ( $c['p'] ?? true ),
			'marketing'   => (bool) ( $c['m'] ?? false ),
			'gpc'         => (bool) ( $decoded['g'] ?? false ),
			'timestamp'   => (int) ( $decoded['t'] ?? 0 ),
			'consent_id'  => sanitize_text_field( $decoded['id'] ?? '' ),
			'is_set'      => true,
		];

		return $this->current_state;
	}

	public function get_default_state(): array {
		$gpc_detected = $this->detect_gpc();

		return [
			'essential'   => true,
			'analytics'   => ! $gpc_detected,
			'performance' => ! $gpc_detected,
			'marketing'   => false,
			'gpc'         => $gpc_detected,
			'timestamp'   => 0,
			'consent_id'  => '',
			'is_set'      => false,
		];
	}

	public function set_consent_state( array $categories, string $source = 'banner_accept' ): string {
		$consent_id = $this->generate_uuid();
		$gpc        = $this->detect_gpc();

		$cookie_data = [
			'v'  => '1.0',
			's'  => $this->compute_status( $categories ),
			'c'  => [
				'e' => 1,
				'a' => isset( $categories['analytics'] ) ? (int) (bool) $categories['analytics'] : 1,
				'p' => isset( $categories['performance'] ) ? (int) (bool) $categories['performance'] : 1,
				'm' => isset( $categories['marketing'] ) ? (int) (bool) $categories['marketing'] : 0,
			],
			'g'  => (int) $gpc,
			't'  => time(),
			'id' => $consent_id,
		];

		$this->set_cookie( $this->encode_cookie( $cookie_data ) );

		$this->current_state = [
			'essential'   => true,
			'analytics'   => (bool) $cookie_data['c']['a'],
			'performance' => (bool) $cookie_data['c']['p'],
			'marketing'   => (bool) $cookie_data['c']['m'],
			'gpc'         => $gpc,
			'timestamp'   => $cookie_data['t'],
			'consent_id'  => $consent_id,
			'is_set'      => true,
		];

		do_action( 'consentforge/consent_updated', $this->current_state, $source, $consent_id );

		return $consent_id;
	}

	public function has_consent( string $category ): bool {
		$state = $this->get_consent_state();
		return (bool) ( $state[ $category ] ?? false );
	}

	public function should_show_banner(): bool {
		$state = $this->get_consent_state();
		return ! $state['is_set'];
	}

	public function get_consent_id(): ?string {
		$state = $this->get_consent_state();
		$id    = $state['consent_id'] ?? '';
		return $id !== '' ? $id : null;
	}

	public function withdraw_consent(): void {
		$consent_id  = $this->generate_uuid();
		$gpc         = $this->detect_gpc();

		$cookie_data = [
			'v'  => '1.0',
			's'  => 'denied',
			'c'  => [
				'e' => 1,
				'a' => 0,
				'p' => 0,
				'm' => 0,
			],
			'g'  => (int) $gpc,
			't'  => time(),
			'id' => $consent_id,
		];

		$this->set_cookie( $this->encode_cookie( $cookie_data ) );

		$this->current_state = [
			'essential'   => true,
			'analytics'   => false,
			'performance' => false,
			'marketing'   => false,
			'gpc'         => $gpc,
			'timestamp'   => $cookie_data['t'],
			'consent_id'  => $consent_id,
			'is_set'      => true,
		];

		do_action( 'consentforge/consent_updated', $this->current_state, 'withdraw', $consent_id );
	}

	private function compute_status( array $categories ): string {
		$optional = [ 'analytics', 'performance', 'marketing' ];
		$granted  = 0;

		foreach ( $optional as $cat ) {
			if ( ! empty( $categories[ $cat ] ) ) {
				$granted++;
			}
		}

		if ( 0 === $granted ) {
			return 'denied';
		}

		if ( count( $optional ) === $granted ) {
			return 'granted';
		}

		return 'partial';
	}

	private function detect_gpc(): bool {
		return ( $_SERVER['HTTP_SEC_GPC'] ?? '' ) === '1';
	}

	private function generate_uuid(): string {
		$data    = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	private function encode_cookie( array $state ): string {
		return base64_encode( wp_json_encode( $state ) );
	}

	private function decode_cookie( string $value ): ?array {
		$decoded = base64_decode( $value, true );

		if ( false === $decoded ) {
			return null;
		}

		$data = json_decode( $decoded, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ! isset( $data['v'], $data['c'], $data['t'], $data['id'] ) ) {
			return null;
		}

		return $data;
	}

	private function get_cookie_name(): string {
		return 'cf_consent';
	}

	private function set_cookie( string $value ): void {
		$settings = SettingsManager::instance();
		$lifetime = absint( $settings->get( 'cookie_lifetime_days', 365, 'general' ) );
		$expires  = time() + ( $lifetime * DAY_IN_SECONDS );

		setcookie(
			$this->get_cookie_name(),
			$value,
			[
				'expires'  => $expires,
				'path'     => '/',
				'domain'   => '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			]
		);

		$_COOKIE[ $this->get_cookie_name() ] = $value;
	}
}
