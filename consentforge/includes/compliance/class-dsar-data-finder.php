<?php
namespace ConsentForge\Compliance;

class DsarDataFinder {

    private static ?self $instance = null;

    public static function instance(): static {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function find_all_data( string $email ): array {
        $email   = sanitize_email( $email );
        $sources = [
            'wordpress_user'   => $this->find_wordpress_user( $email ),
            'woocommerce'      => $this->find_woocommerce_orders( $email ),
            'comments'         => $this->find_comments( $email ),
            'consent_logs'     => $this->find_consent_logs( $email ),
            'gravity_forms'    => $this->find_gravity_forms( $email ),
            'wpforms'          => $this->find_wpforms( $email ),
            'contact_form_7'   => $this->find_contact_form_7( $email ),
        ];

        return apply_filters( 'consentforge/dsar_data_sources', $sources, $email );
    }

    public function find_wordpress_user( string $email ): ?array {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            return null;
        }
        return [
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'registered'   => $user->user_registered,
            'roles'        => $user->roles,
        ];
    }

    public function find_woocommerce_orders( string $email ): array {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return [];
        }
        $orders = wc_get_orders( [
            'billing_email' => $email,
            'limit'         => 100,
            'return'        => 'ids',
        ] );
        $result = [];
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }
            $result[] = [
                'order_id'     => $order_id,
                'date'         => $order->get_date_created()?->date( 'c' ),
                'status'       => $order->get_status(),
                'total'        => $order->get_total(),
                'items'        => count( $order->get_items() ),
                'billing_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'billing_addr' => $order->get_billing_address_1() . ', ' . $order->get_billing_city(),
            ];
        }
        return $result;
    }

    public function find_comments( string $email ): array {
        $comments = get_comments( [
            'author_email' => $email,
            'number'       => 100,
            'status'       => 'all',
        ] );
        return array_map( fn( $c ) => [
            'id'         => $c->comment_ID,
            'post_id'    => $c->comment_post_ID,
            'date'       => $c->comment_date,
            'content'    => wp_strip_all_tags( $c->comment_content ),
            'author'     => $c->comment_author,
            'ip'         => $c->comment_author_IP,
        ], $comments );
    }

    public function find_consent_logs( string $email ): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'cf_consent_logs';
        $user   = get_user_by( 'email', $email );
        $result = [];

        if ( $user ) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare( "SELECT * FROM `{$table}` WHERE wordpress_user_id = %d ORDER BY created_at DESC LIMIT 50", $user->ID ),
                ARRAY_A
            );
            $result = array_merge( $result, $rows ?: [] );
        }

        return $result;
    }

    public function find_gravity_forms( string $email ): array {
        if ( ! class_exists( 'GFAPI' ) ) {
            return [];
        }
        $search   = [ 'field_filters' => [ [ 'key' => 'email', 'value' => $email ] ] ];
        $entries  = \GFAPI::get_entries( 0, $search, null, [ 'offset' => 0, 'page_size' => 100 ] );
        if ( is_wp_error( $entries ) ) {
            return [];
        }
        return array_map( fn( $e ) => [
            'entry_id' => $e['id'],
            'form_id'  => $e['form_id'],
            'date'     => $e['date_created'],
            'status'   => $e['status'],
        ], (array) $entries );
    }

    public function find_wpforms( string $email ): array {
        if ( ! function_exists( 'wpforms' ) ) {
            return [];
        }
        try {
            $entries = wpforms()->get( 'entry' )->get_entries( [ 'fields_search' => $email, 'number' => 100 ] );
            return array_map( fn( $e ) => [ 'entry_id' => $e->entry_id, 'form_id' => $e->form_id, 'date' => $e->date ], (array) $entries );
        } catch ( \Exception ) {
            return [];
        }
    }

    public function find_contact_form_7( string $email ): array {
        if ( ! post_type_exists( 'flamingo_inbound' ) ) {
            return [];
        }
        $posts = get_posts( [
            'post_type'      => 'flamingo_inbound',
            'meta_key'       => '_from_email', // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value'     => $email,        // phpcs:ignore WordPress.DB.SlowDBQuery
            'posts_per_page' => 50,
        ] );
        return array_map( fn( $p ) => [ 'id' => $p->ID, 'date' => $p->post_date, 'subject' => $p->post_title ], $posts );
    }
}
