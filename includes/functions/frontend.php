<?php
/* Abort! */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Frontend functions for use in themes, shortcods, etc.
 */
if ( ! function_exists( 'adcmdr_display_ad' ) ) {
	/**
	 * Display ar return an individual ad.
	 *
	 * @param int   $ad_id The ad post ID.
	 * @param array $args Function arguments.
	 *
	 * @return void|string HTML string if ad is not printing.
	 */
	function adcmdr_display_ad( $ad_id, $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'display'          => true,
				'force_noajax'     => false,
				'force_nocheck'    => false,
				'disable_wrappers' => false,
			),
		);

		$ad_post = \ADCmdr\Query::ad( $ad_id );
		$output  = '';
		if ( $ad_post && is_a( $ad_post, 'WP_Post' ) ) {
			$ad = new \ADCmdr\Ad( $ad_post );
			if ( ! $args['force_noajax'] && $ad->should_ajax() ) {
				$output = $ad->build_ajax_container();
			} else {
				$output = $ad->build_ad(
					array(
						'display'          => false,
						'disable_wrappers' => $args['disable_wrappers'],
						'force_nocheck'    => $args['force_nocheck'],
					)
				);
			}
		}

		if ( ! $args['display'] ) {
			return \ADCmdr\Output::process( $output, false );
		}

		\ADCmdr\Output::process( $output );
	}
}

if ( ! function_exists( 'adcmdr_display_group' ) ) {
	/**
	 * Display ar return a group.
	 *
	 * @param int   $group_id The group term ID.
	 * @param array $args Function arguments.
	 *
	 * @return void|string The group HTML or ajax container HTML if group is not printing.
	 */
	function adcmdr_display_group( $group_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'display'          => true,
				'force_noajax'     => false,
				'force_nocheck'    => false,
				'disable_wrappers' => false,
			),
		);

		$group = new \ADCmdr\Group( $group_id );

		if ( ! $args['force_noajax'] && $group->should_ajax() ) {
			$output = $group->build_ajax_container();
		} else {
			$output = $group->build_group(
				array(
					'display'          => false,
					'disable_wrappers' => $args['disable_wrappers'],
					'force_nocheck'    => $args['force_nocheck'],
				)
			);
		}

		if ( ! $args['display'] ) {
			return \ADCmdr\Output::process( $output, false );
		}

		\ADCmdr\Output::process( $output );
	}
}

if ( ! function_exists( 'adcmdr_the_ad' ) ) {
	/**
	 * Theme function for interfacing with adcmdr_display_ad.
	 * Always displays the ad.
	 *
	 * @param int   $ad_id The ad post ID.
	 * @param array $args Function arguments.
	 *
	 * @return void
	 */
	function adcmdr_the_ad( $ad_id, $args = array() ) {
		$args['display'] = true;

		adcmdr_display_ad( $ad_id, $args );
	}
}

if ( ! function_exists( 'adcmdr_get_ad' ) ) {
	/**
	 * Theme function for interfacing with adcmdr_display_ad.
	 * Always returns the ad.
	 *
	 * @param int   $ad_id The ad post ID.
	 * @param array $args Function arguments.
	 *
	 * @return string HTML string containing the ad.
	 */
	function adcmdr_get_ad( $ad_id, $args = array() ) {
		$args['display'] = false;

		return adcmdr_display_ad( $ad_id, $args );
	}
}

if ( ! function_exists( 'adcmdr_the_group' ) ) {
	/**
	 * Theme function for interfacing with adcmdr_display_group.
	 * Always displays the group.
	 *
	 * @param int   $group_id The group term ID.
	 * @param array $args Function arguments.
	 *
	 * @return void
	 */
	function adcmdr_the_group( $group_id, $args = array() ) {
		$args['display'] = true;

		adcmdr_display_group( $group_id, $args );
	}
}

if ( ! function_exists( 'adcmdr_get_group' ) ) {
	/**
	 * Theme function for interfacing with adcmdr_display_group.
	 * Always returns the group.
	 *
	 * @param int   $group_id The group term ID.
	 * @param array $args Function arguments.
	 *
	 * @return string The group HTML or ajax container HTML.
	 */
	function adcmdr_get_group( $group_id, $args = array() ) {
		$args['display'] = false;

		return adcmdr_display_group( $group_id, $args );
	}
}

/**
 * Shortcode for interfacing with adcmdr_display_ad.
 *
 * @param int $ad_id The ad post ID.
 * @param bool $force_noajax Force group to load without ajax container.
 * @param bool $disable_wrappers Don't include wrappers around ad.
 */
add_shortcode(
	'adcmdr_ad',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'id'               => null,
				'force_noajax'     => false,
				'disable_wrappers' => false,
			),
			$atts
		);

		if ( ! $a['id'] ) {
			return;
		}

		$args = array(
			'display'          => false,
			'force_noajax'     => \ADCmdr\Util::truthy( $a['force_noajax'] ),
			'disable_wrappers' => \ADCmdr\Util::truthy( $a['disable_wrappers'] ),
		);

		return adcmdr_display_ad( absint( $a['id'] ), $args );
	}
);

/**
 * Shortcode for interfacing with adcmdr_display_group.
 *
 * @param int $ad_id The ad post ID.
 * @param bool $force_noajax Force group to load without ajax container.
 * @param bool $disable_wrappers Don't include wrappers around group.
 */
add_shortcode(
	'adcmdr_group',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'id'               => null,
				'force_noajax'     => false,
				'disable_wrappers' => false,
			),
			$atts
		);

		if ( ! $a['id'] ) {
			return;
		}

		$args = array(
			'display'          => false,
			'force_noajax'     => \ADCmdr\Util::truthy( $a['force_noajax'] ),
			'disable_wrappers' => \ADCmdr\Util::truthy( $a['disable_wrappers'] ),
		);

		return adcmdr_display_group( absint( $a['id'] ), $args );
	}
);
