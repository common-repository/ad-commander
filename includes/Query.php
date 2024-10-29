<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Commonly used queries
 */
class Query {

	/**
	 * Query ad posts using WP_Query.
	 *
	 * @param string $orderby Order by.
	 * @param string $order Order.
	 * @param string $post_status Post status.
	 * @param array  $meta_query Optional meta query.
	 * @param array  $tax_query Optional tax query.
	 * @param array  $ad_ids Optional array of ad_ids to include in results.
	 *
	 * @return array
	 */
	public static function ads( $orderby = 'post_title', $order = 'asc', $post_status = 'publish', $meta_query = array(), $tax_query = array(), $ad_ids = array(), $limit = -1, $fields = 'all' ) {
		$ads = array();

		$args = array(
			'post_type'           => AdCommander::posttype_ad(),
			'post_status'         => $post_status,
			'posts_per_page'      => $limit,
			'orderby'             => $orderby,
			'order'               => $order,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);

		if ( $fields !== 'all' ) {
			$args['fields'] = $fields;
		}

		if ( $meta_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query needed in some cases.
			$args['meta_query'] = $meta_query;
		}

		if ( $tax_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- tax_query needed in some cases.
			$args['tax_query'] = $tax_query;
		}

		if ( ! empty( $ad_ids ) ) {
			$args['post__in'] = $ad_ids;
		}

		$ad_query = new \WP_Query(
			$args
		);

		if ( $ad_query->have_posts() ) {
			$ads = $ad_query->posts;
		}

		return $ads;
	}

	/**
	 * Query posts with post_title matching.
	 *
	 * @param string       $search_term The term to search for in the title.
	 * @param string|array $post_status The post status to query.
	 * @param string       $post_type The post type to query.
	 *
	 * @return array
	 */
	public static function by_title( $search_term, $post_status = 'publish', $post_type = null ) {

		if ( $post_type === null ) {
			$post_type = AdCommander::posttype_ad();
		}

		add_filter( 'posts_where', array( static::class, 'post_title_where' ), 10, 2 );

		$post_query = new \WP_Query(
			array(
				'search_title'        => $search_term,
				'post_type'           => $post_type,
				'post_status'         => $post_status,
				'posts_per_page'      => -1,
				'orderby'             => 'title',
				'order'               => 'asc',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		remove_filter( 'posts_where', array( static::class, 'post_title_where' ), 10, 2 );

		if ( $post_query->have_posts() ) {
			return $post_query->posts;
		}

		return array();
	}

	/**
	 * Add like matching to post_title via posts_where filter. Implemented by Query::by_title().
	 *
	 * @param string    $where The current where string.
	 * @param \WP_Query $wp_query The current WP_Query instance.
	 *
	 * @return string
	 */
	public static function post_title_where( $where, $wp_query ) {
		global $wpdb;

		$title = $wp_query->get( 'search_title' );

		$like_sql = ' AND ' . $wpdb->posts . ".post_title LIKE '%" . esc_sql( $wpdb->esc_like( $title ) ) . "%'";

		if ( $title && stripos( $where, $like_sql ) === false ) {
			$where .= $like_sql;
		}

		return $where;
	}


	/**
	 * Query an individual post by Id.
	 *
	 * @param int          $post_id The post ID to query.
	 * @param string|array $post_status The post status to query.
	 * @param string       $post_type The post type to query.
	 *
	 * @return bool|\WP_Post
	 */
	public static function by_id( $post_id, $post_status = 'publish', $post_type = null ) {

		if ( $post_type === null ) {
			$post_type = AdCommander::posttype_ad();
		}

		$result  = false;
		$post_id = absint( $post_id );

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		);

		if ( $post_type === 'page' ) {
			$args['page_id'] = $post_id;
		} else {
			$args['p'] = $post_id;
		}

		$post_query = new \WP_Query( $args );
		if ( $post_query->have_posts() ) {
			$result = $post_query->posts[0];
		}

		return $result;
	}

	/**
	 * Query an individual ad using WP_Query.
	 *
	 * @param int    $ad_id The post ID to query.
	 * @param string $post_status The post_status for the query.
	 *
	 * @return \WP_Post|bool
	 */
	public static function ad( $ad_id, $post_status = 'publish' ) {
		return self::by_id( $ad_id, $post_status );
	}

	/**
	 * Query ads that have an expire key and have expired.
	 * Interfaces with self::ads using a meta_query.
	 *
	 * @return array
	 */
	public static function expiring_ads() {
		$wo_meta    = new WOMeta( AdCommander::ns() );
		$expire_key = $wo_meta->make_key( 'expire_gmt' );

		$meta_query = array(
			'relation'         => 'AND',
			'exists_clause'    => array(
				'key'     => $expire_key,
				'compare' => 'EXISTS',
			),
			'not_empty_clause' => array(
				'key'     => $expire_key,
				'compare' => '!=',
				'value'   => '',
			),
			'value_clause'     => array(
				'key'     => $expire_key,
				'compare' => '<=',
				'value'   => time(),
				'type'    => 'NUMERIC',
			),
		);

		return self::ads( 'post_id', 'asc', 'publish', $meta_query );
	}

	/**
	 * Query ads that are in a particular group.
	 * Interfaces with self::ads using a tax_query.
	 *
	 * @param mixed  $group_id The term_id of the group to query.
	 * @param string $post_status The status of the ads to query.
	 *
	 * @return array
	 */
	public static function ads_by_group( $group_id, $post_status = 'publish' ) {
		$tax_query = array(
			array(
				'taxonomy' => AdCommander::tax_group(),
				'field'    => 'term_id',
				'terms'    => absint( $group_id ),
			),
		);

		return self::ads( 'post_title', 'asc', $post_status, array(), $tax_query );
	}

	/**
	 * Query groups using get_terms()
	 *
	 * @param bool  $hide_empty Whether to hide empty groups or not.
	 * @param array $meta_query Optional meta_query to include in get_terms().
	 *
	 * @return array
	 */
	public static function groups( $hide_empty = false, $meta_query = array() ) {
		$args = array(
			'taxonomy'   => AdCommander::tax_group(),
			'hide_empty' => $hide_empty,
		);

		if ( $meta_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query needed in some cases.
			$args['meta_query'] = $meta_query;
		}

		$groups = get_terms( $args );

		if ( ! $groups || is_wp_error( $groups ) ) {
			$groups = array();
		}

		return $groups;
	}

	/**
	 * Query specific group.
	 *
	 * @param int $term_id The term ID to query.
	 *
	 * @return WP_Term|bool
	 */
	public static function group( $term_id ) {
		$group = get_term( $term_id, AdCommander::tax_group() );

		if ( ! $group || is_wp_error( $group ) ) {
			return false;
		}

		return $group;
	}

	/**
	 * Check if any groups exist that use a specific mode.
	 *
	 * @param string $mode The name of the mode.
	 *
	 * @return bool
	 */
	public static function group_mode_exists( $mode ) {
		global $wpdb;

		$wo_meta  = new WOMeta( AdCommander::ns() );
		$mode_key = $wo_meta->make_key( 'mode' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- TODO: Test implicatoins of caching this query in future versions.
		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT meta_id FROM ' . $wpdb->prefix . 'termmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1', $mode_key, $mode )
		);

		return count( $results ) > 0;
	}

	/**
	 * Query Placements using WP_Query
	 *
	 * @param string $post_status The post_status to query.
	 * @param array  $meta_query Optional meta_query.
	 * @param array  $include_ids Post IDs to include.
	 *
	 * @return array
	 */
	public static function placements( $post_status = 'publish', $meta_query = array(), $include_ids = array(), $limit = -1, $fields = 'all' ) {
		$placements = array();

		$args = array(
			'post_type'           => AdCommander::posttype_placement(),
			'post_status'         => $post_status,
			'posts_per_page'      => $limit,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);

		if ( $fields !== 'all' ) {
			$args['fields'] = $fields;
		}

		if ( $meta_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query needed in some cases.
			$args['meta_query'] = $meta_query;
		}

		if ( ! empty( $include_ids ) ) {
			$args['post__in'] = array_map( 'absint', $include_ids );
		}

		$placement_query = new \WP_Query( $args );

		if ( $placement_query->have_posts() ) {
			$placements = $placement_query->posts;
		}

		return $placements;
	}


	/**
	 * Query an individual placement using WP_Query.
	 *
	 * @param int    $placement_id The post ID to query.
	 * @param string $post_status The post_status for the query.
	 *
	 * @return \WP_Post|bool
	 */
	public static function placement( $placement_id, $post_status = 'publish' ) {
		return self::by_id( $placement_id, $post_status, AdCommander::posttype_placement() );
	}

	/**
	 * Determine if the site has ads of any post status.
	 *
	 * @return bool
	 */
	public static function has_ads() {
		$ads = self::ads( 'ID', 'asc', Util::any_post_status(), array(), array(), array(), 1, 'ids' );
		return count( $ads ) > 0;
	}

	/**
	 * Determine if the site has placements of any post status.
	 *
	 * @return bool
	 */
	public static function has_placements() {
		$placements = self::placements( Util::any_post_status(), array(), array(), 1, 'ids' );
		return count( $placements ) > 0;
	}
}
