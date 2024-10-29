<?php
namespace ADCmdr;

/**
 * A Group term's allowed meta keys and related functionality.
 */
class GroupTermMeta {

	/**
	 * Allowed meta keys and their related details.
	 *
	 * @return array
	 */
	public static function tax_group_meta_keys() {
		$modes = array_keys( Group::modes() );

		return array_merge(
			array(
				'mode'               => array(
					'type'       => 'str',
					'restricted' => $modes,
					'default'    => $modes[0],
				),
				'order_method'       => array(
					'type'       => 'str',
					'restricted' => array_keys( self::allowed_order_methods() ),
					'default'    => 'random',
				),
				'grid-cols'          => array(
					'type'     => 'int',
					'default'  => 3,
					'required' => array( 'mode' => 'grid' ),
				),
				'grid-rows'          => array(
					'type'     => 'int',
					'default'  => 1,
					'required' => array( 'mode' => 'grid' ),
				),
				'refresh'            => array(
					'type'     => 'int',
					'default'  => 5,
					'minimum'  => 1,
					'required' => array( 'mode' => 'rotate' ),
				),
				'stop_tracking_i'    => array(
					'type'     => 'int',
					'default'  => 0,
					'required' => array( 'mode' => 'rotate' ),
				),
				'ad_label'           => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
				),
				'ad_order'           => array(
					'type'        => 'ints',
					'ignore_post' => true,
				),
				'ad_weights'         => array(
					'type'     => 'assoc_ints',
					'required' => array( 'order_method' => 'weighted' ),
				),
				'clear_float'        => array(
					'type' => 'bool',
				),
				'float'              => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::float_options() ),
					'default'    => 'no',
				),
				'custom_classes'     => array(
					'type' => 'str',
				),
				'margin_top'         => array(
					'type' => 'int',
				),
				'margin_left'        => array(
					'type' => 'int',
				),
				'margin_right'       => array(
					'type' => 'int',
				),
				'margin_bottom'      => array(
					'type' => 'int',
				),
				'responsive_banners' => array(
					'type'       => 'str',
					'restricted' => array_keys( Util::site_default_options() ),
					'default'    => 'site_default',
				),
				'custom_code_before' => array(
					'type' => 'editor',
				),
				'custom_code_after'  => array(
					'type' => 'editor',
				),
				'disable_consent'    => array(
					'type' => 'bool',
				),
			),
			TargetingMeta::post_meta_keys(),
		);
	}

	/**
	 * A group's count transient.
	 *
	 * @param mixed $group_id The group ID for this transient.
	 *
	 * @return string
	 */
	public static function group_count_transient( $group_id ) {
		return AdCommander::tax_group() . '_count_' . $group_id;
	}

	public static function allowed_order_methods() {
		return array(
			'random'     => 'Random',
			'manual'     => 'Manual',
			'weighted'   => 'Weighted',
			'sequential' => 'Sequential',
		);
	}
}
