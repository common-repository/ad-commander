<?php
namespace ADCmdr;

/**
 * Class for determining if an ad or group should display in the current visitor scenario.
 */
class Visitor {
	/**
	 * An instance of this class.
	 *
	 * @var Visitor|null
	 */
	private static $instance = null;

	/**
	 * Create an instance of self if necessary and return it.
	 *
	 * @return Visitor
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Cookie that includes all site impressions.
	 *
	 * @return string
	 */
	public static function impression_cookie_name() {
		return Util::ns( 'page_impressions' );
	}

	/**
	 * Cookie that includes the referrer for this session
	 *
	 * @return string
	 */
	public static function session_referrer_cookie_name() {
		return Util::ns( 'session_referrer' );
	}

	/**
	 * The visitor cookie, which includes some browser information.
	 *
	 * @return string
	 */
	public static function visitor_cookie_name() {
		return Util::ns( 'visitor' );
	}

	/**
	 * Cookie that includes impressions for ads and placements.
	 *
	 * @return string
	 */
	public static function ad_impression_cookie_name() {
		return Util::ns( 'ad_impressions' );
	}

	/**
	 * Cookie that includes ad click counts.
	 *
	 * @return string
	 */
	public static function ad_click_cookie_name() {
		return Util::ns( 'ad_clicks' );
	}

	/**
	 * Get the visitor site impressions from a cookie.
	 *
	 * @return int
	 */
	public function impressions() {
		$impressions = 0;

		$impression_cookie = self::impression_cookie_name();

		if ( isset( $_COOKIE[ $impression_cookie ] ) ) {
			$impressions = absint( $_COOKIE[ $impression_cookie ] );
		}

		return $impressions;
	}

	/**
	 * Save the session referrer to a cookie.
	 *
	 * @return void
	 */
	public function set_session_referrer_url() {
		if ( ! is_admin() && ! wp_doing_ajax() && ! isset( $_COOKIE[ $this->session_referrer_cookie_name() ] ) ) {

			$url = '';
			if ( isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$url = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			}

			$this->process_referrer_url( $url );
		}
	}

	/**
	 * Receive the referrer from front-end js.
	 *
	 * @return void
	 */
	public function handle_js_session_referrer( $referrer ) {
		$this->process_referrer_url( $referrer, false );
	}

	/**
	 * Process the referring url and optionally save it to a cookie.
	 *
	 * @return void
	 */
	private function process_referrer_url( $url, $set_cookie = true ) {
		if ( $url !== '' ) {
			$url = sanitize_url( $url );
		}

		$cookie_name = $this->session_referrer_cookie_name();

		if ( $set_cookie ) {
			setcookie( $cookie_name, $url, 0, '/' );
		}

		$_COOKIE[ $cookie_name ] = $url;
	}

	/**
	 * Return the session referrer if one exists.
	 *
	 * @return string
	 */
	public function get_session_referrer_url() {
		$cookie_name = $this->session_referrer_cookie_name();
		return ( isset( $_COOKIE[ $cookie_name ] ) ) ? wp_strip_all_tags( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : '';
	}

	/**
	 * Get the visitor cookie with browser information.
	 *
	 * @return array
	 */
	public function get_visitor_cookie() {
		$cookie_name = $this->visitor_cookie_name();
		return ( isset( $_COOKIE[ $cookie_name ] ) ) ? json_decode( wp_strip_all_tags( wp_unslash( $_COOKIE[ $cookie_name ] ) ), true ) : array();
	}
}
