<?php
namespace ADCmdr;

/**
 * A Placement post's allowed meta keys and related functionality.
 */
class PlacementPostMeta {

	/**
	 * Allowed meta keys and their related details.
	 *
	 * @return array
	 */
	public static function post_meta_keys() {
		return array_merge(
			array(
				'placement_position'         => array(
					'type'       => 'str',
					'restricted' => array_keys( self::placement_positions() ),
				),
				'disable_consent'            => array(
					'type' => 'bool',
				),
				'within_content_position'    => array(
					'type'       => 'int',
					'default'    => 10,
					'restricted' => array( 10, 20, 30, 40, 50, 60, 70, 80, 90 ),
					'required'   => array( 'placement_position' => 'within_content' ),
				),
				'paragraph_number'           => array(
					'type'     => 'int',
					'default'  => 2,
					'required' => array( 'placement_position' => 'after_p_tag' ),
				),
				'post_list_position'         => array(
					'type'     => 'int',
					'default'  => 1,
					'required' => array( 'placement_position' => 'post_list' ),
				),
				'popup_display_when'         => array(
					'type'     => 'str',
					'default'  => 'after_num_seconds',
					'required' => array( 'placement_position' => 'popup' ),
				),
				'popup_after_num_seconds'    => array(
					'type'     => 'int',
					'default'  => 20,
					'required' => array( 'placement_position' => 'popup' ),
				),
				'popup_after_percent_scroll' => array(
					'type'     => 'int',
					'default'  => 20,
					'required' => array( 'placement_position' => 'popup' ),
				),
				'popup_hide_close_btn'       => array(
					'type'     => 'bool',
					'required' => array( 'placement_position' => 'popup' ),
				),
				'popup_auto_close_seconds'   => array(
					'type'     => 'int',
					'default'  => 0,
					'required' => array( 'placement_position' => 'popup' ),
				),
				'popup_overlay_bg'           => array(
					'type'     => 'str',
					'default'  => 'rgba(0, 0, 0, 0.25)',
					'required' => array( 'placement_position' => 'popup' ),
				),
				'popup_position'             => array(
					'type'       => 'str',
					'default'    => 0,
					'required'   => array( 'placement_position' => 'popup' ),
					'restricted' => array_keys( self::allowed_popup_positions() ),
				),
				'disable_wrappers_body'      => array(
					'type'     => 'bool',
					'default'  => 1,
					'required' => array( 'placement_position' => 'body_close_tag' ),
				),
				'force_serverside_body'      => array(
					'type'     => 'bool',
					'default'  => 1,
					'required' => array( 'placement_position' => 'body_close_tag' ),
				),
				'placement_items'            => array(
					'type' => 'str',
				),
				'order'                      => array(
					'type'    => 'int',
					'default' => 1,
				),
			),
			TargetingMeta::post_meta_keys(),
		);
	}

	/**
	 * Placement position key/value array for use in admin and elsewhere.
	 *
	 * @return array
	 */
	public static function placement_positions() {
		return array(
			'before_content' => __( 'Content: Before Content', 'ad-commander' ),
			'after_content'  => __( 'Content: After Content', 'ad-commander' ),
			'within_content' => __( 'Content: Within Content', 'ad-commander' ),
			'after_p_tag'    => __( 'Content: After Paragraph #', 'ad-commander' ),
			// 'above_title'    => __( 'Above Title', 'ad-commander' ),
			'post_list'      => __( 'Post Lists (Blog, Archives, etc)', 'ad-commander' ),
			'popup'          => __( 'Popup Overlay', 'ad-commander' ),
			'head_close_tag' => __( 'HTML: Before </head>', 'ad-commander' ),
			'body_close_tag' => __( 'HTML: Before </body>', 'ad-commander' ),
		);
	}

	/**
	 * Allowed popup positions
	 */
	public static function allowed_popup_positions() {
		return array(
			'left-top'      => __( 'Left Top' ),
			'center-top'    => __( 'Center Top' ),
			'right-top'     => __( 'Right Top' ),
			'left-center'   => __( 'Left Center' ),
			'center-center' => __( 'Center Center' ),
			'right-center'  => __( 'Right Center' ),
			'left-bottom'   => __( 'Left Bottom' ),
			'center-bottom' => __( 'Center Bottom' ),
			'right-bottom'  => __( 'Right Bottom' ),
		);
	}
}
