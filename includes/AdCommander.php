<?php
namespace ADCmdr;

/**
 * Misc data used throughout this plugin.
 */
class AdCommander {

	/**
	 * The plugin version.
	 *
	 * @return string
	 */
	public static function version() {
		return '1.1.8';
	}

	/**
	 * The plugin title.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Ad Commander', 'ad-commander' );
	}

	/**
	 * The menu title.
	 *
	 * @return string
	 */
	public static function menu_title() {
		return self::title();
	}

	/**
	 * The plugin namespace.
	 *
	 * @return string
	 */
	public static function ns() {
		return 'adcmdr';
	}

	/**
	 * The path to the assets directory.
	 *
	 * @return string
	 */
	public static function assets_path() {
		return ADCMDR_PLUGIN_DIR . 'dist/';
	}

	/**
	 * The URL to the assets directory.
	 *
	 * @return string
	 */
	public static function assets_url() {
		return ADCMDR_PLUGIN_URL . 'dist/';
	}

	/**
	 * The required capability for managing this plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		return 'manage_' . self::ns();
	}

	/**
	 * The namespaced post type for ads.
	 *
	 * @return string
	 */
	public static function posttype_ad() {
		return self::ns() . '_advert';
	}

	/**
	 * The namespaced post type for placements.
	 *
	 * @return string
	 */
	public static function posttype_placement() {
		return self::ns() . '_placement';
	}

	/**
	 * The namespaced taxonomy for groups.
	 *
	 * @return string
	 */
	public static function tax_group() {
		return self::ns() . '_group';
	}

	/**
	 * Creates a URL (with path) to the public plugin site. Adds optional parameters.
	 *
	 * @param string $path The URL path.
	 * @param array  $args The URL parameters.
	 *
	 * @return string
	 */
	public static function public_site_url( $path = '', $args = array() ) {
		$url = sanitize_url( trailingslashit( 'https://wpadcommander.com/' . $path ) );

		if ( $args !== false ) {
			$args = wp_parse_args(
				$args,
				array(
					'utm_source'   => 'wpadmin',
					'utm_medium'   => 'link',
					'utm_campaign' => 'plugin',
				)
			);

			$url = add_query_arg(
				$args,
				$url
			);
		}

		return $url;
	}
}
