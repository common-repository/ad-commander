<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOUtilities;

/**
 * Misc helper classes used throughout this plugin.
 * Also bridges some vendor frameworks, so we don't have to interface with those in other classes.
 */
class Util {

	/**
	 * Get the version of the plugin from the database.
	 *
	 * @param string $key The option key for the plugin version.
	 *
	 * @return float
	 */
	public static function get_dbversion( $key = 'version' ) {
		$version = Options::instance()->get( $key );

		if ( ! $version || is_array( $version ) ) {
			return '';
		}

		return $version;
	}

	/**
	 * An array for use with options that have "Site Default" as an option.
	 *
	 * @return array
	 */
	public static function site_default_options() {
		return array(
			'site_default' => __( 'Site Default', 'ad-commander' ),
			'yes'          => __( 'Yes', 'ad-commander' ),
			'no'           => __( 'No', 'ad-commander' ),
		);
	}

	/**
	 * An array for use when selecting a float.
	 *
	 * @return array
	 */
	public static function float_options() {
		return array(
			'no' => __( 'None', 'ad-commander' ),
			'l'  => __( 'Left', 'ad-commander' ),
			'r'  => __( 'Right', 'ad-commander' ),
		);
	}

	/**
	 * Converts a string into the correct bool.
	 * Interfaces with WOUtilities::truthy
	 *
	 * @param mixed $value Any string or bool.
	 *
	 * @return bool
	 */
	public static function truthy( $value ) {
		return WOUtilities::truthy( $value );
	}

	/**
	 * If a variable is not an array, bool, or WP_Error, make it an array. Interfaces with WOUtilities.
	 *
	 * @param mixed $arr Variable to check and convert to array.
	 * @param bool  $force Force an array return in some cases.
	 *
	 * @return mixed
	 */
	public static function arrayify( $arr, $force = false ) {
		return WOUtilities::arrayify( $arr, $force );
	}

	/**
	 * Create a prefixed string for use throughout plugin to avoid conflicts.
	 *
	 * @param string $str The string to prefix.
	 * @param string $sep The seperator.
	 * @param string $ns The prefix.
	 *
	 * @return string
	 */
	public static function ns( $str, $sep = '-', $ns = null ) {
		if ( ! $ns ) {
			$ns = AdCommander::ns();
		}

		return $ns . $sep . $str;
	}

	/**
	 * Creates a prefix for use on CSS classes, IDs, etc.
	 *
	 * @return string
	 */
	public static function prefix() {
		$prefix = Options::instance()->get( 'prefix', 'general', false, AdCommander::ns() );

		if ( ! $prefix || $prefix === '' ) {
			$prefix = AdCommander::ns();
		}

		return sanitize_key( strtolower( $prefix ) );
	}

	/**
	 * Applies the specified prefix to a string. Works similar to self::ns() but uses the admin option, if one exists.
	 *
	 * @param string $str The string to prefix.
	 * @param string $sep The seperator after the prefix.
	 *
	 * @return string
	 */
	public static function prefixed( $str, $sep = '-' ) {
		return sanitize_text_field( self::prefix() . $sep . $str );
	}

	/**
	 * If a meta value is set to 'site_default', find the site default for that option.
	 * Otherwise, return the bool of the meta value.
	 *
	 * @param mixed $meta_value The value to check.
	 * @param mixed $option_key The site_default option_key.
	 * @param mixed $option_group The site_default option_group.
	 * @param null  $array_value Optionally, look for an array value in the option group instead of using truthy.
	 *
	 * @return bool
	 */
	public static function truthy_or_site_default( $meta_value, $option_key, $option_group, $array_value = null ) {
		if ( $meta_value === 'site_default' ) {
			$option_value = Options::instance()->get( $option_key, $option_group );

			if ( $array_value !== null ) {
				return is_array( $option_value ) && in_array( $array_value, $option_value );
			} else {
				return self::truthy( $option_value );
			}
		}

		return self::truthy( $meta_value );
	}

	/**
	 * Alternative to WP_Query's post_status = 'any'.
	 * This includes more post_statuses.
	 *
	 * @param array $exclude Remove some of the statuses.
	 *
	 * @return array
	 */
	public static function any_post_status( $exclude = array() ) {
		if ( $exclude && ! is_array( $exclude ) ) {
			$exclude = array( $exclude );
		}

		return array_diff( array( 'publish', 'pending', 'draft', 'future', 'private', 'trash' ), $exclude );
	}

	/**
	 * Get and format post types for use in option groups.
	 *
	 * @return array
	 */
	public static function get_post_types() {
		$formatted_post_types = array();
		$post_types           = get_post_types( array( 'public' => 1 ), 'object' );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$formatted_post_types[ $post_type->name ] = $post_type->label;
			}
		}

		/**
		 * Filter: adcmdr_available_post_types
		 * Add or remove post types from the available post types array.
		 */
		return apply_filters( 'adcmdr_available_post_types', $formatted_post_types );
	}

	/**
	 * Returns a timestamp for the start of the hour.
	 *
	 * @param string $datetime String for use in DateTime.
	 *
	 * @return int
	 */
	public static function start_of_hour_timestamp( $datetime = 'now' ) {
		$date = new \DateTime( $datetime );
		return $date->setTime( $date->format( 'G' ), 0 )->getTimestamp();
	}

	/**
	 * Creates a DateTime instance using the current WordPress timezone.
	 *
	 * @param mixed $datetime_string The string to use while creating the DateTime instance.
	 *
	 * @return \DateTime
	 */
	public static function datetime_wp_timezone( $datetime_string ) {
		return new \DateTime( $datetime_string, wp_timezone() );
	}

	/**
	 * Returns an array of days of the week.
	 *
	 * @return array
	 */
	public static function days_of_week() {
		return array( __( 'Sunday' ), __( 'Monday' ), __( 'Tuesday' ), __( 'Wednesday' ), __( 'Thursday' ), __( 'Friday' ), __( 'Saturday' ) );
	}

	/**
	 * Get the first day of the week using the WordPress option.
	 *
	 * @return string
	 */
	public static function first_day_of_the_week() {
		$start_of_week = intval( get_option( 'start_of_week' ) );
		return self::days_of_week()[ $start_of_week ];
	}

	/**
	 * Calculate the Click-Thur-Ratio
	 *
	 * @param int $clicks Number of clicks.
	 * @param int $impressions Numberof impressions.
	 *
	 * @return float
	 */
	public static function ctr( $clicks, $impressions ) {
		if ( ! class_exists( 'NumberFormatter' ) ) {
			return null;
		}

		if ( $impressions <= 0 || $clicks <= 0 ) {
			$value = 0;
		} else {
			$value = floatval( $clicks / $impressions );
		}

		$formatter = new \NumberFormatter( get_locale(), \NumberFormatter::PERCENT );
		numfmt_set_attribute( $formatter, \NumberFormatter::MIN_FRACTION_DIGITS, 2 );
		numfmt_set_attribute( $formatter, \NumberFormatter::MAX_FRACTION_DIGITS, 2 );

		return $formatter->format( $value );
	}

	/**
	 * Create HTML clearfix.
	 *
	 * @param bool $display Whether or not to display the HTML.
	 *
	 * @return string
	 */
	public static function clear_float( $display = false ) {
		$html = '<br class="' . esc_attr( self::prefixed( 'clr' ) ) . '" />';

		$allowed_html = array( 'br' => array( 'class' => array() ) );

		if ( ! $display ) {
			return wp_kses( $html, $allowed_html );
		}

		echo wp_kses( $html, $allowed_html );
	}

	/**
	 * Create an array of inline styles
	 *
	 * @param array  $styles An array of CSS properties and values.
	 * @param bool   $number If the value is a number or not.
	 * @param string $unit Unit to append to the value.
	 * @param bool   $important Optionally add an important tag.
	 * @param bool   $include_style_attr Optionally include the style= tag when returned.
	 *
	 * @return string
	 */
	public static function inline_styles( $styles, $number = true, $unit = 'px', $important = true, $include_style_attr = true ) {
		$inline = array();

		foreach ( $styles as $key => $value ) {
			$value = ( $number ) ? intval( $value ) : wp_strip_all_tags( $value );

			if ( $number && $value <= 0 ) {
				continue;
			}

			$style = $key . ':' . $value . $unit;

			if ( $important ) {
				$style .= '!important';
			}

			$inline[] = $style . ';';
		}

		return self::make_inline_style( $inline, $include_style_attr );
	}

	/**
	 * Converts an array of inline styles into a string. Optionally adds the style= attribute.
	 *
	 * @param array $inline Array of inline styles.
	 * @param bool  $include_style_attr Optionally include style attribute in return value.
	 *
	 * @return string
	 */
	public static function make_inline_style( $inline = array(), $include_style_attr = true ) {
		if ( ! empty( $inline ) ) {
			$inline = implode( '', $inline );

			if ( $include_style_attr ) {
				$inline = ' style="' . esc_attr( $inline ) . '"';
			}

			return $inline;
		}

		return '';
	}

	/**
	 * Converts an object or array of object into an array.
	 *
	 * @param array|object $objects The objects to convert.
	 * @param string       $key1 The key from the object that will be used for the index.
	 * @param string       $key2 The key from the object that will be used for the value.
	 *
	 * @return array
	 */
	public static function object_to_array( $objects, $key1 = 'term_id', $key2 = 'name' ) {
		$values = array();

		if ( ! empty( $objects ) && ! is_wp_error( $objects ) ) {

			$objects = self::arrayify( $objects );

			foreach ( $objects as $object ) {
				$values[ $object->$key1 ] = $object->$key2;
			}
		}

		return $values;
	}

	/**
	 * This is a customized version of wp_list_authors. Simplified and modified in some areas.
	 * Returns authors as a user_id => display_name array.
	 *
	 * @param string $args Arguments.
	 *
	 * @return array
	 */
	public static function get_authors( $args = '' ) {
		global $wpdb;

		$defaults = array(
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => '',
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			'exclude'    => '',
			'include'    => '',
			'fields'     => array( 'id', 'display_name' ),
			'hide_empty' => true,
		);

		$parsed_args = wp_parse_args( $args, $defaults );

		$query_args           = wp_array_slice_assoc( $parsed_args, array( 'orderby', 'order', 'number', 'exclude', 'include' ) );
		$query_args['fields'] = $parsed_args['fields'];

		$users = get_users( $query_args );

		/**
		 * Filters whether to short-circuit performing the query for author post counts.
		 *
		 * @since 6.1.0
		 *
		 * @param int[]|false $post_counts Array of post counts, keyed by author ID.
		 * @param array       $parsed_args The arguments passed to wp_list_authors() combined with the defaults.
		 */
		$post_counts = apply_filters( 'pre_wp_list_authors_post_counts_query', false, $parsed_args );

		if ( $parsed_args['hide_empty'] && ! is_array( $post_counts ) ) {
			$post_counts = array();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- TODO: Test implications of caching this query in future versions.
			$post_counts_query = $wpdb->get_results( "SELECT DISTINCT post_author, COUNT(ID) AS count FROM $wpdb->posts GROUP BY post_author" );

			foreach ( (array) $post_counts_query as $row ) {
				$post_counts[ $row->post_author ] = $row->count;
			}
		}

		$processed_users = array();

		foreach ( $users as $user ) {
			$posts = isset( $post_counts[ $user->ID ] ) ? $post_counts[ $user->ID ] : 0;

			if ( ! $posts && $parsed_args['hide_empty'] ) {
				continue;
			}

			$processed_users[] = $user;
		}

		return $processed_users;
	}


	/**
	 * Format hours using digits_formatted.
	 *
	 * @param bool $keyvaluepair Whether to return a keyvaluepair.
	 *
	 * @return array
	 */
	public static function hours_formatted( $keyvaluepair = true ) {
		return self::digits_formatted( array_merge( array( 12 ), range( 1, 11 ) ), $keyvaluepair );
	}

	/**
	 * Format minutes using digits_formatted.
	 *
	 * @param bool $keyvaluepair Whether to return a keyvaluepair.
	 *
	 * @return array
	 */
	public static function mins_formatted( $keyvaluepair = true ) {
		return self::digits_formatted( range( 0, 59 ), $keyvaluepair );
	}

	/**
	 * Format digits to include a leading 0 when necessary.
	 *
	 * @param array $range An array of numbers to format.
	 * @param bool  $keyvaluepair Whether to return a keyvaluepair.
	 *
	 * @return array
	 */
	public static function digits_formatted( $range, $keyvaluepair = true ) {
		$digits = array();
		foreach ( $range as $number ) {
			if ( strlen( $number ) === 1 ) {
				$number = '0' . $number;
			}

			if ( $keyvaluepair ) {
				$digits[ $number ] = $number;
			} else {
				$digits[] = $number;
			}
		}

		return $digits;
	}

	/**
	 * Format am/pm
	 *
	 * @param bool $keyvaluepair Whether to return a keyvaluepair.
	 *
	 * @return array
	 */
	public static function ampm_formatted( $keyvaluepair = true ) {
		if ( $keyvaluepair ) {
			return array(
				'am' => __( 'am', 'ad-commander' ),
				'pm' => __( 'pm', 'ad-commander' ),
			);
		}

		return array( 'am', 'pm' );
	}

	/**
	 * Get the label for a post status.
	 *
	 * @param string $post_status The post status to retrieve.
	 * @param mixed  $only_return Only return certain statuses.
	 *
	 * @return string
	 */
	public static function post_status_label( $post_status, $only_return = false ) {
		$post_status = strtolower( trim( $post_status ) );

		if ( $only_return !== false ) {
			$only_return = self::arrayify( $only_return );
			if ( ! in_array( $post_status, $only_return ) ) {
				return '';
			}
		}

		/**
		 * Use the WordPress translated post status strings, add one for Trash, and overwrite the label for 'Future' status.
		 */
		$available_statuses           = array_merge( get_post_statuses(), get_page_statuses(), array( 'trash' => __( 'Trash', 'ad-commander' ) ) );
		$available_statuses['future'] = __( 'Scheduled', 'ad-commander' );

		if ( isset( $available_statuses[ $post_status ] ) ) {
			return $available_statuses[ $post_status ];
		}

		return '';
	}

	/**
	 * Detect if a known caching plugin or service is in use.
	 */
	public static function cache_detected() {
		return defined( 'WPSC_VERSION' ) // WP Super Cache
				|| defined( 'W3TC' ) // W3 Total Cache
				|| defined( 'WPFC_MAIN_PATH' ) // WP Fastest Cache
				|| function_exists( 'BorlabsCacheHelper' ) // Borlabs
				|| ( function_exists( 'is_wpe' ) && is_wpe() ) // WP Engine
				|| ( defined( 'WP_CACHE' ) && WP_CACHE === true ); // WP_CACHE constant added by plugins
	}

	/**
	 * The default render method.
	 *
	 * @return string
	 */
	public function default_render_method() {
		if ( self::cache_detected() ) {
			return 'smart';
		}

		return 'serverside';
	}

	/**
	 * Returns render method.
	 */
	public static function render_method() {
		return ProBridge::instance()->is_pro_loaded() ? Options::instance()->get( 'render_method', 'general' ) : 'serverside';
	}

	/**
	 * Get the current URL.
	 *
	 * @return string
	 */
	public static function current_url() {
		$url         = ( isset( $_SERVER['HTTPS'] ) && sanitize_text_field( wp_unslash( $_SERVER['HTTPS'] ) ) == 'on' ? 'https' : 'http' ) . '://';
		$host        = ( isset( $_SERVER['HTTP_HOST'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$request_uri = ( isset( $_SERVER['REQUEST_URI'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		return sanitize_url( $url . $host . $request_uri );
	}

	/**
	 * Add javascript data variable for an euqneue script.
	 *
	 * @param string $handle The script handle.
	 * @param array  $data The data to add.
	 * @param string $jsvar The javascript object.
	 * @param string $position Before or after the enqueued script.
	 *
	 * @return void
	 */
	public static function enqueue_script_data( $handle, $data, $jsvar = null, $position = 'before' ) {
		$data = wp_json_encode( $data );

		if ( $data ) {
			if ( ! $jsvar ) {
				$jsvar = str_replace( '-', '_', $handle );
			}

			$jsvar = trim( wp_strip_all_tags( $jsvar ) );

			$script = 'var ' . $jsvar . ' = ' . $data . ';';
			wp_add_inline_script( $handle, $script, $position );
		}
	}

	/**
	 * Determine if a string is a timestamp.
	 *
	 * @param string $str The string to check.
	 *
	 * @return bool
	 */
	public static function is_valid_timestamp( $str ) {
		return ( (string) (int) $str === $str ) && ( $str <= PHP_INT_MAX ) && ( $str >= ~PHP_INT_MAX );
	}

	/**
	 * Escape dashicon HTML.
	 *
	 * @param string $html The HTML to escape.
	 * @param bool   $display Whether to echo or return.
	 *
	 * @return void|string
	 */
	public static function wp_kses_icon( $html, $display = true ) {
		if ( ! $display ) {
			return wp_kses(
				$html,
				array(
					'i' => array(
						'class' => array(),
						'title' => array(),
					),
				)
			);
		}

		echo wp_kses(
			$html,
			array(
				'i' => array(
					'class' => array(),
					'title' => array(),
				),
			)
		);
	}

	/**
	 * Determine if an ad is valid based on its ad type and required meta.
	 *
	 * @param string      $ad_type The type of ad.
	 * @param array|bool  $meta The current ad's meta.
	 * @param WOMeta}bool $wo_meta An instance of WOMeta.
	 * @param int|bool    $ad_id The current ad post ID.
	 */
	public static function has_valid_ad( $ad_type, $meta, $wo_meta, $ad_id ) {
		if ( ! $ad_type ) {
			return false;
		}

		if ( $ad_type === 'bannerad' ) {
			return has_post_thumbnail( $ad_id );
		} elseif ( $ad_type === 'textcode' ) {
			$content = $wo_meta->get_value( $meta, 'adcontent_text' );
			return $content !== null && $content !== '';
		} elseif ( $ad_type === 'richcontent' ) {
			$content = $wo_meta->get_value( $meta, 'adcontent_rich' );
			return $content !== null && $content !== '';
		} elseif ( $ad_type === 'adsense' ) {
			return AdSense::instance()->has_valid_ad( $meta );
		}
	}

	/**
	 * Mark array keys as disabled.
	 *
	 * @param array $options An array of options.
	 *
	 * @return array
	 */
	public static function disable_options( $options ) {
		$disabled = array();

		foreach ( $options as $key => $text ) {
			$disabled[ 'disabled:' . $key ] = $text;
		}

		return $disabled;
	}
}
