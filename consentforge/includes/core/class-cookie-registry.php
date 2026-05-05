<?php

declare(strict_types=1);

namespace ConsentForge\Core;

class CookieRegistry {

	private static ?self $instance = null;
	private string $table;

	public static function instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'cf_cookies';
	}

	public function get_all( bool $active_only = true ): array {
		global $wpdb;

		$sql = "SELECT * FROM {$this->table}";

		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}

		$sql .= ' ORDER BY category ASC, name ASC';

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : [];
	}

	public function get_by_category( string $category ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE category = %s AND active = 1 ORDER BY name ASC",
				$category
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	public function get_by_id( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	public function add( array $cookie_data ): int|false {
		global $wpdb;

		$insert               = $this->prepare_row( $cookie_data );
		$insert['created_at'] = \current_time( 'mysql', true );
		$insert['updated_at'] = \current_time( 'mysql', true );

		$result = $wpdb->insert( $this->table, $insert, $this->get_formats( $insert ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;

		$update               = $this->prepare_row( $data );
		$update['updated_at'] = \current_time( 'mysql', true );

		$result = $wpdb->update(
			$this->table,
			$update,
			[ 'id' => $id ],
			$this->get_formats( $update ),
			[ '%d' ]
		);

		return false !== $result;
	}

	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}

	public function get_script_patterns(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT category, script_pattern FROM {$this->table}
			 WHERE active = 1 AND script_pattern IS NOT NULL AND script_pattern != ''
			 ORDER BY id ASC",
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			static fn( array $row ): array => [
				'category' => $row['category'],
				'pattern'  => $row['script_pattern'],
			],
			$results
		);
	}

	public function categorize_cookie( string $name, string $domain = '' ): string {
		$known      = KnownCookiesDb::get_map();
		$name_lower = strtolower( $name );

		if ( isset( $known[ $name_lower ] ) ) {
			return $known[ $name_lower ];
		}

		foreach ( $known as $pattern => $category ) {
			if ( str_contains( $name_lower, $pattern ) ) {
				return $category;
			}
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT category FROM {$this->table}
				 WHERE active = 1 AND (name = %s OR name LIKE %s)
				 ORDER BY id ASC LIMIT 1",
				$name,
				$wpdb->esc_like( $name ) . '%'
			),
			ARRAY_A
		);

		return $row['category'] ?? 'analytics';
	}

	public function bulk_upsert( array $cookies ): int {
		global $wpdb;

		$new_count = 0;

		foreach ( $cookies as $cookie_data ) {
			if ( empty( $cookie_data['name'] ) ) {
				continue;
			}

			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->table} WHERE name = %s LIMIT 1",
					\sanitize_text_field( $cookie_data['name'] )
				)
			);

			if ( $existing_id ) {
				$this->update( (int) $existing_id, $cookie_data );
			} else {
				$result = $this->add( $cookie_data );
				if ( false !== $result ) {
					$new_count++;
				}
			}
		}

		return $new_count;
	}

	private function prepare_row( array $data ): array {
		$row                = [];
		$allowed_categories = [ 'essential', 'analytics', 'performance', 'marketing' ];

		if ( isset( $data['name'] ) ) {
			$row['name'] = \sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['category'] ) ) {
			$row['category'] = in_array( $data['category'], $allowed_categories, true )
				? $data['category']
				: 'analytics';
		}

		if ( isset( $data['provider'] ) ) {
			$row['provider'] = \sanitize_text_field( $data['provider'] );
		}

		if ( isset( $data['purpose'] ) ) {
			$row['purpose'] = \sanitize_textarea_field( $data['purpose'] );
		}

		if ( isset( $data['domain'] ) ) {
			$row['domain'] = \sanitize_text_field( $data['domain'] );
		}

		if ( isset( $data['duration'] ) ) {
			$row['duration'] = \sanitize_text_field( $data['duration'] );
		}

		if ( isset( $data['script_pattern'] ) ) {
			$row['script_pattern'] = \sanitize_text_field( $data['script_pattern'] );
		}

		if ( isset( $data['active'] ) ) {
			$row['active'] = (int) (bool) $data['active'];
		}

		return $row;
	}

	private function get_formats( array $row ): array {
		$integer_fields = [ 'active', 'id' ];
		$formats        = [];

		foreach ( array_keys( $row ) as $key ) {
			$formats[] = in_array( $key, $integer_fields, true ) ? '%d' : '%s';
		}

		return $formats;
	}
}
