<?php
namespace ADCmdr;

/**
 * Output ads to the front-end.
 */
class Output {

	/**
	 * Prepare and output HTML.
	 *
	 * @param string $output The HTML to output.
	 * @param bool   $display Display the HTML (or return it).
	 * @param bool   $allow_unfiltered Optionally allow unfiltered tags in output. Required to include scripts from ad networks.
	 *
	 * @return string|void
	 */
	public static function process( $output, $display = true, $allow_unfiltered = true ) {

		$output = self::clean( $output );

		if ( ! $display ) {
			return ( $allow_unfiltered ) ? $output : wp_kses_post( $output );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- script tags and other elements from ad networks are required in ads and must be outputted.
		echo ( $allow_unfiltered ) ? $output : wp_kses_post( $output );
	}

	/**
	 * Remove PHP from the output. Optionally remove scripts.
	 *
	 * @param string $output The HTML to output.
	 *
	 * @return string
	 */
	public static function clean( $output ) {
		if ( ! is_string( $output ) ) {
			return '';
		}

		// short-circut any PHP in the output.
		$output = str_ireplace( array( '<?php', '<? ', '?>' ), '', $output );

		return $output;
	}
}
