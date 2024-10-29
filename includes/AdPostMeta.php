<?php
namespace ADCmdr;

/**
 * An Ad post's allowed meta keys and related functionality.
 */
class AdPostMeta {

	/**
	 * Allowed meta keys and their related details.
	 *
	 * @return array
	 */
	public static function post_meta_keys() {
		return array_merge(
			array(
				'adtype'                        => array(
					'type'       => 'str',
					'restricted' => array_keys( self::ad_types() ),
				),
				'adcontent_text'                => array(
					'type'     => 'editor',
					'required' => array( 'adtype' => 'textcode' ),
				),
				'adcontent_rich'                => array(
					'type'     => 'editor',
					'required' => array( 'adtype' => 'richcontent' ),
				),
				'adsense_adslot_id'             => array(
					'type'     => 'str',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_size_width'            => array(
					'type'     => 'int',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_size_height'           => array(
					'type'     => 'int',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_layout_key'            => array(
					'type'     => 'str',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_ad_pub_id'             => array(
					'type'     => 'str',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_ad_format'             => array(
					'type'       => 'str',
					'restricted' => array_keys( AdSense::ad_formats() ),
					'required'   => array( 'adtype' => 'adsense' ),
				),
				'adsense_ad_mode'               => array(
					'type'       => 'str',
					'restricted' => AdSense::ad_modes(),
					'required'   => array( 'adtype' => 'adsense' ),
				),
				'adsense_full_width_responsive' => array(
					'type'       => 'str',
					'restricted' => array( 'true', 'false', 'default' ),
					'default'    => 'true',
					'required'   => array( 'adtype' => 'adsense' ),
				),
				'adsense_ad_code'               => array(
					'type'     => 'editor',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_multiplex_uitype'      => array(
					'type'       => 'str',
					'restricted' => array_keys( AdSense::multiplex_ui_types() ),
					'required'   => array( 'adtype' => 'adsense' ),
				),
				'adsense_multiplex_cols'        => array(
					'type'     => 'int',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_multiplex_rows'        => array(
					'type'     => 'int',
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_amp_ad_mode'           => array(
					'type'       => 'str',
					'restricted' => array_keys( AdSense::amp_modes() ),
					'default'    => 'site_default',
					'required'   => array( 'adtype' => 'adsense' ),
				),
				'adsense_amp_dynamic_width'     => array(
					'type'     => 'int',
					'default'  => 300,
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_amp_dynamic_height'    => array(
					'type'     => 'int',
					'default'  => 250,
					'required' => array( 'adtype' => 'adsense' ),
				),
				'adsense_amp_fixed_height'      => array(
					'type'     => 'int',
					'default'  => 250,
					'required' => array( 'adtype' => 'adsense' ),
				),
				'bannerurl'                     => array(
					'type'     => 'url',
					'required' => array( 'adtype' => 'bannerad' ),
				),
				'newwindow'                     => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
					'required'   => array( 'adtype' => 'bannerad' ),
				),
				'noopener'                      => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
					'required'   => array( 'adtype' => 'bannerad' ),
				),
				'noreferrer'                    => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
					'required'   => array( 'adtype' => 'bannerad' ),
				),
				'nofollow'                      => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
					'required'   => array( 'adtype' => 'bannerad' ),
				),
				'sponsored'                     => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
					'required'   => array( 'adtype' => 'bannerad' ),
				),
				'display_width'                 => array(
					'type'     => 'int',
					'required' => array( 'adtype' => 'bannerad' ),
				),
				'display_height'                => array(
					'type'     => 'int',
					'required' => array( 'adtype' => 'bannerad' ),
				),
				'expire_date'                   => array(
					'type' => 'date',
				),
				'expire_hour'                   => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::hours_formatted() ),
				),
				'expire_min'                    => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::mins_formatted() ),
				),
				'expire_ampm'                   => array(
					'type'       => 'str',
					'restricted' => Util::ampm_formatted( false ),
				),
				'expire_gmt'                    => array(
					'type'        => 'timestamp',
					'ignore_post' => true,
				),
				'expire_clicks'                 => array(
					'type' => 'int',
				),
				'expire_impressions'            => array(
					'type' => 'int',
				),
				'ad_label'                      => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
				),
				'responsive_banners'            => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
				),
				'clear_float'                   => array(
					'type' => 'bool',
				),
				'float'                         => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::float_options() ),
					'default'    => 'no',
				),
				'custom_classes'                => array(
					'type' => 'str',
				),
				'margin_top'                    => array(
					'type' => 'int',
				),
				'margin_left'                   => array(
					'type' => 'int',
				),
				'margin_right'                  => array(
					'type' => 'int',
				),
				'margin_bottom'                 => array(
					'type' => 'int',
				),
				'custom_code_before'            => array(
					'type' => 'editor',
				),
				'custom_code_after'             => array(
					'type' => 'editor',
				),
				'disable_consent'               => array(
					'type' => 'bool',
				),
				'donottrack_i'                  => array(
					'type' => 'bool',
				),
				'donottrack_c'                  => array(
					'type' => 'bool',
				),
			),
			TargetingMeta::post_meta_keys()
		);
	}

	/**
	 * Ad types key/value array for use in admin and elsewhere.
	 *
	 * @return array
	 */
	public static function ad_types() {
		return array(
			'bannerad'    => __( 'Image/Banner Ad', 'ad-commander' ),
			'adsense'     => __( 'AdSense', 'ad-commander' ),
			'textcode'    => __( 'Text or Code (Ad Networks)', 'ad-commander' ),
			'richcontent' => __( 'Rich Content', 'ad-commander' ),
		);
	}
}
