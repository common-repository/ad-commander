<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Class for working with AdSense and related settings.
 */
class AdSense {
	/**
	 * An instance of this class.
	 *
	 * @var null|AdSense
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return AdSense
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		/**
		 * Maybe insert the AdSense script into wp_head.
		 */
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ), 10, 3 );
	}

	/**
	 * Types of AdSense ads
	 */
	public static function ad_formats() {
		return array(
			'responsive' => __( 'Display (Responsive)', 'ad-commander' ),
			'normal'     => __( 'Display (Fixed)', 'ad-commander' ),
			'multiplex'  => __( 'Multiplex', 'ad-commander' ),
			'inarticle'  => __( 'In-article', 'ad-commander' ),
			'infeed'     => __( 'In-feed', 'ad-commander' ),
		);
	}

	/**
	 * Multiplex types
	 *
	 * @return array
	 */
	public static function multiplex_ui_types() {
		return array(
			'default'               => __( 'Default', 'ad-commander' ),
			'image_sidebyside'      => __( 'Side by Side', 'ad-commander' ),
			'image_card_sidebyside' => __( 'Side by Side with Card', 'ad-commander' ),
			'image_stacked'         => __( 'Image Above Text', 'ad-commander' ),
			'text'                  => __( 'Text Only', 'ad-commander' ),
			'text_card'             => __( 'Text with Card', 'ad-commander' ),
		);
	}

	/**
	 * Allowed modes for this ad.
	 * We aren't using an associative array here because of all of the logic that goes into enabling/disabling modes in the admin.
	 * This is just used for allowed values that can be saved to the database.
	 *
	 * @return array
	 */
	public static function ad_modes() {
		return array( 'manual', 'direct', 'ad_code' );
	}

	/**
	 * Allowed AMP modes for individual ads.
	 *
	 * @return array
	 */
	public static function amp_modes() {
		return array(
			'site_default' => __( 'Site Default', 'ad-commander' ),
			'automatic'    => __( 'Convert AdSense ad to AMP ad', 'ad-commander' ),
			// 'dynamic'      => __( 'Dynamic size with set ratio', 'ad-commander' ),
			// 'fixed_height' => __( 'Responsive width and static height', 'ad-commander' ),
			'disable'      => __( 'Disable ad for AMP visits', 'ad-commander' ),
		);
	}

	/**
	 * Get publisher ID from settings.
	 *
	 * @return null|string
	 */
	public function current_adsense_publisher_id() {
		return Options::instance()->get( 'adsense_account', 'adsense' );
	}

	/**
	 * Check if publisher ID is valid
	 *
	 * @param mixed $pub_id The publisher ID to check. Defaults to current pub ID.
	 *
	 * @return bool
	 */
	public function is_publisher_id_valid( $pub_id = false ) {
		if ( ! $pub_id ) {
			$pub_id = $this->current_adsense_publisher_id();
		}

		if ( ! is_string( $pub_id ) ) {
			return false;
		}

		return substr( strtolower( $pub_id ), 0, 4 ) === 'pub-';
	}

	/**
	 * Get the base URL for AdSense JS
	 *
	 * @return string
	 */
	public function adsense_js_base_url() {
		/**
		 * Filter: adcmdr_adsense_js_base_url
		 *
		 * Filters the AdSense URL allowing you to change or add parameters if needed.
		 */
		return apply_filters( 'adcmdr_adsense_js_base_url', 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js' );
	}

	/**
	 * The full AdSense JS URL with parameters appended.
	 *
	 * @param null|string $publisher_id The publisher ID to append to the AdSense URL.
	 *
	 * @return string
	 */
	public function adsense_js_url( $publisher_id = null ) {
		$url = $this->adsense_js_base_url();

		if ( $publisher_id ) {
			$client_id = 'ca-' . $publisher_id;

			$url = add_query_arg(
				array(
					'client' => esc_attr( $client_id ),
				),
				$url
			);
		}

		/**
		 * Filter: adcmdr_adsense_js_url
		 *
		 * Filters the AdSense URL allowing you to change or add parameters if needed.
		 */
		return apply_filters( 'adcmdr_adsense_js_url', $url );
	}

	/**
	 * The AdSense script URL.
	 *
	 * @param bool|string $publisher_id A publisher id to use instead of loading from settings.
	 * @param bool|string $fallback_id A fake publisher id used for display purposes.
	 *
	 * @return string
	 */
	public function get_adsense_script_url( $publisher_id = false, $fallback_id = false ) {
		if ( ! $publisher_id ) {
			$publisher_id = $this->current_adsense_publisher_id();
		}

		if ( ! $publisher_id && $fallback_id ) {
			$publisher_id = $fallback_id;
		}

		if ( $publisher_id ) {
			return $this->adsense_js_url( $publisher_id );
		}

		return '';
	}

	/**
	 * Gets the full script tag for use in AdSense ads or for displaying in the admin.
	 * This is not the method we use for enqueuing in head. That is done with wp_enqueue_script() below.
	 *
	 * @param bool|string $publisher_id The publisher ID.
	 * @param bool|string $fallback_id A fake publisher id used for display purposes.
	 *
	 * @return string
	 */
	public function get_adsense_script_tag( $publisher_id = false, $fallback_id = false ) {
		if ( ! $publisher_id && ! $fallback_id ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This script is used within page content as part of AdSense ad code.
		return '<script async src="' . esc_url( $this->get_adsense_script_url( $publisher_id, $fallback_id ) ) . '" crossorigin="anonymous"></script>';
	}

	/**
	 * Maybe insert the AdSense script into wp_head.
	 */
	public function wp_enqueue_scripts() {
		if ( ! is_admin() && Options::instance()->get( 'insert_adsense_head_code', 'adsense', true ) && ! Amp::instance()->is_amp() ) {
			if ( apply_filters( 'adcmdr_adsense_head_script_enabled', true ) ) {
				$adsense_url = $this->get_adsense_script_url();

				if ( $adsense_url != '' ) {
					$handle = Util::ns( 'adsense' );

					wp_register_script(
						$handle,
						$adsense_url,
						array(),
						// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The version is intentionally null on this script, because we want to match Google AdSense's typical script tag.
						null,
						array(
							'in_footer' => false,
							'strategy'  => 'async',
						)
					);

					wp_enqueue_script( $handle );
				}
			}
		}
	}

	/**
	 * Filters the AdSense script tag after enqueue.
	 *
	 * @param string $tag The script tag.
	 * @param string $handle The script handle.
	 * @param string $src The script src.
	 *
	 * @return string
	 */
	public function script_loader_tag( $tag, $handle, $src ) {

		if ( $handle === Util::ns( 'adsense' ) ) {
			$tag = str_ireplace( '></script>', ' crossorigin="anonymous"></script>', $tag );
		}

		return $tag;
	}

	/**
	 * Get all of the ad units from the accounts array and convert them to AdSenseNetworkAdUnit objects.
	 *
	 * @param bool|array        $accounts The accounts array with current account data.
	 * @param bool|string       $adsense_id The publisher ID.
	 * @param bool|array|string $limit_ids Whether to limit the ad IDs returned.
	 * @param bool              $include_data Whether to include raw data.
	 *
	 * @return array
	 */
	public function get_google_ad_units( $accounts = false, $adsense_id = false, $limit_ids = false, $include_data = true ) {
		if ( ! $adsense_id ) {
			$adsense_id = $this->current_adsense_publisher_id();
		}

		if ( ! $accounts ) {
			$accounts = AdminAdSense::get_adsense_api_account();
		}

		if ( $limit_ids !== false && ! is_array( $limit_ids ) ) {
			$limit_ids = array( $limit_ids );
		}

		if ( ! isset( $accounts['ad_codes'] ) ) {
			$accounts['ad_codes'] = array();
		}

		$ad_units = array();

		if ( isset( $accounts['accounts'] )
		&& isset( $accounts['accounts'][ $adsense_id ] )
		&& isset( $accounts['accounts'][ $adsense_id ]['ad_units'] ) && isset( $accounts['accounts'][ $adsense_id ]['ad_units']['ads'] ) ) {
			foreach ( $accounts['accounts'][ $adsense_id ]['ad_units']['ads'] as $id => $data ) {

				if ( $limit_ids !== false && ! in_array( $id, $limit_ids, true ) ) {
					continue;
				}

				$ad_unit = new AdSenseNetworkAdUnit( $data, $id, $include_data );
				$ad_unit->set_ad_code( $accounts['ad_codes'] );

				$ad_units[ $id ] = $ad_unit;
			}
		}

		return $ad_units;
	}

	/**
	 * Google status codes for active ads.
	 *
	 * @return array
	 */
	public static function google_active_status_codes() {
		return array( 'ACTIVE', 'NEW' );
	}

	/**
	 * Google ad types that are supported by direct integration.
	 *
	 * @return array
	 */
	public static function supported_ad_types() {
		return array( 'DISPLAY', 'LINK', 'MATCHED_CONTENT', 'ARTICLE' );
	}

	/**
	 * Google ad types that have ad codes available from the API.
	 *
	 * @return array
	 */
	public static function available_ad_code_types() {
		return array( 'DISPLAY', 'LINK' );
	}

	/**
	 * Determine if all meta is set that is needed to display an adsense ad.
	 *
	 * @param mixed       $meta The meta for the current ad post.
	 * @param bool|WOMeta $wo_meta An instance of WOMeta.
	 *
	 * @return bool
	 */
	public function has_valid_ad( $meta, $wo_meta = false ) {
		if ( ! $meta || empty( $meta ) ) {
			return false;
		}

		if ( ! $wo_meta ) {
			$wo_meta = new WOMeta( AdCommander::ns() );
		}

		$mode = $wo_meta->get_value( $meta, 'adsense_ad_mode' );

		if ( ! $mode ) {
			return false;
		}

		if ( $mode === 'ad_code' ) {
			if ( ! $wo_meta->get_value( $meta, 'adsense_ad_code' ) ) {
				return false;
			} else {
				return true;
			}
		}

		if ( ! $wo_meta->get_value( $meta, 'adsense_adslot_id' ) ) {
			return false;
		}

		if ( ! $wo_meta->get_value( $meta, 'adsense_ad_pub_id' ) ) {
			return false;
		}

		$type = $wo_meta->get_value( $meta, 'adsense_ad_format' );

		if ( ! $type ) {
			return false;
		}

		if ( $type === 'infeed' && ! $wo_meta->get_value( $meta, 'adsense_layout_key' ) ) {
			return false;
		}

		return true;
	}
}
