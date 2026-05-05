<?php

declare(strict_types=1);

namespace ConsentForge\Core;

class ConsentLogger {

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
		$this->table = $wpdb->prefix . 'cf_consent_logs';
	}

	public function log(
		string $consent_id,
		string $consent_type,
		array $categories_accepted,
		array $categories_rejected,
		bool $gpc = false,
		array $extra = []
	): int|false {
		global $wpdb;

		$ip           = $_SERVER['REMOTE_ADDR'] ?? '';
		$ua           = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$visitor_hash = hash( 'sha256', $ip . $ua );

		$ip_country = \sanitize_text_field(
			$_SERVER['HTTP_X_COUNTRY'] ??
			$_SERVER['HTTP_CF_IPCOUNTRY'] ??
			''
		);

		$allowed_types = [
			'banner_accept',
			'banner_reject',
			'banner_partial',
			'banner_accept_all',
			'banner_reject_all',
			'withdraw',
			'api',
		];
		$consent_type  = in_array( $consent_type, $allowed_types, true ) ? $consent_type : 'api';

		$row = [
			'consent_id'           => \sanitize_text_field( $consent_id ),
			'consent_type'         => $consent_type,
			'categories_accepted'  => \wp_json_encode( array_map( 'sanitize_text_field', $categories_accepted ) ),
			'categories_rejected'  => \wp_json_encode( array_map( 'sanitize_text_field', $categories_rejected ) ),
			'gpc'                  => (int) $gpc,
			'visitor_hash'         => $visitor_hash,
			'ip_country'           => $ip_country,
			'user_agent'           => \sanitize_text_field( substr( $ua, 0, 512 ) ),
			'wordpress_user_id'    => isset( $extra['wordpress_user_id'] ) ? \absint( $extra['wordpress_user_id'] ) : \get_current_user_id(),
			'banner_version'       => \sanitize_text_field( $extra['banner_version'] ?? '' ),
			'consent_mode_signals' => isset( $extra['consent_mode_signals'] ) ? \wp_json_encode( $extra['consent_mode_signals'] ) : null,
			'receipt_hash'         => \sanitize_text_field( $extra['receipt_hash'] ?? '' ),
			'created_at'           => \current_time( 'mysql', true ),
		];

		$formats = [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ];

		$result = $wpdb->insert( $this->table, $row, $formats );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	public function get_logs( array $args = [] ): array {
		global $wpdb;

		$per_page     = max( 1, \absint( $args['per_page'] ?? 20 ) );
		$page         = max( 1, \absint( $args['page'] ?? 1 ) );
		$offset       = ( $page - 1 ) * $per_page;
		$visitor_hash = \sanitize_text_field( $args['visitor_hash'] ?? '' );
		$consent_type = \sanitize_text_field( $args['consent_type'] ?? '' );
		$date_from    = \sanitize_text_field( $args['date_from'] ?? '' );
		$date_to      = \sanitize_text_field( $args['date_to'] ?? '' );

		$where  = [];
		$params = [];

		if ( $visitor_hash !== '' ) {
			$where[]  = 'visitor_hash = %s';
			$params[] = $visitor_hash;
		}

		if ( $consent_type !== '' ) {
			$where[]  = 'consent_type = %s';
			$params[] = $consent_type;
		}

		if ( $date_from !== '' ) {
			$where[]  = 'created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		if ( $date_to !== '' ) {
			$where[]  = 'created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$where_sql    = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$filter_count = count( $params );

		$params[] = $per_page;
		$params[] = $offset;

		$items_sql = "SELECT * FROM {$this->table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$results   = $wpdb->get_results(
			$where || $params ? $wpdb->prepare( $items_sql, $params ) : $items_sql,
			ARRAY_A
		);

		$count_sql = "SELECT COUNT(*) FROM {$this->table} {$where_sql}";
		$total     = (int) $wpdb->get_var(
			$filter_count > 0
				? $wpdb->prepare( $count_sql, array_slice( $params, 0, $filter_count ) )
				: $count_sql
		);

		return [
			'items'      => is_array( $results ) ? $results : [],
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'page_count' => (int) ceil( $total / $per_page ),
		];
	}

	public function get_log( int $id ): ?array {
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

	public function get_stats(): array {
		global $wpdb;

		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
		$accepted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE consent_type IN ('banner_accept','banner_accept_all')" );
		$rejected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE consent_type IN ('banner_reject','banner_reject_all','withdraw')" );
		$partial  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE consent_type = 'banner_partial'" );
		$gpc      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE gpc = 1" );
		$rate     = $total > 0 ? round( ( $accepted / $total ) * 100, 2 ) : 0.0;

		return compact( 'total', 'accepted', 'rejected', 'partial', 'gpc', 'rate' );
	}

	public function get_trend( int $days = 30 ): array {
		global $wpdb;

		$date_limit = \gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(created_at) AS date,
					COUNT(*) AS total,
					SUM(CASE WHEN consent_type IN ('banner_accept','banner_accept_all') THEN 1 ELSE 0 END) AS accepted,
					SUM(CASE WHEN consent_type IN ('banner_reject','banner_reject_all','withdraw') THEN 1 ELSE 0 END) AS rejected,
					SUM(CASE WHEN consent_type = 'banner_partial' THEN 1 ELSE 0 END) AS partial
				FROM {$this->table}
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$date_limit
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	public function cleanup_old( int $days = 1095 ): int {
		global $wpdb;

		$date_limit = \gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE created_at < %s",
				$date_limit
			)
		);

		return false !== $result ? (int) $result : 0;
	}
}
