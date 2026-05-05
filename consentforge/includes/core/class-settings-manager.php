<?php

declare(strict_types=1);

namespace ConsentForge\Core;

class SettingsManager {

	private static ?self $instance = null;
	private array $cache = [];
	private string $table;

	public static function instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'cf_settings';
		$this->load_autoload_settings();
	}

	private function load_autoload_settings(): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT setting_key, setting_value, setting_group FROM {$this->table} WHERE autoload = 1",
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$group = $row['setting_group'];
			$key   = $row['setting_key'];
			$value = \maybe_unserialize( $row['setting_value'] );

			$this->cache[ $group ][ $key ] = $value;
		}
	}

	public function get( string $key, mixed $default = null, string $group = 'general' ): mixed {
		if ( isset( $this->cache[ $group ][ $key ] ) ) {
			return $this->cache[ $group ][ $key ];
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT setting_value FROM {$this->table} WHERE setting_key = %s AND setting_group = %s LIMIT 1",
				$key,
				$group
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return $default;
		}

		$value                         = \maybe_unserialize( $row['setting_value'] );
		$this->cache[ $group ][ $key ] = $value;

		return $value;
	}

	public function set( string $key, mixed $value, string $group = 'general', bool $autoload = true ): bool {
		global $wpdb;

		$serialized   = \maybe_serialize( $value );
		$autoload_int = $autoload ? 1 : 0;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE setting_key = %s AND setting_group = %s LIMIT 1",
				$key,
				$group
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$this->table,
				[
					'setting_value' => $serialized,
					'autoload'      => $autoload_int,
					'updated_at'    => \current_time( 'mysql', true ),
				],
				[
					'setting_key'   => $key,
					'setting_group' => $group,
				],
				[ '%s', '%d', '%s' ],
				[ '%s', '%s' ]
			);
		} else {
			$result = $wpdb->insert(
				$this->table,
				[
					'setting_key'   => $key,
					'setting_group' => $group,
					'setting_value' => $serialized,
					'autoload'      => $autoload_int,
					'created_at'    => \current_time( 'mysql', true ),
					'updated_at'    => \current_time( 'mysql', true ),
				],
				[ '%s', '%s', '%s', '%d', '%s', '%s' ]
			);
		}

		if ( false !== $result ) {
			$this->cache[ $group ][ $key ] = $value;
			return true;
		}

		return false;
	}

	public function get_group( string $group ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value FROM {$this->table} WHERE setting_group = %s",
				$group
			),
			ARRAY_A
		);

		$settings = [];

		if ( ! is_array( $rows ) ) {
			return $settings;
		}

		foreach ( $rows as $row ) {
			$key   = $row['setting_key'];
			$value = \maybe_unserialize( $row['setting_value'] );

			$this->cache[ $group ][ $key ] = $value;
			$settings[ $key ]              = $value;
		}

		// Merge any in-memory cache values (e.g. set() calls within the same request)
		if ( isset( $this->cache[ $group ] ) ) {
			$settings = array_merge( $this->cache[ $group ], $settings );
		}

		return $settings;
	}

	public function set_group( string $group, array $settings ): void {
		foreach ( $settings as $key => $value ) {
			$this->set( (string) $key, $value, $group );
		}
	}

	public function delete( string $key, string $group = 'general' ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table,
			[
				'setting_key'   => $key,
				'setting_group' => $group,
			],
			[ '%s', '%s' ]
		);

		if ( false !== $result ) {
			unset( $this->cache[ $group ][ $key ] );
			return true;
		}

		return false;
	}

	public function get_banner_config(): array {
		return $this->get_group( 'banner' );
	}

	public function get_general_config(): array {
		return $this->get_group( 'general' );
	}
}
