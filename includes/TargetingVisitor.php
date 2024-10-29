<?php
namespace ADCmdr;

/**
 * Class for determining if an ad or group should display in the current visitor scenario.
 */
class TargetingVisitor extends Targeting {

	/**
	 * Determine if ads are disabled for a user role.
	 *
	 * @return bool
	 */
	public static function ads_disabled_user_role() {
		$disabled_roles = Options::instance()->get( 'disable_user_roles', 'general' );

		if ( ! $disabled_roles || empty( $disabled_roles ) ) {
			return false;
		}

		$current_user = wp_get_current_user();

		if ( $current_user && isset( $current_user->roles ) && ! empty( $current_user->roles ) ) {
			$roles = $current_user->roles;

			if ( ! is_array( $roles ) ) {
				$roles = array( $roles );
			}

			foreach ( $disabled_roles as $disabled_role ) {
				if ( in_array( $disabled_role, $roles ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks a visitor condition for pass/fail.
	 *
	 * @param mixed  $condition The condition array.
	 * @param string $object_type The type of object to check (ad or placement).
	 * @param int    $object_id The ID of the object to check.
	 *
	 * @return bool
	 */
	public function condition_check( $condition, $object_type, $object_id ) {
		$pass_values = $condition['values'];

		/**
		 * Setup the check and pass values for each target.
		*/
		$pro_bridge = ProBridge::instance();

		if ( in_array( $condition['target'], $pro_bridge->pro_visitor_conditions() ) && $pro_bridge->is_pro_loaded() ) {
				$check_and_pass_values = apply_filters( 'adcmdr_pro_visitor_condition_check', $condition, $object_type, $object_id );
				$check_value           = $check_and_pass_values['check_value'];
				$pass_values           = $check_and_pass_values['pass_values'];
		} else {
			switch ( $condition['target'] ) {
				case 'logged_in':
					$check_value = is_user_logged_in();
					$pass_values = true;
					break;

				default:
					$check_value = true;
					$pass_values = true;
			}
		}

		/**
		 * Check for a passfail result, or return true if we don't have a proper check value.
		 */
		return isset( $check_value ) ? self::passfail_condition( $check_value, $pass_values, $condition['condition'] ) : true;
	}

	/**
	 * Get the type of value expected for a target.
	 *
	 * @param string $target The current target.
	 *
	 * @return string
	 */
	public static function value_type( $target ) {
		switch ( $target ) {
			case 'geolocation':
				return array(
					'country' => 'select',
					'region'  => 'text',
					'city'    => 'text',
				);
				break;

			case 'geolocation_radius':
				return array(
					'distance' => 'number',
					'units'    => 'select',
					'lat'      => 'number',
					'lng'      => 'number',
				);
				break;

			case 'logged_in':
			case 'new_visitor':
				return 'words';
				break;

			case 'browser_language':
				return 'select';
				break;

			case 'browser_user_agent':
			case 'session_referrer_url':
				return 'text';
				break;

			case 'site_impressions':
			case 'browser_width':
			case 'max_impressions':
			case 'max_clicks':
				return 'number';
				break;

			// TODO: Switch this to an autocomplete in the future.
			case 'user_cap':
				return 'checkgroup';
				break;

			default:
				return 'checkgroup';
			break;
		}
	}

	/**
	 * Get an array of values for a target.
	 *
	 * @param string $target The current target.
	 *
	 * @return mixed
	 */
	public static function values( $target ) {
		switch ( $target ) {
			case 'geolocation_country':
				return self::countries_continents_for_targeting();
				break;

			case 'geolocation_radius_units':
				return array(
					'mi' => __( 'miles', 'ad-commander' ),
					'km' => __( 'kilometers', 'ad-commander' ),
				);
				break;

			case 'browser_language':
				return self::languages_for_targeting();
				break;

			case 'logged_in':
			case 'new_visitor':
				return __( 'true', 'ad-commander' );
				break;

			case 'user_role':
				return self::parse_roles_to_values();
				break;

			case 'user_cap':
				return self::parse_caps_to_values();
				break;

			default:
				return '';
			break;
		}
	}

	/**
	 * Labels displayed next to a value type.
	 *
	 * @param mixed $target The target key.
	 *
	 * @return array
	 */
	public static function value_type_labels( $target ) {
		switch ( $target ) {
			case 'geolocation':
				return array(
					'region' => __( 'OR State/Province/Region:', 'ad-commander' ),
					'city'   => __( 'OR City:', 'ad-commander' ),
				);
				break;

			case 'geolocation_radius':
				return array(
					'lat' => __( 'from latitude:', 'ad-commander' ),
					'lng' => __( 'longitude:', 'ad-commander' ),
				);
				break;

			default:
				return array();
			break;
		}
	}

	/**
	 * Arguments for inputs.
	 *
	 * @param string $target The target to get args for.
	 *
	 * @return array
	 */
	public static function args( $target ) {
		switch ( $target ) {
			case 'geolocation_radius':
				return array(
					'lat' => array( 'step' => 'any' ),
					'lng' => array( 'step' => 'any' ),
				);
				break;

			default:
				return array();
			break;
		}
	}

	/**
	 * Parse useroles to a key/value array.
	 *
	 * @return array
	 */
	public static function parse_roles_to_values() {
		global $wp_roles;

		$roles = array();

		if ( $wp_roles && property_exists( $wp_roles, 'roles' ) ) {
			foreach ( $wp_roles->roles as $key => $role ) {
				$roles[ $key ] = $role['name'];
			}
		}

		return apply_filters( 'adcmdr_targeting_user_roles', $roles );
	}

	/**
	 * Parse capabilities to a key/value array.
	 *
	 * @return array
	 */
	public static function parse_caps_to_values() {
		$capabilities = array();
		$users        = get_users();

		if ( empty( $users ) ) {
			return $capabilities;
		}

		foreach ( $users as $user ) {
			if ( empty( $user->allcaps ) ) {
				continue;
			}

			$caps = array_keys( $user->allcaps );
			foreach ( $caps as $c ) {
				$capabilities[ $c ] = $c;
			}
		}

		asort( $capabilities );

		return apply_filters( 'adcmdr_targeting_capabilities', $capabilities );
	}

	/**
	 * Countries and continents for admin targeting options.
	 *
	 * This is a Pro feature, but we don't want someone to lose their settings if they temporarily disable Pro, then edit an Ad, so the countries are included here.
	 *
	 * @return array
	 */
	public static function countries_continents_for_targeting() {
		$countries_continents = array();

		$countries_continents = array_merge(
			array( '' => __( 'Select a location', 'ad-commander' ) ),
			Geo::frequent_countries(),
			array( 'woadmin_divider:continents' => '' ),
			Geo::continents(),
			array( 'woadmin_divider:countries' => '' ),
			Geo::countries()
		);

		return apply_filters( 'adcmdr_targeting_countries_continents', $countries_continents );
	}

	/**
	 * Parse list of languages to a key/value array.
	 *
	 * @return array
	 */
	public static function languages_for_targeting() {
		$languages = array();

		foreach ( self::languages() as $key => $lang ) {
			$languages[ $key ] = $lang . ' [' . $key . ']';
		}

		return $languages;
	}

	/**
	 * A list of all languages.
	 *
	 * @return array
	 */
	public static function languages() {
		$langs = array(
			'af'    => __( 'Afrikaans', 'ad-commander' ),
			'ar'    => __( 'Arabic', 'ad-commander' ),
			'ar-ae' => __( 'Arabic - UAE', 'ad-commander' ),
			'ar-bh' => __( 'Arabic - Bahrain', 'ad-commander' ),
			'ar-dz' => __( 'Arabic - Algeria', 'ad-commander' ),
			'ar-eg' => __( 'Arabic - Egypt', 'ad-commander' ),
			'ar-iq' => __( 'Arabic - Iraq', 'ad-commander' ),
			'ar-jo' => __( 'Arabic - Jordan', 'ad-commander' ),
			'ar-kw' => __( 'Arabic - Kuwait', 'ad-commander' ),
			'ar-lb' => __( 'Arabic - Lebanon', 'ad-commander' ),
			'ar-ly' => __( 'Arabic - Libya', 'ad-commander' ),
			'ar-ma' => __( 'Arabic - Morocco', 'ad-commander' ),
			'ar-om' => __( 'Arabic - Oman', 'ad-commander' ),
			'ar-qa' => __( 'Arabic - Qatar', 'ad-commander' ),
			'ar-sa' => __( 'Arabic - Saudi Arabia', 'ad-commander' ),
			'ar-sy' => __( 'Arabic - Syria', 'ad-commander' ),
			'ar-tn' => __( 'Arabic - Tunisia', 'ad-commander' ),
			'ar-ye' => __( 'Arabic - Yemen', 'ad-commander' ),
			'ar'    => __( 'Aragonese', 'ad-commander' ),
			'as'    => __( 'Assamese', 'ad-commander' ),
			'ast'   => __( 'Asturian', 'ad-commander' ),
			'az'    => __( 'Azerbaijani', 'ad-commander' ),
			'be'    => __( 'Belarusian', 'ad-commander' ),
			'bg'    => __( 'Bulgarian', 'ad-commander' ),
			'bg'    => __( 'Bulgarian', 'ad-commander' ),
			'bn'    => __( 'Bengali', 'ad-commander' ),
			'br'    => __( 'Breton', 'ad-commander' ),
			'bs'    => __( 'Bosnian', 'ad-commander' ),
			'ca'    => __( 'Catalan', 'ad-commander' ),
			'ce'    => __( 'Chechen', 'ad-commander' ),
			'ch'    => __( 'Chamorro', 'ad-commander' ),
			'co'    => __( 'Corsican', 'ad-commander' ),
			'cr'    => __( 'Cree', 'ad-commander' ),
			'cs'    => __( 'Czech', 'ad-commander' ),
			'cv'    => __( 'Chuvash', 'ad-commander' ),
			'cy'    => __( 'Welsh', 'ad-commander' ),
			'da'    => __( 'Danish', 'ad-commander' ),
			'de'    => __( 'German', 'ad-commander' ),
			'de-at' => __( 'German - Austria', 'ad-commander' ),
			'de-ch' => __( 'German - Switzerland', 'ad-commander' ),
			'de-de' => __( 'German - Germany', 'ad-commander' ),
			'de-li' => __( 'German - Liechtenstein', 'ad-commander' ),
			'de-lu' => __( 'German - Luxembourg', 'ad-commander' ),
			'el'    => __( 'Greek', 'ad-commander' ),
			'en'    => __( 'English', 'ad-commander' ),
			'en-au' => __( 'English - Australia', 'ad-commander' ),
			'en-bz' => __( 'English - Belize', 'ad-commander' ),
			'en-ca' => __( 'English - Canada', 'ad-commander' ),
			'en-gb' => __( 'English - United Kingdom', 'ad-commander' ),
			'en-ie' => __( 'English - Ireland', 'ad-commander' ),
			'en-jm' => __( 'English - Jamaica', 'ad-commander' ),
			'en-nz' => __( 'English - New Zealand', 'ad-commander' ),
			'en-ph' => __( 'English - Philippines', 'ad-commander' ),
			'en-tt' => __( 'English - Trinidad & Tobago', 'ad-commander' ),
			'en-us' => __( 'English - United States', 'ad-commander' ),
			'en-za' => __( 'English - South Africa', 'ad-commander' ),
			'en-zw' => __( 'English - Zimbabwe', 'ad-commander' ),
			'eo'    => __( 'Esperanto', 'ad-commander' ),
			'es'    => __( 'Spanish', 'ad-commander' ),
			'es-ar' => __( 'Spanish - Argentina', 'ad-commander' ),
			'es-bo' => __( 'Spanish - Bolivia', 'ad-commander' ),
			'es-cl' => __( 'Spanish - Chile', 'ad-commander' ),
			'es-co' => __( 'Spanish - Colombia', 'ad-commander' ),
			'es-cr' => __( 'Spanish - Costa Rica', 'ad-commander' ),
			'es-do' => __( 'Spanish - Dominican Republic', 'ad-commander' ),
			'es-ec' => __( 'Spanish - Ecuador', 'ad-commander' ),
			'es-es' => __( 'Spanish - Spain', 'ad-commander' ),
			'es-gt' => __( 'Spanish - Guatemala', 'ad-commander' ),
			'es-hn' => __( 'Spanish - Honduras', 'ad-commander' ),
			'es-mx' => __( 'Spanish - Mexico', 'ad-commander' ),
			'es-ni' => __( 'Spanish - Nicaragua', 'ad-commander' ),
			'es-pa' => __( 'Spanish - Panama', 'ad-commander' ),
			'es-pe' => __( 'Spanish - Peru', 'ad-commander' ),
			'es-pr' => __( 'Spanish - Puerto Rico', 'ad-commander' ),
			'es-py' => __( 'Spanish - Paraguay', 'ad-commander' ),
			'es-sv' => __( 'Spanish - El Salvador', 'ad-commander' ),
			'es-uy' => __( 'Spanish - Uruguay', 'ad-commander' ),
			'es-ve' => __( 'Spanish - Venezuela', 'ad-commander' ),
			'et'    => __( 'Estonian', 'ad-commander' ),
			'eu'    => __( 'Basque', 'ad-commander' ),
			'fa-ir' => __( 'Persian - Iran', 'ad-commander' ),
			'fa'    => __( 'Farsi', 'ad-commander' ),
			'fi'    => __( 'Finnish', 'ad-commander' ),
			'fj'    => __( 'Fijian', 'ad-commander' ),
			'fo'    => __( 'Faeroese', 'ad-commander' ),
			'fr'    => __( 'French', 'ad-commander' ),
			'fr-be' => __( 'French - Belgium', 'ad-commander' ),
			'fr-ca' => __( 'French - Canada', 'ad-commander' ),
			'fr-ch' => __( 'French - Switzerland', 'ad-commander' ),
			'fr-fr' => __( 'French - France', 'ad-commander' ),
			'fr-lu' => __( 'French - Luxembourg', 'ad-commander' ),
			'fr-mc' => __( 'French - Monaco', 'ad-commander' ),
			'fur'   => __( 'Friulian', 'ad-commander' ),
			'fy'    => __( 'Frisian', 'ad-commander' ),
			'ga'    => __( 'Irish', 'ad-commander' ),
			'gd-ie' => __( 'Gaelic - Irish', 'ad-commander' ),
			'gd'    => __( 'Gaelic - Scots', 'ad-commander' ),
			'gd'    => __( 'Scots Gaelic', 'ad-commander' ),
			'gl'    => __( 'Galacian', 'ad-commander' ),
			'gu'    => __( 'Gujurati', 'ad-commander' ),
			'he'    => __( 'Hebrew', 'ad-commander' ),
			'hi'    => __( 'Hindi', 'ad-commander' ),
			'hr'    => __( 'Croatian', 'ad-commander' ),
			'hsb'   => __( 'Upper Sorbian', 'ad-commander' ),
			'ht'    => __( 'Haitian', 'ad-commander' ),
			'hu'    => __( 'Hungarian', 'ad-commander' ),
			'hy'    => __( 'Armenian', 'ad-commander' ),
			'id'    => __( 'Indonesian', 'ad-commander' ),
			'is'    => __( 'Icelandic', 'ad-commander' ),
			'it'    => __( 'Italian', 'ad-commander' ),
			'it-ch' => __( 'Italian - Switzerland', 'ad-commander' ),
			'iu'    => __( 'Inuktitut', 'ad-commander' ),
			'ja'    => __( 'Japanese', 'ad-commander' ),
			'ji'    => __( 'Yiddish', 'ad-commander' ),
			'ka'    => __( 'Georgian', 'ad-commander' ),
			'kk'    => __( 'Kazakh', 'ad-commander' ),
			'km'    => __( 'Khmer', 'ad-commander' ),
			'kn'    => __( 'Kannada', 'ad-commander' ),
			'ko'    => __( 'Korean', 'ad-commander' ),
			'ko-kp' => __( 'Korean - North Korea', 'ad-commander' ),
			'ko-kr' => __( 'Korean - South Korea', 'ad-commander' ),
			'ks'    => __( 'Kashmiri', 'ad-commander' ),
			'ky'    => __( 'Kirghiz', 'ad-commander' ),
			'la'    => __( 'Latin', 'ad-commander' ),
			'lb'    => __( 'Luxembourgish', 'ad-commander' ),
			'lt'    => __( 'Lithuanian', 'ad-commander' ),
			'lv'    => __( 'Latvian', 'ad-commander' ),
			'mi'    => __( 'Maori', 'ad-commander' ),
			'mk'    => __( 'FYRO Macedonian', 'ad-commander' ),
			'ml'    => __( 'Malayalam', 'ad-commander' ),
			'mo'    => __( 'Moldavian', 'ad-commander' ),
			'mr'    => __( 'Marathi', 'ad-commander' ),
			'ms'    => __( 'Malay', 'ad-commander' ),
			'mt'    => __( 'Maltese', 'ad-commander' ),
			'my'    => __( 'Burmese', 'ad-commander' ),
			'nb'    => __( 'Norwegian - Bokmal', 'ad-commander' ),
			'ne'    => __( 'Nepali', 'ad-commander' ),
			'ng'    => __( 'Ndonga', 'ad-commander' ),
			'nl'    => __( 'Dutch', 'ad-commander' ),
			'nl-be' => __( 'Dutch - Belgian', 'ad-commander' ),
			'nn'    => __( 'Norwegian - Nynorsk', 'ad-commander' ),
			'no'    => __( 'Norwegian', 'ad-commander' ),
			'nv'    => __( 'Navajo', 'ad-commander' ),
			'oc'    => __( 'Occitan', 'ad-commander' ),
			'om'    => __( 'Oromo', 'ad-commander' ),
			'or'    => __( 'Oriya', 'ad-commander' ),
			'pa'    => __( 'Punjabi', 'ad-commander' ),
			'pa-in' => __( 'Punjabi - India', 'ad-commander' ),
			'pa-pk' => __( 'Punjabi - Pakistan', 'ad-commander' ),
			'pl'    => __( 'Polish', 'ad-commander' ),
			'pt'    => __( 'Portuguese', 'ad-commander' ),
			'pt-br' => __( 'Portuguese - Brazil', 'ad-commander' ),
			'qu'    => __( 'Quechua', 'ad-commander' ),
			'rm'    => __( 'Rhaeto-Romanic', 'ad-commander' ),
			'ro'    => __( 'Romanian', 'ad-commander' ),
			'ro-mo' => __( 'Romanian - Moldavia', 'ad-commander' ),
			'ru-mo' => __( 'Russian - Moldavia', 'ad-commander' ),
			'ru'    => __( 'Russian', 'ad-commander' ),
			'sa'    => __( 'Sanskrit', 'ad-commander' ),
			'sb'    => __( 'Sorbian', 'ad-commander' ),
			'sc'    => __( 'Sardinian', 'ad-commander' ),
			'sd'    => __( 'Sindhi', 'ad-commander' ),
			'sg'    => __( 'Sango', 'ad-commander' ),
			'si'    => __( 'Singhalese', 'ad-commander' ),
			'sk'    => __( 'Slovak', 'ad-commander' ),
			'sl'    => __( 'Slovenian', 'ad-commander' ),
			'so'    => __( 'Somani', 'ad-commander' ),
			'sq'    => __( 'Albanian', 'ad-commander' ),
			'sr'    => __( 'Serbian', 'ad-commander' ),
			'sv'    => __( 'Swedish', 'ad-commander' ),
			'sv-fi' => __( 'Swedish - Finland', 'ad-commander' ),
			'sv-sv' => __( 'Swedish - Sweden', 'ad-commander' ),
			'sw'    => __( 'Swahili', 'ad-commander' ),
			'sx'    => __( 'Sutu', 'ad-commander' ),
			'sz'    => __( 'Sami - Lappish', 'ad-commander' ),
			'ta'    => __( 'Tamil', 'ad-commander' ),
			'te'    => __( 'Teluga', 'ad-commander' ),
			'th'    => __( 'Thai', 'ad-commander' ),
			'tig'   => __( 'Tigre', 'ad-commander' ),
			'tk'    => __( 'Turkmen', 'ad-commander' ),
			'tlh'   => __( 'Klingon', 'ad-commander' ),
			'tn'    => __( 'Tswana', 'ad-commander' ),
			'tr'    => __( 'Turkish', 'ad-commander' ),
			'ts'    => __( 'Tsonga', 'ad-commander' ),
			'tt'    => __( 'Tatar', 'ad-commander' ),
			'uk'    => __( 'Ukrainian', 'ad-commander' ),
			'ur'    => __( 'Urdu', 'ad-commander' ),
			've'    => __( 'Venda', 'ad-commander' ),
			'vi'    => __( 'Vietnamese', 'ad-commander' ),
			'vo'    => __( 'Volapuk', 'ad-commander' ),
			'wa'    => __( 'Walloon', 'ad-commander' ),
			'xh'    => __( 'Xhosa', 'ad-commander' ),
			'zh'    => __( 'Chinese', 'ad-commander' ),
			'zh-cn' => __( 'Chinese - PRC', 'ad-commander' ),
			'zh-hk' => __( 'Chinese - Hong Kong', 'ad-commander' ),
			'zh-sg' => __( 'Chinese - Singapore', 'ad-commander' ),
			'zh-tw' => __( 'Chinese - Taiwan', 'ad-commander' ),
			'zu'    => __( 'Zulu', 'ad-commander' ),
		);

		asort( $langs );

		return $langs;
	}
}
