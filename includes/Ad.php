<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Converts a WP_Post instance into a usable Ad.
 */
class Ad {

	/**
	 * The current ad post.
	 *
	 * @var \WP_Post
	 */
	private $ad;

	/**
	 * The current group ID (if one exists)
	 *
	 * @var int
	 */
	private $group_id;

	/**
	 * The current post's meta.
	 *
	 * @var mixed
	 */
	private $meta;

	/**
	 * The current ad's type as set in the WP admin.
	 *
	 * @var string
	 */
	private $ad_type;

	/**
	 * An instance of an AdType class.
	 *
	 * @var AdTypeBanner|AdTypeContent|AdTypeAdSense
	 */
	private $ad_type_instance;

	/**
	 * Whether this ad is floated or not.
	 *
	 * @var bool
	 */
	private $has_float;

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
	 * Does this ad need consent?
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
	 * Is this ad being rendered for AMP?
	 */
	private $is_amp = false;

	/**
	 * Ad class __construct.
	 *
	 * @param \WP_Post $ad The WP_Post object to use while creating this Ad.
	 * @param bool     $group_id The group this Ad belongs to, if any.
	 */
	public function __construct( \WP_Post $ad, $group_id = false ) {

		$this->ad       = $ad;
		$this->group_id = $group_id;

		$this->wo_meta = new WOMeta( AdCommander::ns() );
		$this->meta    = $this->wo_meta->get_post_meta( $ad->ID, AdPostMeta::post_meta_keys() );

		$this->ad_type = $this->wo_meta->get_value( $this->meta, 'adtype', false );

		/**
		 * Initiate AdType class for this ad.
		 */
		switch ( $this->ad_type ) {
			case 'bannerad':
				$this->ad_type_instance = new AdTypeBanner( $this->ad, $this->meta, $this->wo_meta );
				break;

			case 'textcode':
			case 'richcontent':
				$this->ad_type_instance = new AdTypeContent( $this->ad, $this->meta, $this->wo_meta, $this->ad_type );
				break;

			case 'adsense':
				$this->ad_type_instance = new AdTypeAdSense( $this->ad, $this->meta, $this->wo_meta );
				break;

			default:
				break;
		}
	}

	/**
	 * Check if the current ad is valid.
	 *
	 * @return bool
	 */
	private function has_valid_ad() {
		return Util::has_valid_ad( $this->ad_type, $this->meta, $this->wo_meta, $this->ad->ID );
	}

	/**
	 * Do we need consent to display?
	 *
	 * @return bool
	 */
	private function needs_consent() {

		if ( $this->needs_consent === null ) {
			if ( $this->wo_meta->get_value( $this->meta, 'disable_consent', false ) ) {
				$this->needs_consent = false;
				return $this->needs_consent;
			}

			$global_needs_consent = Consent::instance()->global_needs_consent( $this->force_no_consent_check );
			$this->needs_consent  = $global_needs_consent;
		}

		return $this->needs_consent;
	}

	/**
	 * Determine if we should use ajax loading for this ad.
	 *
	 * @return bool
	 */
	public function should_ajax() {
		/**
		 * If part of a group, never load over ajax. The group will load over ajax.
		 */
		if ( $this->group_id || ! ProBridge::instance()->is_pro_loaded() || $this->is_amp ) {
			return false;
		}

		$render = Util::render_method();

		/**
		 * If render method is Ajax, load over ajax.
		 */
		if ( $render === 'clientside' ) {
			return true;
		}

		/**
		 * If render method is serverside, never load over ajax.
		 */
		if ( $render === 'serverside' ) {
			return false;
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

		/*
		 * If max_clicks or max_impressions are set, load over ajax because otherwise expirations won't work.
		 */
		$max_clicks      = $this->wo_meta->get_value( $this->meta, 'expire_clicks', false );
		$max_impressions = $this->wo_meta->get_value( $this->meta, 'expire_impressions', false );

		if ( $max_clicks || $max_impressions ) {
			return true;
		}

		$visitor_conditions = $this->wo_meta->get_value( $this->meta, 'visitor_conditions', false );
		return ( ( $visitor_conditions && ! empty( $visitor_conditions ) ) );
	}

	/**
	 * Determine if this ad has max tracking and expire the ad if they're reached.
	 *
	 * @return bool
	 */
	private function check_max_tracking() {

		if ( ProBridge::instance()->is_pro_loaded() ) {
			$max_clicks      = $this->wo_meta->get_value( $this->meta, 'expire_clicks', false );
			$max_impressions = $this->wo_meta->get_value( $this->meta, 'expire_impressions', false );

			return apply_filters( 'adcmdr_pro_check_max_tracking', false, $this->ad, $max_clicks, $max_impressions );
		}

		return false;
	}

	/**
	 * Potentially block a bot from seeing the ad.
	 *
	 * @return bool
	 */
	private function should_block_bot() {
		/**
		 * Already checked when the group started.
		 */
		if ( $this->group_id ) {
			return false;
		}

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
		/**
		 * Already checked when the group started.
		 */
		if ( $this->group_id ) {
			return false;
		}

		if ( Targeting::ads_disabled_all() ) {
			return true;
		}

		if ( TargetingVisitor::ads_disabled_user_role() ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if tracking is disabled for this individual ad.
	 *
	 * @param string $type Impressoin or Click tracking (i or c).
	 *
	 * @return bool
	 */
	private function ad_tracking_disabled( $type ) {
		return Util::truthy( $this->wo_meta->get_value( $this->meta, 'donottrack_' . $type, false ) );
	}

	/**
	 * Determine if we should set banners in this ad to responsive width and height.
	 *
	 * @return bool
	 */
	private function responsive_ad_images() {
		return ! $this->group_id && Util::truthy_or_site_default( $this->wo_meta->get_value( $this->meta, 'responsive_banners', 'site_default' ), 'responsive_banners', 'general' );
	}

	/**
	 * Create list of classes for this ad.
	 *
	 * @param mixed $classes Classes that should be included in this ad.
	 *
	 * @return string
	 */
	private function ad_classes( $classes ) {
		$classes = Util::arrayify( $classes );

		$classes[] = Util::prefixed( 'ad' );
		$classes[] = Util::prefixed( 'ad-' . $this->ad->ID );

		if ( $this->responsive_ad_images() ) {
			$classes[] = Util::prefixed( 'ad-resp' );
		}

		if ( $this->is_amp ) {
			$classes[] = Util::prefixed( 'amp-ad' );
		}

		$custom_classes = $this->wo_meta->get_value( $this->meta, 'custom_classes' );
		if ( $custom_classes ) {
			$classes[] = $custom_classes;
		}

		/**
		 * Filter: adcmdr_ad_start_class_list
		 * Filter the list of classes for an individual ad, before they are converted into a string.
		 */
		$classes = array_unique( apply_filters( 'adcmdr_ad_start_class_list', $classes, $this->ad ) );

		return implode( ' ', $classes );
	}

	/**
	 * Create HTML data attributes for ad tracking and identification on the front-end.
	 *
	 * @return string
	 */
	private function ad_data_attributes() {
		$data_attributes = ' data-t-id="' . esc_attr( $this->ad->ID ) . '" data-t-title="' . esc_attr( wp_strip_all_tags( $this->ad->post_title ) ) . '"';

		if ( $this->ad_tracking_disabled( 'i' ) ) {
			$data_attributes .= ' data-ti-disabled="true"';
		}

		if ( $this->ad_tracking_disabled( 'c' ) ) {
			$data_attributes .= ' data-tc-disabled="true"';
		}

		return $data_attributes;
	}

	/**
	 * Create an inline style for manually-set margins.
	 *
	 * @return string
	 */
	private function ad_inline_styles() {
		return Util::inline_styles(
			array(
				'margin-top'    => $this->wo_meta->get_value( $this->meta, 'margin_top', false ),
				'margin-right'  => $this->wo_meta->get_value( $this->meta, 'margin_right', false ),
				'margin-bottom' => $this->wo_meta->get_value( $this->meta, 'margin_bottom', false ),
				'margin-left'   => $this->wo_meta->get_value( $this->meta, 'margin_left', false ),
			)
		);
	}

	/**
	 * If ad is set to float and is not part of a group, create the float wrapper.
	 *
	 * @return string
	 */
	private function ad_float_wrapper() {
		$html = '';

		if ( ! $this->disable_wrappers && ! $this->group_id ) {
			$float = $this->wo_meta->get_value( $this->meta, 'float', false );

			if ( $float && ( $float === 'l' || $float === 'r' ) ) {
				$this->has_float = true;
				$float_class     = Util::prefixed( 'f' . $float );

				$html = '<div class="' . esc_attr( $float_class ) . '">';
			}
		}

		return $html;
	}

	/**
	 * If necessary, clearfix this ad.
	 *
	 * @return string
	 */
	private function maybe_clearfix() {
		if ( $this->disable_wrappers || $this->group_id ) {
			return '';
		}

		if ( Util::truthy( $this->wo_meta->get_value( $this->meta, 'clear_float', false ) ) ) {
			return Util::clear_float();
		}

		return '';
	}

	/**
	 * Return custom code for insertion prior to ad, if any exists.
	 *
	 * @return null|string
	 */
	private function before_ad() {
		$html = '';

		if ( ! $this->group_id && ! $this->disable_wrappers && Util::truthy_or_site_default( $this->wo_meta->get_value( $this->meta, 'ad_label', 'site_default' ), 'ad_label', 'general' ) ) {

			/**
			 * Filter: adcmdr_ad_label_text
			 *
			 * @param $object_id The current object ID (group or an ad).
			 * @param $is_group Whether the filter is running on a group (true) or an ad (false)
			 */
			$ad_label_text = apply_filters( 'adcmdr_ad_label_text', Options::instance()->get( 'ad_label_text', 'general' ), $this->ad->ID, false );

			if ( $ad_label_text ) {
				$html .= '<div class="' . esc_attr( Util::prefixed( 'ad-label' ) ) . '">' . esc_html( $ad_label_text ) . '</div>';
			}
		}

		$html .= $this->wo_meta->get_value( $this->meta, 'custom_code_before', '' );

		return $html;
	}

	/**
	 * Return custom code for insertion after the ad, if any exists.
	 *
	 * @return null|string
	 */
	private function after_ad() {
		return $this->wo_meta->get_value( $this->meta, 'custom_code_after', '' );
	}

	/**
	 * Start the ad and any necessary wrappers.
	 *
	 * @param array $classes If there are any classes pre-set by a group, include them ehre.
	 *
	 * @return string
	 */
	private function ad_start( $classes = array() ) {
		/**
		 * Filter: adcmdr_before_ad_wrapper_start
		 *
		 * HTML to display before the ad wrapper starts. Normally empty.
		 */
		$html = apply_filters( 'adcmdr_before_ad_wrapper_start', '', $this->ad );

		/**
		 * Filter: adcmdr_ad_wrapper_start
		 *
		 * HTML for the start of the ad wrapper.
		 * Includes the wrapper div, float, and custom before-ad code.
		 */
		$ad_html_start = '';

		if ( ! $this->disable_wrappers ) {
			$ad_html_start .= '<div class="' . esc_attr( $this->ad_classes( $classes ) ) . '"' . $this->ad_data_attributes() . $this->ad_inline_styles() . '>';
			$ad_html_start .= $this->ad_float_wrapper();
		}

		$ad_html_start .= $this->before_ad();

		$html .= apply_filters( 'adcmdr_ad_wrapper_start', $ad_html_start, $this->ad );

		/**
		 * Filter: adcmdr_before_ad_wrapper_start
		 *
		 * HTML to display after the ad wrapper starts. Normally empty.
		 */
		$html .= apply_filters( 'adcmdr_after_ad_wrapper_start', '', $this->ad );

		/**
		 * Return all ad start HTML.
		 */
		return $html;
	}

	/**
	 * End the ad and any necessary wrappers.
	 *
	 * @return string
	 */
	private function ad_end() {
		/**
		 * Filter: adcmdr_before_ad_wrapper_end
		 *
		 * HTML to display before the ad wrapper ends. Normally empty.
		 * This would also be located inside the float div.
		 */
		$html = apply_filters( 'adcmdr_before_ad_wrapper_end', '', $this->ad );

		/**
		 * Filter: adcmdr_ad_wrapper_end
		 *
		 * HTML for the end of the ad wrapper.
		 * Includes the ending float div, any after-ad custom code, clearfix, and closing wrapper div.
		 */
		$ad_html_end = '';

		if ( ! $this->disable_wrappers && $this->has_float ) {
			$ad_html_end .= '</div>';
		}

		$ad_html_end .= $this->after_ad();

		if ( ! $this->disable_wrappers ) {
			$ad_html_end .= $this->maybe_clearfix();
			$ad_html_end .= '</div>';
		}

		$html .= apply_filters( 'adcmdr_ad_wrapper_end', $ad_html_end, $this->ad );

		/**
		 * Filter: adcmdr_after_ad_wrapper_end
		 *
		 * HTML to display after the ad wrapper ends. Normally empty.
		 */
		$html .= apply_filters( 'adcmdr_after_ad_wrapper_end', '', $this->ad );

		return $html;
	}

	/**
	 * Start an AJAX container for this ad.
	 *
	 * @return string
	 */
	public function ad_ajax_container_start() {
		/**
		 * Filter: adcmdr_ad_ajax_container_start
		 *
		 * Filters the start of an AJAX container.
		 */
		$classes = Util::prefixed( 't' );

		if ( $this->needs_consent() ) {
			$classes .= ' ' . Util::prefixed( 'needs-consent' );
		}

		return apply_filters( 'adcmdr_ad_ajax_container_start', '<div class="' . esc_attr( $classes ) . '" data-gid="a' . esc_attr( $this->ad->ID ) . '">', $this->ad->ID );
	}

	/**
	 * End an ajax container.
	 *
	 * @return string
	 */
	public function ad_ajax_container_end() {
		/**
		 * Filter: adcmdr_ajax_container_end
		 *
		 * Filters the end of an AJAX container.
		 */
		return apply_filters( 'adcmdr_ad_ajax_container_end', '</div>', $this->ad->ID );
	}

	/**
	 * Create the ad inside the wrapper.
	 *
	 * @return string
	 */
	private function ad() {
		if ( ! $this->ad_type_instance ) {
			return '';
		}

		/**
		 * Filter: adcmdr_ad_inner_html
		 *
		 * Filter the inner HTML for every ad that is created.
		 */
		$html = apply_filters( 'adcmdr_ad_inner_html', $this->ad_type_instance->build_ad(), $this->ad );

		/**
		 * Filter: adcmdr_ad_inner_html_#
		 *
		 * Same as above but only fires for this ad ID.
		 */
		return apply_filters( 'adcmdr_ad_inner_html_' . $this->ad->ID, $html, $this->ad );
	}

	/**
	 * Build the current ad, if necessary.
	 *
	 * @param array $args Arguments for this ad.
	 *
	 * @return string
	 */
	public function build_ad( $args = array() ) {

		$this->is_amp                 = Amp::instance()->is_amp();
		$this->force_no_consent_check = false;

		if ( isset( $args['force_nocheck'] ) ) {
			$this->force_no_consent_check = $args['force_nocheck'];
		}

		if ( $this->should_block_bot() ||
			! $this->has_valid_ad() ||
			$this->check_max_tracking() ||
			$this->ads_disabled() ||
			$this->needs_consent() ||
			! Targeting::check_andor_groups( $this->wo_meta->get_value( $this->meta, 'content_conditions', false ), 'content', AdCommander::posttype_ad(), $this->ad->ID ) ||
			! Targeting::check_andor_groups( $this->wo_meta->get_value( $this->meta, 'visitor_conditions', false ), 'visitor', AdCommander::posttype_ad(), $this->ad->ID )
		) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'disable_wrappers' => false,
			)
		);

		$this->disable_wrappers = $args['disable_wrappers'];

		$classes = array();

		if ( isset( $args['mode'] ) && $args['mode'] === 'rotate' ) {
			$classes[] = 'woslide';

			if ( isset( $args['idx'] ) && $args['idx'] === 0 ) {
				$classes[] = 'woactive';
			}
		}

		$ad_html = $this->ad();

		if ( $ad_html !== '' ) {
			$html  = $this->ad_start( $classes );
			$html .= $ad_html;
			$html .= $this->ad_end();
		}

		if ( $html !== '' && $this->is_amp && ! $this->ad_tracking_disabled( 'i' ) ) {
			TrackingAmp::instance()->queue_impression(
				array(
					'ad_id' => $this->ad->ID,
					'title' => wp_strip_all_tags( $this->ad->post_title ),
				)
			);
		}

		return $html;
	}

	/**
	 * Create an ajax container for this group.
	 *
	 * @return string
	 */
	public function build_ajax_container() {
		if ( $this->should_block_bot() ||
		$this->ads_disabled() ||
		! Targeting::check_andor_groups( $this->wo_meta->get_value( $this->meta, 'content_conditions', false ), 'content', AdCommander::posttype_ad(), $this->ad->ID ) ||
		! Targeting::check_andor_groups( $this->wo_meta->get_value( $this->meta, 'visitor_conditions', false ), 'visitor', AdCommander::posttype_ad(), $this->ad->ID ) ) {
			return '';
		}

		$html  = $this->ad_ajax_container_start();
		$html .= $this->ad_ajax_container_end();

		return $html;
	}
}
