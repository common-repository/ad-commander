<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Converts a taxonomy term into a Group
 */
class Group {
	/**
	 * The current group ID (if one exists).
	 *
	 * @var int
	 */
	private $group_id;

	/**
	 * An array of posts in this group.
	 *
	 * @var array
	 */
	private $ad_posts;

	/**
	 * The display mode for this group.
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Whether this group is floated or not.
	 *
	 * @var bool
	 */
	private $has_float;

	/**
	 * The number of columns in the grid.
	 *
	 * @var int
	 */
	private $grid_cols;


	/**
	 * The current term's meta.
	 *
	 * @var mixed
	 */
	private $term_meta;

	/**
	 * The current instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * Disable all wrappers
	 *
	 * @var bool
	 */
	private $disable_wrappers;

	/**
	 * Does this group need consent?
	 *
	 * @var bool
	 */
	private $needs_consent = null;

	/**
	 * Skip the consent check?
	 *
	 * @var bool
	 */
	private $force_no_consent_check = false;

	/**
	 * __construct
	 *
	 * @param int $group_id The term ID for this group.
	 */
	public function __construct( int $group_id ) {
		$this->group_id = $group_id;
		$this->wo_meta  = new WOMeta( AdCommander::ns() );
	}

	/**
	 * Get and return the term meta.
	 *
	 * @return array
	 */
	private function term_meta() {
		if ( ! $this->term_meta ) {
			$this->term_meta = $this->wo_meta->get_term_meta( $this->group_id, GroupTermMeta::tax_group_meta_keys() );
		}

		return $this->term_meta;
	}

	/**
	 * The display modes available for groups.
	 *
	 * @return array
	 */
	public static function modes() {
		return array(
			'single' => __( 'Single', 'ad-commander' ),
			'rotate' => __( 'Rotate', 'ad-commander' ),
			'grid'   => __( 'Grid', 'ad-commander' ),
		);
	}

	/**
	 * The mode of this current group. Load meta if it hasn't been loaded.
	 *
	 * @return string
	 */
	private function mode() {
		if ( $this->mode ) {
			return $this->mode;
		}

		$mode = $this->wo_meta->get_value( $this->term_meta(), 'mode', 'single' );

		if ( in_array( $mode, ProBridge::instance()->pro_group_modes() ) && ! ProBridge::instance()->is_pro_loaded() ) {
			$mode = 'single';
		}

		$this->mode = $mode;

		return $this->mode;
	}

	/**
	 * Potentially block a bot from seeing the group.
	 *
	 * @return bool
	 */
	private function should_block_bot() {
		if ( ! Options::instance()->get( 'bots_disable_ads', 'general', true ) ) {
			return false;
		}

		return Bots::is_bot();
	}

	/**
	 * Check if ads are disabled in global settings.
	 *
	 * @return bool
	 */
	private function ads_disabled() {
		if ( Targeting::ads_disabled_all() ) {
			return true;
		}

		if ( TargetingVisitor::ads_disabled_user_role() ) {
			return true;
		}

		return false;
	}

	/**
	 * Load the ads in this group into an array.
	 * Optionally process that array into another format.
	 *
	 * @param bool $process Whether to process the array of posts or not.
	 *
	 * @return void
	 */
	public function load_ads( $process = true ) {
		$this->ad_posts = Query::ads_by_group( $this->group_id );

		if ( ! empty( $this->ad_posts ) && $process ) {
			$this->process_ad_posts();
		}
	}

	/**
	 * Count the number of ads in this group.
	 *
	 * @return int
	 */
	private function group_count() {
		$count = get_transient( GroupTermMeta::group_count_transient( $this->group_id ) );

		if ( $count === false ) {
			if ( ! $this->ad_posts ) {
				$this->load_ads( false );
			}

			$count = count( $this->ad_posts );
			set_transient( GroupTermMeta::group_count_transient( $this->group_id ), $count, HOUR_IN_SECONDS );
		}

		return $count;
	}

	/**
	 * Get the group order method.
	 *
	 * @return string
	 */
	private function get_order_method() {
		return $this->wo_meta->get_value( $this->term_meta(), 'order_method', 'random' );
	}

	/**
	 * Do we need consent to display?
	 *
	 * @return bool
	 */
	private function needs_consent() {

		if ( $this->needs_consent === null ) {
			if ( $this->wo_meta->get_value( $this->term_meta(), 'disable_consent', false ) ) {
				$this->needs_consent = false;
				return $this->needs_consent;
			}

			$global_needs_consent = Consent::instance()->global_needs_consent( $this->force_no_consent_check );
			$this->needs_consent  = $global_needs_consent;
		}

		return $this->needs_consent;
	}

	/**
	 * Determine if ads in this group should be randomized or not.
	 *
	 * @return bool
	 */
	private function should_auto_sort() {
		if ( $this->group_count() < 2 ) {
			return false;
		}

		$order_method = $this->get_order_method();

		return $order_method !== 'manual';
	}

	/**
	 * Determine if we should use ajax loading for this group.
	 *
	 * @return bool
	 */
	public function should_ajax() {

		if ( ! ProBridge::instance()->is_pro_loaded() || Amp::instance()->is_amp() ) {
			return false;
		}

		$render = Util::render_method();

		if ( $render === 'serverside' ) {
			return false;
		}

		if ( $render === 'clientside' ) {
			return true;
		}

		/**
		 * 'Smart' render method.
		 */

		/**
		 * Use consent
		 * Always use client-side if there's a consent cookie, because page caching would cause false positives.
		 */
		if ( Consent::instance()->requires_consent() ) {
			return true;
		}

		/**
		 * TODO: Do we need to check visitor conditions here?
		 * This is how it was done in placements. Not sure why it wasn't originally checked in groups.
		 *
		 * In the future, could check only certain visitor conditions.
		 */
		$visitor_conditions = $this->wo_meta->get_value( $this->term_meta(), 'visitor_conditions', false );
		return ( ( $visitor_conditions && ! empty( $visitor_conditions ) ) || $this->should_auto_sort() );
	}

	/**
	 * Sort ads in this group by their manually selected order.
	 *
	 * @param mixed $ad_posts An array of ads.
	 * @param mixed $sorted_ids An array of ad IDs in the properly sorted order.
	 *
	 * @return array
	 */
	public static function sort_ads_by_sorted_ids( $ad_posts, $sorted_ids ) {
		$sorted_ad_posts = array();

		while ( ! empty( $sorted_ids ) ) {
			$sorted_id = array_shift( $sorted_ids );

			foreach ( $ad_posts as $key => $ad_post ) {
				if ( $ad_post->ID === $sorted_id ) {
					$sorted_ad_posts[] = $ad_post;
					unset( $ad_posts[ $key ] );
					break;
				}
			}
		}

		if ( ! empty( $ad_posts ) ) {
			foreach ( $ad_posts as $ad_post ) {
				$sorted_ad_posts[] = $ad_post;
			}
		}

		return $sorted_ad_posts;
	}

	private function sort_ads_auto( $ad_posts ) {
		$order_method = $this->get_order_method();

		if ( $order_method === 'random' ) {
			shuffle( $ad_posts );
		} elseif ( in_array( $order_method, ProBridge::instance()->pro_group_order_methods() ) && ProBridge::instance()->is_pro_loaded() ) {
			$args = array();

			if ( $order_method === 'weighted' ) {
				$args['ad_weights'] = $this->wo_meta->get_value( $this->term_meta(), 'ad_weights', array() );
			}

			$ad_posts = apply_filters( 'adcmdr_pro_sort_ads_auto', $ad_posts, $order_method, $this->group_id, $args );
		}

		return $ad_posts;
	}

	/**
	 * Process ad posts that have been loaded by this class instance.
	 * Processing includes either randomizing or sorting the ads.
	 *
	 * @return void
	 */
	private function process_ad_posts() {
		if ( $this->should_auto_sort() ) {
			$this->ad_posts = $this->sort_ads_auto( $this->ad_posts );
		} else {
			$sorted_ids = $this->wo_meta->get_value( $this->term_meta(), 'ad_order', array() );

			if ( ! $sorted_ids || ! is_array( $sorted_ids ) ) {
				$sorted_ids = array();
			}

			$this->ad_posts = self::sort_ads_by_sorted_ids( $this->ad_posts, $sorted_ids );
		}
	}

	/**
	 * Determine if ad images within this group should be responsively styled.
	 *
	 * @return bool
	 */
	private function responsive_ad_images() {
		return Util::truthy_or_site_default( $this->wo_meta->get_value( $this->term_meta(), 'responsive_banners', 'site_default' ), 'responsive_banners', 'general' );
	}

	/**
	 * Create list of classes for this group.
	 *
	 * @param mixed $classes Classes that should be included in this ad.
	 *
	 * @return string
	 */
	private function group_classes( $classes ) {
		$classes = Util::arrayify( $classes );

		$classes[] = Util::prefixed( 'group' );
		$classes[] = Util::prefixed( 'group-' . $this->group_id );
		$classes[] = Util::prefixed( 'group--' . sanitize_title( $this->mode() ) );

		if ( $this->responsive_ad_images() ) {
			$classes[] = Util::prefixed( 'ad-resp' );
		}

		$custom_classes = $this->wo_meta->get_value( $this->term_meta(), 'custom_classes' );
		if ( $custom_classes ) {
			$classes[] = $custom_classes;
		}

		/**
		 * Filter: adcmdr_group_start_class_list
		 * Filter the list of classes for a group, before they are converted into a string.
		 */
		$classes = array_unique( apply_filters( 'adcmdr_group_start_class_list', $classes, $this->group_id ) );

		return implode( ' ', $classes );
	}

	/**
	 * Create an inline style for manually-set margins.
	 * Add CSS variables for grid layouts.
	 *
	 * @return string
	 */
	private function group_inline_styles() {
		$inline = array();

		$margins = Util::inline_styles(
			array(
				'margin-top'    => $this->wo_meta->get_value( $this->term_meta(), 'margin_top', false ),
				'margin-right'  => $this->wo_meta->get_value( $this->term_meta(), 'margin_right', false ),
				'margin-bottom' => $this->wo_meta->get_value( $this->term_meta(), 'margin_bottom', false ),
				'margin-left'   => $this->wo_meta->get_value( $this->term_meta(), 'margin_left', false ),
			),
			true,
			'px',
			true,
			false
		);

		if ( $margins ) {
			$inline[] = $margins;
		}

		if ( $this->grid_cols && ProBridge::instance()->is_pro_loaded() ) {
			$inline[] = apply_filters( 'adcmdr_pro_grid_inline_styles', array(), $this->grid_cols );
		}

		return Util::make_inline_style( $inline );
	}

	/**
	 * Create HTML data attributes for rotating groups.
	 *
	 * @return string
	 */
	private function group_data_attributes() {
		return '';
	}

	/**
	 * HTML data attributes for the rotate wrapper.
	 *
	 * @return string
	 */
	private function rotate_data_attributes() {
		$data_attributes = array();

		$interval = absint( $this->wo_meta->get_value( $this->term_meta(), 'refresh', 5 ) );

		if ( ! $interval || $interval === 0 ) {
			$interval = 5;
		}

		$interval = $interval * 1000;

		$stop_tracking = absint( $this->wo_meta->get_value( $this->term_meta(), 'stop_tracking_i', 0 ) ) * 1000;

		return 'data-interval="' . esc_attr( $interval ) . '" data-stoptrack="' . esc_attr( $stop_tracking ) . '"';
	}

	/**
	 * If necessary, clearfix this group.
	 *
	 * @return string
	 */
	private function maybe_clearfix() {

		if ( Util::truthy( $this->wo_meta->get_value( $this->term_meta(), 'clear_float', false ) ) ) {
			return Util::clear_float();
		}

		return '';
	}

	/**
	 * If group is set to grid, create the grid wrappers.
	 *
	 * @return string
	 */
	private function group_mode_wrapper() {
		if ( $this->mode() === 'grid' && ProBridge::instance()->is_pro_loaded() ) {
			return apply_filters( 'adcmdr_pro_grid_wrapper', '' );
		} elseif ( $this->mode() === 'rotate' ) {
			return $this->rotate_wrapper();
		}

		return '';
	}

	/**
	 * Create the rotate wrapper.
	 *
	 * @return string
	 */
	private function rotate_wrapper() {
		return '<div class="' . esc_attr( Util::prefixed( 'rotate' ) ) . '" ' . $this->rotate_data_attributes() . '>';
	}

	/**
	 * If group is set to float, create the float wrapper.
	 *
	 * @return string
	 */
	private function group_float_wrapper() {

		$html = '';

		if ( ! $this->disable_wrappers ) {
			$float = $this->wo_meta->get_value( $this->term_meta(), 'float', false );

			if ( $float && ( $float === 'l' || $float === 'r' ) ) {
				$this->has_float = true;
				$html            = '<div class="' . esc_attr( Util::prefixed( 'f' . $float ) ) . '">';
			}
		}

		return $html;
	}

	/**
	 * Return custom code for insertion prior to group, if any exists.
	 *
	 * @return null|string
	 */
	private function before_group() {
		$html = '';

		if ( ! $this->disable_wrappers && Util::truthy_or_site_default( $this->wo_meta->get_value( $this->term_meta(), 'ad_label', 'site_default' ), 'ad_label', 'general' ) ) {

			/**
			 * Filter: adcmdr_ad_label_text
			 *
			 * @param $object_id The current object ID (group or an ad).
			 * @param $is_group Whether the filter is running on a group (true) or an ad (false)
			 */
			$ad_label_text = apply_filters( 'adcmdr_ad_label_text', Options::instance()->get( 'ad_label_text', 'general' ), $this->group_id, true );

			if ( $ad_label_text ) {
				$html .= '<div class="' . esc_attr( Util::prefixed( 'ad-label' ) ) . '">' . esc_html( $ad_label_text ) . '</div>';
			}
		}

		$html .= $this->wo_meta->get_value( $this->term_meta(), 'custom_code_before', '' );

		return $html;
	}

	/**
	 * Return custom code for insertion after the group, if any exists.
	 *
	 * @return null|string
	 */
	private function after_group() {
		return $this->wo_meta->get_value( $this->term_meta(), 'custom_code_after', '' );
	}

	/**
	 * Start the group and any necessary wrappers.
	 *
	 * @param array $classes If there are any classes pre-set, include them ehre.
	 *
	 * @return string
	 */
	public function group_start( $classes = array() ) {
		/**
		 * Filter: adcmdr_before_group_wrapper_start
		 *
		 * HTML to display before the group wrapper starts. Normally empty.
		 */
		$html = apply_filters( 'adcmdr_before_group_wrapper_start', '', $this->group_id );

		/**
		 * Filter: adcmdr_group_wrapper_start
		 *
		 * HTML for the start of the group wrapper.
		 * Includes the wrapper div, float, grid wrapper, and custom before-group code.
		 */
		$group_start_html = '';

		if ( ! $this->disable_wrappers ) {
			$group_start_html .= '<div class="' . esc_attr( $this->group_classes( $classes ) ) . '"' . $this->group_inline_styles() . $this->group_data_attributes() . '>';
			$group_start_html .= $this->group_float_wrapper();
		}

		/**
		 * Allow before_group custom code even if wrappers are disabled.
		 * Someone may want to inject extra JS or css this way?
		 */
		$group_start_html .= $this->before_group();

		if ( ! $this->disable_wrappers ) {
			$group_start_html .= $this->group_mode_wrapper();
		}

		$html .= apply_filters( 'adcmdr_group_wrapper_start', $group_start_html, $this->group_id );

		/**
		 * Filter: adcmdr_after_group_wrapper_start
		 *
		 * HTML to display after the group wrapper starts. Normally empty.
		 */
		$html .= apply_filters( 'adcmdr_after_group_wrapper_start', '', $this->group_id );

		/**
		 * Return all group start HTML.
		 */
		return $html;
	}

	/**
	 * End the group and any necessary wrappers.
	 *
	 * @return string
	 */
	public function group_end() {
		/**
		 * Filter: adcmdr_before_group_wrapper_end
		 *
		 * HTML to display before the group wrapper ends. Normally empty.
		 * This would also be located inside the float div.
		 */
		$html = apply_filters( 'adcmdr_before_group_wrapper_end', '', $this->group_id );

		/**
		 * Filter: adcmdr_group_wrapper_end
		 *
		 * HTML for the end of the ad wrapper.
		 * Includes the ending float div, any after-ad custom code, clearfix, and closing wrapper div.
		 */
		$group_html_end = '';

		if ( ! $this->disable_wrappers ) {
			if ( $this->mode() === 'grid' && ProBridge::instance()->is_pro_loaded() ) {
				$group_html_end .= apply_filters( 'adcmdr_pro_grid_wrapper_end', '' );
			} elseif ( $this->mode() === 'rotate' ) {
				$group_html_end .= '</div>';
			}
		}

		if ( ! $this->disable_wrappers && $this->has_float ) {
			$group_html_end .= '</div>';
		}

		$group_html_end .= $this->after_group();

		if ( ! $this->disable_wrappers ) {
			$group_html_end .= $this->maybe_clearfix();
			$group_html_end .= '</div>';
		}

		$html .= apply_filters( 'adcmdr_group_wrapper_end', $group_html_end, $this->group_id );

		/**
		 * Filter: adcmdr_after_group_wrapper_end
		 *
		 * HTML to display after the group wrapper ends. Normally empty.
		 */
		$html .= apply_filters( 'adcmdr_after_group_wrapper_end', '', $this->group_id );

		return $html;
	}

	/**
	 * Build ad HTML for display in the group.
	 *
	 * @param array $args An array of arguments.
	 *
	 * @return string
	 */
	private function build_ads( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'disable_wrappers' => false,
			)
		);

		$this->disable_wrappers = $args['disable_wrappers'];

		$this->load_ads();

		if ( empty( $this->ad_posts ) ) {
			return '';
		}

		$total = 0;
		if ( ! $this->disable_wrappers && $this->mode() === 'grid' && ProBridge::instance()->is_pro_loaded() ) {
			$rowscols = apply_filters(
				'adcmdr_pro_prepare_grid',
				array(
					'rows'  => 1,
					'cols'  => 1,
					'total' => 1,
				),
				$this->wo_meta->get_value( $this->term_meta(), 'grid-rows', 1 ),
				$this->wo_meta->get_value( $this->term_meta(), 'grid-cols', 1 )
			);

			$total           = $rowscols['total'];
			$this->grid_cols = $rowscols['cols'];
		} elseif ( $this->mode() === 'single' ) {
			$total = 1;
		}

		$ads_html     = '';
		$args['mode'] = $this->mode();
		$args['idx']  = 0;

		if ( ! $this->needs_consent() ) {
			$args['force_nocheck'] = true;
		}

		foreach ( $this->ad_posts as $ad_post ) {
			if ( $ad_post && is_a( $ad_post, 'WP_Post' ) ) {
				$ad      = new Ad( $ad_post, $this->group_id );
				$ad_html = $ad->build_ad( $args );

				if ( $ad_html != '' ) {
					$ads_html .= $ad_html;
					++$args['idx'];
				}
			}

			if ( $total > 0 && $args['idx'] >= $total ) {
				break;
			}
		}

		/**
		 * Filter: adcmdr_group_inner_html
		 *
		 * Filter the inner HTML for every ad that is created.
		 */
		$ads_html = apply_filters( 'adcmdr_group_inner_html', $ads_html, $this->group_id );

		/**
		 * Filter: adcmdr_ad_inner_html_#
		 *
		 * Same as above but only fires for this group ID.
		 */
		return apply_filters( 'adcmdr_group_inner_html_' . $this->group_id, $ads_html, $this->group_id );
	}

	/**
	 * Build the current group, if necessary.
	 *
	 * @param array $args An array of arguments.
	 *
	 * @return string
	 */
	public function build_group( $args = array() ) {

		$force_no_consent_check = false;

		if ( isset( $args['force_nocheck'] ) ) {
			$force_no_consent_check = $args['force_nocheck'];
		}
		$this->force_no_consent_check = $force_no_consent_check;

		if ( $this->should_block_bot() || $this->ads_disabled() || $this->needs_consent() ) {
			return '';
		}

		if ( ProBridge::instance()->is_pro_loaded() ) {
			if ( ! apply_filters( 'adcmdr_pro_group_passes_content_targeting', true, $this->wo_meta->get_value( $this->term_meta(), 'content_conditions', false ), $this->group_id ) ||
				! apply_filters( 'adcmdr_pro_group_passes_visitor_targeting', true, $this->wo_meta->get_value( $this->term_meta(), 'visitor_conditions', false ), $this->group_id ) ) {
				return '';
			}
		}

		$args = wp_parse_args(
			$args,
			array(
				'disable_wrappers' => false,
			)
		);

		$this->disable_wrappers = $args['disable_wrappers'];

		$ads_html = $this->build_ads( $args );

		if ( ! $ads_html ) {
			return '';
		}

		$html  = $this->group_start();
		$html .= $ads_html;
		$html .= $this->group_end();

		return $html;
	}

	/**
	 * Start an AJAX container for this group.
	 *
	 * @return string
	 */
	public function group_ajax_container_start() {
		/**
		 * Filter: adcmdr_group_ajax_container_start
		 *
		 * Filters the start of an AJAX container.
		 */
		$classes = Util::prefixed( 't' );

		if ( $this->needs_consent() ) {
			$classes .= ' ' . Util::prefixed( 'needs-consent' );
		}

		return apply_filters( 'adcmdr_group_ajax_container_start', '<div class="' . esc_attr( $classes ) . '" data-gid="' . esc_attr( $this->group_id ) . '">', $this->group_id );
	}

	/**
	 * End an ajax container.
	 *
	 * @return string
	 */
	public function group_ajax_container_end() {
		/**
		 * Filter: adcmdr_ajax_container_end
		 *
		 * Filters the end of an AJAX container.
		 */
		return apply_filters( 'adcmdr_ajax_container_end', '</div>', $this->group_id );
	}

	/**
	 * Create an ajax container for this group.
	 *
	 * @return string
	 */
	public function build_ajax_container() {
		if ( $this->should_block_bot() || $this->ads_disabled() ) {
			return '';
		}

		$html  = $this->group_ajax_container_start();
		$html .= $this->group_ajax_container_end();

		return $html;
	}
}
