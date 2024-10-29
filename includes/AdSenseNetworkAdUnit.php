<?php
namespace ADCmdr;

/**
 * Converts raw AdSense data into a more usable object.
 */
class AdSenseNetworkAdUnit {
	/**
	 * The ad ID.
	 *
	 * @var string
	 */
	public $id = null;

	/**
	 * The publisher ID.
	 *
	 * @var string
	 */
	public $pub_id = null;

	/**
	 * The ad slot ID.
	 *
	 * @var string|int
	 */
	public $slot_id = null;

	/**
	 * The ad display name.
	 *
	 * @var string
	 */
	public $display_name = null;

	/**
	 * The ad status.
	 *
	 * @var string
	 */
	public $status = null;

	/**
	 * The ad active status.
	 *
	 * @var bool
	 */
	public $active = false;

	/**
	 * The ad script code.
	 *
	 * @var string
	 */
	public $ad_code = '';

	/**
	 * The ad ID.
	 *
	 * @var array|string
	 */
	public $size = null;

	/**
	 * The type of ad.
	 *
	 * @var string
	 */
	public $type = null;

	/**
	 * The display name for the type of ad.
	 *
	 * @var string
	 */
	public $display_type = null;

	/**
	 * Whether to make this ad full width responsive.
	 *
	 * @var string
	 */
	public $full_width_responsive = 'default';

	/**
	 * The ad layout key.
	 *
	 * @var string
	 */
	public $layout_key = null;

	/**
	 * Whether the ad is unsupported or not.
	 *
	 * @var bool
	 */
	public $unsupported = true;

	/**
	 * The ad raw data.
	 *
	 * @var array
	 */
	public $data = null;

	/**
	 * __construct
	 *
	 * @param array       $data The raw data used to build the ad.
	 * @param bool|string $id The ad ID.
	 * @param bool        $include_data Whether to save the raw data or not.
	 */
	public function __construct( $data, $id = false, $include_data = true ) {

		if ( ! $id ) {
			$id = ( isset( $data['id'] ) ) ? $data['id'] : '';
		}

		$this->id = trim( $id );

		if ( strpos( $this->id, ':' ) !== false ) {
			$parts = explode( ':', $this->id );

			$pub_id  = trim( $parts[0] );
			$slot_id = trim( $parts[1] );

			if ( substr( strtolower( $pub_id ), 0, 3 ) === 'ca-' ) {
				$pub_id = substr( $pub_id, 3 );
			}

			$this->pub_id  = $pub_id;
			$this->slot_id = $slot_id;

		} elseif ( isset( $data['code'] ) ) {
			$this->slot_id = trim( $data['code'] );
		}

		$this->display_name = isset( $data['display_name'] ) ? esc_html( trim( $data['display_name'] ) ) : $this->slot_id;
		$this->status       = isset( $data['status'] ) ? esc_html( trim( strtoupper( $data['status'] ) ) ) : 'UNKNOWN';
		$this->active       = in_array( $this->status, AdSense::google_active_status_codes(), true );
		$this->type         = ( isset( $data['contentAdsSettings'] ) && isset( $data['contentAdsSettings']['type'] ) ) ? esc_html( trim( strtoupper( $data['contentAdsSettings']['type'] ) ) ) : null;
		$this->display_type = esc_html( $this->parse_display_type( $this->type ) );

		if ( $this->type !== null ) {
			$this->unsupported = ! in_array( $this->type, AdSense::supported_ad_types(), true );
		}

		$size = ( isset( $data['contentAdsSettings'] ) && isset( $data['contentAdsSettings']['size'] ) ) ? esc_html( trim( $data['contentAdsSettings']['size'] ) ) : null;

		if ( $size !== null ) {
			$size = str_ireplace( 'SIZE_', '', $size );
			$size = str_replace( '_', 'x', $size );
		}

		if ( $size === '1x3' ) {
			$size = 'Responsive';
		} elseif ( strpos( $size, 'x' ) !== false ) {
			$dimensions = explode( 'x', $size );
			$size       = array(
				'width'  => isset( $dimensions[0] ) ? intval( $dimensions[0] ) : 0,
				'height' => isset( $dimensions[1] ) ? intval( $dimensions[1] ) : 0,
			);
		}

		$this->size = $size;

		if ( $include_data ) {
			$this->data = $data;
		}
	}

	/**
	 * Parse the ad type into a user-friendly display type.
	 *
	 * @param string $type The raw ad type.
	 *
	 * @return string
	 */
	public function parse_display_type( $type ) {
		switch ( $type ) {
			case 'ARTICLE':
				return _x( 'In-article', 'AdSense ad format', 'ad-commander' );

			case 'DISPLAY':
				return _x( 'Display', 'AdSense ad format', 'ad-commander' );

			case 'FEED':
				return _x( 'Feed', 'AdSense ad format', 'ad-commander' );

			case 'LINK':
				return _x( 'Link', 'AdSense ad format', 'ad-commander' );

			case 'MATCHED_CONTENT':
				return _x( 'Multiplex', 'AdSense ad format', 'ad-commander' );

			default:
				return _x( 'Unknown', 'AdSense ad format', 'ad-commander' ) . ' (' . $type . ')';
		}
	}

	/**
	 * Set the ad code for an ad, if it exists.
	 *
	 * @param bool|array $ad_codes The account ad codes.
	 *
	 * @return void
	 */
	public function set_ad_code( $ad_codes = false ) {
		if ( ! $ad_codes ) {
			$accounts = AdminAdSense::get_adsense_api_account();
			if ( isset( $accounts['ad_codes'] ) && is_array( $accounts['ad_codes'] ) ) {
				$ad_codes = $accounts['ad_codes'];
			}
		}

		if ( ! $ad_codes ) {
			return;
		}

		if ( isset( $ad_codes[ $this->id ] ) ) {
			$this->ad_code = $ad_codes[ $this->id ];
		}

		$this->set_data_from_ad_code();
	}

	/**
	 * Parse some data from the ad code and save it to the ad unit.
	 *
	 * @return void
	 */
	private function set_data_from_ad_code() {
		if ( $this->ad_code ) {
			preg_match_all( '/data-full-width-responsive="(?<full_width_responsive>.+?)"|data-layout-key="(?<layout_key>.+?)"/', $this->ad_code, $matches );

			$full_width_responsive = isset( $matches['full_width_responsive'] ) && ! empty( $matches['full_width_responsive'] ) ? $matches['full_width_responsive'][0] : false;
			$layout_key            = isset( $matches['layout_key'] ) && ! empty( $matches['layout_key'] ) ? $matches['layout_key'][0] : false;

			if ( $full_width_responsive ) {
				$full_width_responsive = esc_html( trim( strtolower( $full_width_responsive ) ) );

				if ( in_array( $full_width_responsive, array( 'true', 'false', 'default' ), true ) ) {
					$this->full_width_responsive = $full_width_responsive;
				}
			}

			if ( $layout_key ) {
				$this->layout_key = esc_html( trim( $layout_key ) );
			}
		}
	}
}
