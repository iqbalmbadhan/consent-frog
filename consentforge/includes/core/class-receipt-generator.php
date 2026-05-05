<?php

declare(strict_types=1);

namespace ConsentForge\Core;

class ReceiptGenerator {

	private static ?self $instance = null;
	private string $receipts_table;
	private string $logs_table;

	public static function instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->receipts_table = $wpdb->prefix . 'cf_receipts';
		$this->logs_table     = $wpdb->prefix . 'cf_consent_logs';
	}

	public function generate( int $consent_log_id, array $consent_state, string $source ): ?string {
		global $wpdb;

		$receipt_id     = $this->generate_uuid();
		$controller     = $this->get_controller_info();
		$previous_hash  = $this->get_last_hash();
		$chain_position = $this->get_chain_position();

		$receipt_data = [
			'receipt_id'      => $receipt_id,
			'version'         => '1.0',
			'jurisdiction'    => 'EU-GDPR-DigitalOmnibus',
			'timestamp'       => gmdate( 'c' ),
			'controller'      => $controller,
			'consent_details' => [
				'categories' => [
					'essential'   => [
						'granted' => true,
						'basis'   => 'necessary',
					],
					'analytics'   => [
						'granted' => (bool) ( $consent_state['analytics'] ?? true ),
						'basis'   => 'legitimate_interest',
						'model'   => 'opt_out',
					],
					'performance' => [
						'granted' => (bool) ( $consent_state['performance'] ?? true ),
						'basis'   => 'legitimate_interest',
						'model'   => 'opt_out',
					],
					'marketing'   => [
						'granted' => (bool) ( $consent_state['marketing'] ?? false ),
						'basis'   => 'consent',
						'model'   => 'opt_in',
					],
				],
				'source'       => sanitize_text_field( $source ),
				'gpc_detected' => (bool) ( $consent_state['gpc'] ?? false ),
			],
			'previous_hash'   => $previous_hash,
			'chain_position'  => $chain_position,
		];

		$receipt_hash                 = hash( 'sha256', wp_json_encode( $receipt_data ) . $previous_hash );
		$receipt_data['receipt_hash'] = $receipt_hash;

		$result = $wpdb->insert(
			$this->receipts_table,
			[
				'receipt_id'     => $receipt_id,
				'consent_log_id' => $consent_log_id,
				'receipt_json'   => wp_json_encode( $receipt_data ),
				'receipt_hash'   => $receipt_hash,
				'previous_hash'  => $previous_hash,
				'chain_position' => $chain_position,
				'created_at'     => current_time( 'mysql', true ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		if ( false === $result ) {
			return null;
		}

		return $receipt_id;
	}

	public function get_receipt( string $receipt_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->receipts_table} WHERE receipt_id = %s LIMIT 1",
				$receipt_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		if ( isset( $row['receipt_json'] ) ) {
			$decoded = json_decode( $row['receipt_json'], true );
			if ( is_array( $decoded ) ) {
				$row['receipt_data'] = $decoded;
			}
		}

		return $row;
	}

	public function verify_chain(): bool {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT receipt_json, receipt_hash, previous_hash, chain_position
			 FROM {$this->receipts_table}
			 ORDER BY chain_position ASC",
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return true;
		}

		$previous_hash = '';

		foreach ( $rows as $row ) {
			$data = json_decode( $row['receipt_json'], true );

			if ( ! is_array( $data ) ) {
				return false;
			}

			if ( $row['previous_hash'] !== $previous_hash ) {
				return false;
			}

			$data_without_hash = $data;
			unset( $data_without_hash['receipt_hash'] );

			$recomputed = hash( 'sha256', wp_json_encode( $data_without_hash ) . $previous_hash );

			if ( ! hash_equals( $row['receipt_hash'], $recomputed ) ) {
				return false;
			}

			$previous_hash = $row['receipt_hash'];
		}

		return true;
	}

	public function get_controller_info(): array {
		$settings = SettingsManager::instance();

		return [
			'name'    => sanitize_text_field( $settings->get( 'controller_name', '', 'general' ) ),
			'contact' => sanitize_email( $settings->get( 'controller_contact', '', 'general' ) ),
			'address' => sanitize_textarea_field( $settings->get( 'controller_address', '', 'general' ) ),
		];
	}

	private function get_last_hash(): string {
		global $wpdb;

		$hash = $wpdb->get_var(
			"SELECT receipt_hash FROM {$this->receipts_table} ORDER BY chain_position DESC LIMIT 1"
		);

		return $hash ?? '';
	}

	private function get_chain_position(): int {
		global $wpdb;

		$max = $wpdb->get_var(
			"SELECT MAX(chain_position) FROM {$this->receipts_table}"
		);

		return null === $max ? 1 : (int) $max + 1;
	}

	private function generate_uuid(): string {
		$data    = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}
}
