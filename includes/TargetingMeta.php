<?php
namespace ADCmdr;

/**
 * Allowed meta values for Targeting.
 */
class TargetingMeta {

	/**
	 * Allowed meta keys and their related details.
	 *
	 * @return array
	 */
	public static function post_meta_keys() {
		return array(
			'content_conditions' => array(
				'children' => array(
					'target'            => array(
						'type'       => 'str',
						'restricted' => array_keys( self::allowed_content_targets() ),
						'required'   => true,
					),
					'condition'         => array(
						'type'       => 'str',
						'restricted' => array_keys( self::allowed_comparisons() ),
					),
					'andor'             => array(
						'type'       => 'str',
						'restricted' => array( 'and', 'or' ),
						'default'    => 'or',
					),
					'values'            => array(
						'type'    => 'mixed',
						'default' => null,
					),
					'selected_post_ids' => array(
						'type'    => 'ints',
						'default' => null,
					),
				),
			),
			'visitor_conditions' => array(
				'children' => array(
					'target'    => array(
						'type'       => 'str',
						'restricted' => array_keys( self::allowed_visitor_targets() ),
						'required'   => true,
					),
					'condition' => array(
						'type'       => 'str',
						'restricted' => array_keys( self::allowed_comparisons() ),
					),
					'andor'     => array(
						'type'       => 'str',
						'restricted' => array( 'and', 'or' ),
						'default'    => 'or',
					),
					'values'    => array(
						'type'    => 'mixed',
						'default' => null,
					),
				),
			),
		);
	}

	/**
	 * The allowed targets for content target meta.
	 *
	 * TODO: Add additional target types that are currently disabled below.
	 *
	 * @return array
	 */
	public static function allowed_content_targets() {
		/**
		 * TODO: Add individual taxonomies; Remove some items from placements ... or at least test how they work.
		 * 404, blog_index, search_results: does not call the_content()
		 */
		$targets = apply_filters(
			'adcmdr_allowed_content_targets',
			array(
				'amp'               => __( 'AMP (Content served as AMP)', 'ad-commander' ),
				'archive_author'    => __( 'Archive: Author', 'ad-commander' ),
				'archive_category'  => __( 'Archive: Category', 'ad-commander' ),
				'archive_date'      => __( 'Archive: Date', 'ad-commander' ),
				// 'archive_format'   => __('Archive: Format, 'ad-commander')',
				'archive_post_type' => __( 'Archive: Post Type', 'ad-commander' ),
				'archive_tag'       => __( 'Archive: Tag', 'ad-commander' ),
				'archive_taxonomy'  => __( 'Archive: Taxonomy', 'ad-commander' ),
				'author'            => __( 'Author', 'ad-commander' ),
				'category'          => __( 'Category', 'ad-commander' ),
				'content_age'       => __( 'Content Age (Days)', 'ad-commander' ),
				// 'format'           => __('Format', 'ad-commander'),
				'page_template'     => __( 'Page Template', 'ad-commander' ),
				'pagination'        => __( 'Pagination', 'ad-commander' ),
				'attachment'        => __( 'Page Type: Attachments', 'ad-commander' ),
				'front_page'        => __( 'Page Type: Homepage', 'ad-commander' ),
				'blog_index'        => __( 'Page Type: Blog Index', 'ad-commander' ),
				'404'               => __( 'Page Type: 404 Page', 'ad-commander' ),
				'search_results'    => __( 'Page Type: Search Results', 'ad-commander' ),
				'parent_page'       => __( 'Parent Page', 'ad-commander' ),
				'post_content'      => __( 'Post Content', 'ad-commander' ),
				'post_type'         => __( 'Post Type', 'ad-commander' ),
				// 'post_meta'         => __('Post Meta', 'ad-commander'),
				'specific_post'     => __( 'Specific Page/Post', 'ad-commander' ),
				'post_tag'          => __( 'Tag', 'ad-commander' ),
				'url'               => __( 'URL', 'ad-commander' ),
			)
		);

		asort( $targets );

		return $targets;
	}

	/**
	 * The allowed targets for visitor target meta.
	 *
	 * @return array
	 */
	public static function allowed_visitor_targets() {
		return apply_filters(
			'adcmdr_allowed_visitor_targets',
			array(
				'browser_user_agent'   => __( 'Browser User Agent', 'ad-commander' ),
				'browser_language'     => __( 'Browser Language', 'ad-commander' ),
				'browser_width'        => __( 'Browser Width (px)', 'ad-commander' ),
				'geolocation'          => __( 'Geolocation (Specific)', 'ad-commander' ),
				'geolocation_radius'   => __( 'Geolocation (Radius)', 'ad-commander' ),
				'new_visitor'          => __( 'New Visitor', 'ad-commander' ),
				'session_referrer_url' => __( 'Referrer URL (Initial)', 'ad-commander' ),
				'site_impressions'     => __( 'Site Impressions', 'ad-commander' ),
				'max_impressions'      => __( 'Max Ad Impressions', 'ad-commander' ),
				'max_clicks'           => __( 'Max Ad Clicks', 'ad-commander' ),
				'logged_in'            => __( 'User Logged In', 'ad-commander' ),
				'user_role'            => __( 'User Role', 'ad-commander' ),
				'user_cap'             => __( 'User Capability', 'ad-commander' ),
			)
		);
	}

	/**
	 * The allowed comparisons for target meta
	 *
	 * @return array
	 */
	public static function allowed_comparisons() {
		return apply_filters(
			'adcmdr_allowed_target_comparisons',
			array(
				'is'                  => __( 'is', 'ad-commander' ),
				'is_not'              => __( 'is not', 'ad-commander' ),
				'older_than'          => __( 'older than', 'ad-commander' ),
				'newer_than'          => __( 'newer than', 'ad-commander' ),
				'equals'              => __( 'equals', 'ad-commander' ),
				'less_than'           => __( 'less than', 'ad-commander' ),
				'greater_than'        => __( 'greater than', 'ad-commander' ),
				'contains'            => __( 'contains', 'ad-commander' ),
				'does_not_contain'    => __( 'does not contain', 'ad-commander' ),
				'starts_with'         => __( 'starts with', 'ad-commander' ),
				'does_not_start_with' => __( 'does not start with', 'ad-commander' ),
				'ends_with'           => __( 'ends with', 'ad-commander' ),
				'does_not_end_with'   => __( 'does not end with', 'ad-commander' ),
			)
		);
	}

	/**
	 * The allowed and/or values for target meta.
	 *
	 * @return array
	 */
	public static function allowed_andor() {
		return array(
			'or'  => __( 'or', 'ad-commander' ),
			'and' => __( 'and', 'ad-commander' ),
		);
	}
}
