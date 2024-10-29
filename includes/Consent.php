<?php
namespace ADCmdr;

/**
 * Consent requirements for ads and groups
 */
class Consent {
	/**
	 * An instance of this class.
	 *
	 * @var null|Consent
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return Consent
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The current consent cookie
	 *
	 * @var bool|array
	 */
	private $consent_cookie = null;

	/**
	/**
	 * Returns consent requirement that isn't specific to an ad or group.
	 * Checks for the cookie before deciding if the visitor still needs consent.
	 *
	 * @param bool $force_nocheck Whether to skip cookie check.
	 */
	public function global_needs_consent( $force_nocheck = false ) {
		if ( $force_nocheck ) {
			return false;
		}

		$consent_cookie = $this->consent_cookie();

		if ( ! $consent_cookie ) {
			/**
			 * Consent not required.
			 */
			return false;
		}

		$cookie_name = $consent_cookie['name'];

		if ( $consent_cookie['compare'] === 'exists' ) {
			/**
			 * Check only if the cookie exists.
			 */
			return ! isset( $_COOKIE[ $cookie_name ] );
		}

		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$user_cookie = wp_strip_all_tags( wp_unslash( $_COOKIE[ $cookie_name ] ) );

			if ( $user_cookie === null || ! $user_cookie ) {
				$user_cookie = '';
			}

			if ( $consent_cookie['insensitive'] === true ) {
				$user_cookie             = strtolower( $user_cookie );
				$consent_cookie['value'] = strtolower( $consent_cookie['value'] );
			}

			if ( $consent_cookie['compare'] === 'equals' ) {
				/**
				 * Return true if user cookie does not match value.
				 */
				return $user_cookie !== $consent_cookie['value'];
			}

			if ( $consent_cookie['compare'] === 'contains' ) {
				/**
				 * Return true if value is not found in user cookie
				 */
				return strpos( $user_cookie, $consent_cookie['value'] ) === false;
			}
		}

		return true;
	}

	/**
	 * Set up the consent cookie.
	 *
	 * @return bool|array
	 */
	public function consent_cookie() {
		if ( $this->consent_cookie !== null ) {
			return $this->consent_cookie;
		}

		$this->consent_cookie = false;

		if ( Options::instance()->get( 'consent_required', 'privacy', true ) ) {
			$cookie_name = Options::instance()->get( 'consent_cookie_name', 'privacy' );

			if ( ! $cookie_name ) {
				$this->consent_cookie = false;
				return $this->consent_cookie;
			}

			$cookie_str = Options::instance()->get( 'consent_cookie_value', 'privacy', false, '' );

			if ( ! $cookie_str ) {
				$cookie_str     = '';
				$cookie_compare = 'exists';
			} else {
				$cookie_compare = Options::instance()->get( 'consent_cookie_comparison', 'privacy', false, 'equals' );
			}

			$insensitive = false;
			if ( strpos( $cookie_compare, '_insensitive' ) !== false ) {
				$cookie_compare = str_replace( '_insensitive', '', $cookie_compare );
				$insensitive    = true;
			}

			$this->consent_cookie = array(
				'name'        => $cookie_name,
				'value'       => $cookie_str,
				'compare'     => $cookie_compare,
				'insensitive' => $insensitive,
			);
		}

		return $this->consent_cookie;
	}

	/**
	 * Determine if consent is required by checking the consent cookie settings.
	 * This function does not check an individual visitor's cookies, just the settings.
	 *
	 * @return bool
	 */
	public function requires_consent() {
		if ( $this->consent_cookie() !== false ) {
			return true;
		}

		return false;
	}
}
