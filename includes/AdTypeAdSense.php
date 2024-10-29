<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Class to create a AdSense ad.
 *
 * Doesn't extend Ad class, but must be used in conjunction with it.
 */
class AdTypeAdSense {
	/**
	 * The current ad post.
	 *
	 * @var \WP_Post
	 */
	private $ad;

	/**
	 * The current post's meta.
	 *
	 * @var mixed
	 */
	private $meta;

	/**
	 * The current instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * Instance of AdTypeAdSenseAMP
	 *
	 * @var AdTypeAdSenseAMP|bool
	 */
	private $ad_type_amp = false;

	/**
	 * AdSenseNetworkAdUnit class __construct.
	 *
	 * @param \WP_Post $ad The WP_Post object to use while creating this Ad.
	 * @param array    $meta The current meta for the post (so we don't have to re-query it).
	 * @param WOMeta   $wo_meta An instance of WOMeta, so we don't have to re-create it.
	 */
	public function __construct( \WP_Post $ad, array $meta, WOMeta $wo_meta ) {
		$this->ad      = $ad;
		$this->meta    = $meta;
		$this->wo_meta = $wo_meta;
	}

	/**
	 * The adsbygoogle variable script.
	 *
	 * @return string
	 */
	private function script_adsbygoogle_var() {
		return '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
	}

	/**
	 * Build a non-responsive ad.
	 *
	 * @param string|int $slot_id The slot ID.
	 * @param string     $pub_id The publisher ID.
	 * @param string     $type The type of ad.
	 *
	 * @return string
	 */
	private function build_type_normal( $slot_id, $pub_id, $type ) {
		$custom_data = array();
		$style       = 'display:inline-block;';
		$width       = intval( $this->wo_meta->get_value( $this->meta, 'adsense_size_width', null ) );
		$height      = intval( $this->wo_meta->get_value( $this->meta, 'adsense_size_height', null ) );

		if ( $width > 0 ) {
			$style .= 'width:' . $width . 'px;';
		}

		if ( $height > 0 ) {
			$style .= 'height:' . $height . 'px;';
		}

		$ad_html  = AdSense::instance()->get_adsense_script_tag( $pub_id );
		$ad_html .= '<ins class="adsbygoogle" style="' . esc_attr( $style ) . '" data-ad-client="' . esc_attr( 'ca-' . $pub_id ) . '" data-ad-slot="' . esc_attr( $slot_id ) . '"';

		$custom_data = apply_filters( 'adcmdr_adsense_ad_custom_data', $custom_data, $ad_html, $slot_id, $pub_id, $type );
		$ad_html     = self::append_custom_data( $ad_html, $custom_data, $slot_id, $pub_id, $type );

		$ad_html .= '></ins>';
		$ad_html .= $this->script_adsbygoogle_var();

		if ( $this->ad_type_amp !== false ) {
			$ad_html = $this->ad_type_amp->build_amp_ad(
				$ad_html,
				$slot_id,
				$pub_id,
				array(
					'responsive'  => false,
					'type'        => $type,
					'width'       => $width,
					'height'      => $height,
					'custom_data' => $custom_data,
				)
			);
		}

		return $ad_html;
	}

	/**
	 * Build ads that are potentially responsive (some exceptions apply by settings).
	 *
	 * @param string|int $slot_id The slot ID.
	 * @param string     $pub_id The publisher ID.
	 * @param string     $type The type of ad.
	 *
	 * @return string
	 */
	private function build_type_maybe_responsive( $slot_id, $pub_id, $type ) {
		$style       = 'display:block;';
		$full_width  = false;
		$format      = false;
		$layout      = false;
		$layout_key  = false;
		$ui_type     = false;
		$custom_data = array();
		$width       = false;
		$height      = false;

		switch ( $type ) {
			case 'multiplex':
				$width  = intval( $this->wo_meta->get_value( $this->meta, 'adsense_size_width', null ) );
				$height = intval( $this->wo_meta->get_value( $this->meta, 'adsense_size_height', null ) );

				if ( $width > 0 && $height > 0 ) {
					$style = 'display:inline-block;width:' . $width . 'px;height:' . $height . 'px;';
				} else {
					$format = 'autorelaxed';
				}

				$ui_type = $this->wo_meta->get_value( $this->meta, 'adsense_multiplex_uitype' );
				if ( $ui_type && $ui_type !== 'default' && in_array( $ui_type, array_keys( AdSense::multiplex_ui_types() ), true ) ) {

					$columns = intval( $this->wo_meta->get_value( $this->meta, 'adsense_multiplex_cols', null ) );
					$rows    = intval( $this->wo_meta->get_value( $this->meta, 'adsense_multiplex_rows', null ) );

					if ( $columns > 0 && $rows > 0 ) {
						$custom_data['matched-content-ui-type']     = $ui_type;
						$custom_data['matched-content-columns-num'] = $columns;
						$custom_data['matched-content-rows-num ']   = $rows;
					}
				}

				break;

			case 'inarticle':
				$format     = 'fluid';
				$layout     = 'in-article';
				$style      = 'display:block;text-align:center;';
				$full_width = $this->wo_meta->get_value( $this->meta, 'adsense_full_width_responsive', 'default' );
				break;

			case 'infeed':
				$format     = 'fluid';
				$layout_key = $this->wo_meta->get_value( $this->meta, 'adsense_layout_key', null );

				if ( ! $layout_key ) {
					return '';
				}
				break;

			default:
				$format     = 'auto';
				$full_width = $this->wo_meta->get_value( $this->meta, 'adsense_full_width_responsive', 'default' );
				break;
		}

		$ad_html  = AdSense::instance()->get_adsense_script_tag( $pub_id );
		$ad_html .= '<ins class="adsbygoogle" style="' . esc_attr( $style ) . '" data-ad-client="' . esc_attr( 'ca-' . $pub_id ) . '" data-ad-slot="' . esc_attr( $slot_id ) . '"';

		if ( $format ) {
			$ad_html .= ' data-ad-format="' . esc_attr( $format ) . '"';
		}

		if ( $layout ) {
			$ad_html .= ' data-ad-layout="' . esc_attr( $layout ) . '"';
		}

		if ( $layout_key ) {
			$ad_html .= ' data-ad-layout-key="' . esc_attr( $layout_key ) . '"';
		}

		if ( $full_width && $full_width !== 'default' ) {
			$ad_html .= ' data-full-width-responsive="' . esc_attr( $full_width ) . '"';
		}

		$custom_data = apply_filters( 'adcmdr_adsense_ad_custom_data', $custom_data, $ad_html, $slot_id, $pub_id, $type );
		$ad_html     = self::append_custom_data( $ad_html, $custom_data, $slot_id, $pub_id, $type );

		$ad_html .= '></ins>';

		$ad_html .= $this->script_adsbygoogle_var();

		if ( $this->ad_type_amp !== false ) {
			$ad_html = $this->ad_type_amp->build_amp_ad(
				$ad_html,
				$slot_id,
				$pub_id,
				array(
					'responsive'  => true,
					'full_width'  => $full_width,
					'format'      => $format,
					'layout'      => $layout,
					'layout_key'  => $layout_key,
					'width'       => $width,
					'height'      => $height,
					'type'        => $type,
					'ui_type'     => $ui_type,
					'custom_data' => $custom_data,
				)
			);
		}

		return $ad_html;
	}

	/**
	 * Append custom data to ad HTML.
	 *
	 * @param string     $ad_html The current ad HTML.
	 * @param array      $custom_data The custom data to append.
	 * @param string|int $slot_id The slot ID.
	 * @param string     $pub_id The publisher ID.
	 * @param string     $type The type of ad.
	 *
	 * @return string
	 */
	public static function append_custom_data( $ad_html, $custom_data, $slot_id, $pub_id, $type ) {

		if ( ! empty( $custom_data ) ) {
			foreach ( $custom_data as $key => $data ) {
				$ad_html .= ' data-' . sanitize_text_field( $key ) . '="' . esc_attr( $data ) . '"';
			}
		}

		return $ad_html;
	}

	/**
	 * Build the AdSense ad.
	 *
	 * @return string
	 */
	public function build_ad() {

		$ad      = '';
		$type    = null;
		$slot_id = null;
		$pub_id  = null;
		$mode    = $this->wo_meta->get_value( $this->meta, 'adsense_ad_mode', null );

		if ( $mode === 'ad_code' ) {
			$ad_code = $this->wo_meta->get_value( $this->meta, 'adsense_ad_code', null );

			if ( ! $ad_code ) {
				return '';
			}

			$ad = $ad_code;

		} else {

			$type    = $this->wo_meta->get_value( $this->meta, 'adsense_ad_format', null );
			$slot_id = $this->wo_meta->get_value( $this->meta, 'adsense_adslot_id', null );
			$pub_id  = $this->wo_meta->get_value( $this->meta, 'adsense_ad_pub_id', null );
			$pub_id  = ( $pub_id ) ? $pub_id : AdSense::instance()->current_adsense_publisher_id();

			if ( ! $type || ! $slot_id || ! $pub_id ) {
				return '';
			}

			/**
			 * Set up AMP in case this is an AMP visit.
			 */
			if ( ProBridge::instance()->is_pro_loaded() && class_exists( '\ADCmdr\AdTypeAdSenseAMP' ) ) {
				$this->ad_type_amp = new AdTypeAdSenseAMP();
				$amp_mode          = $this->wo_meta->get_value( $this->meta, 'adsense_amp_ad_mode', null );

				if ( ! $amp_mode || $amp_mode === 'site_default' ) {
					$amp_mode = Options::instance()->get( 'adsense_amp_ad_mode', 'adsense', false, 'automatic' );
				}

				$this->ad_type_amp->amp_ad_mode = $amp_mode ? $amp_mode : 'automatic';
			}

			switch ( $type ) {
				case 'normal':
					$ad = $this->build_type_normal( $slot_id, $pub_id, $type );
					break;

				default:
					$ad = $this->build_type_maybe_responsive( $slot_id, $pub_id, $type );
					break;
			}
		}

		return apply_filters( 'adcmdr_adsense_ad_html', $ad, $this->ad, $slot_id, $pub_id, $type, $mode );
	}
}
