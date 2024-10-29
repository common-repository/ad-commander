<?php
/* Abort! */
if ( ! defined( 'WPINC' ) ) {
	die;
}

$adcmdr_id = isset( $attributes['adcmdrId'] ) ? $attributes['adcmdrId'] : null;

if ( $adcmdr_id ) {
	$adcmdr_id = sanitize_key( $adcmdr_id );
	$prefix    = substr( $adcmdr_id, 0, 2 );
	$adcmdr_id = absint( substr( $adcmdr_id, 2 ) );

	if ( $adcmdr_id > 0 ) {
		if ( $prefix === 'a_' ) {
			adcmdr_display_ad( $adcmdr_id );
		} elseif ( $prefix === 'g_' ) {
			adcmdr_display_group( $adcmdr_id );
		}
	}
}
