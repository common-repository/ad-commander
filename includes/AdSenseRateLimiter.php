<?php
namespace ADCmdr;

/**
 * Limit the amount of hits to the AdSense API.
 */
class AdSenseRateLimiter {
	/**
	 * An instance of this class.
	 *
	 * @var null|AdSenseRateLimiter
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return AdSenseRateLimiter
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Determine the allowed number of API calls per day.
	 *
	 * @return int
	 */
	public static function api_calls_per_day() {

		if ( AdminAdSense::instance()->is_user_credentials() ) {
			return PHP_INT_MAX;
		}

		$probridge = ProBridge::instance();

		if ( $probridge->is_pro_loaded() && $probridge->pro_license_status() === 'valid' ) {
			return apply_filters( 'adcmdr_pro_adsense_api_calls_per_day', 20 );
		}

		return 20;
	}

	/**
	 * Determine if API calls remain.
	 *
	 * @return bool
	 */
	public function has_api_calls_remaining() {
		return $this->api_calls_remaining() > 0;
	}

	/**
	 * Determine the number of API calls remaining.
	 *
	 * @return int
	 */
	public function api_calls_remaining() {
		$options = Options::instance();
		$quota   = $options->get( 'adsense_api_quota' );

		if ( ! $quota || ! isset( $quota['timestamp'] ) || ( isset( $quota['timestamp'] ) && time() > ( $quota['timestamp'] + DAY_IN_SECONDS ) ) ) {
			$quota = array(
				'max'   => self::api_calls_per_day(),
				'calls' => 0,
			);
			$options->update( 'adsense_api_quota', $quota );
		} elseif ( $quota && isset( $quota['max'] ) && $quota['max'] < self::api_calls_per_day() ) {
			$quota['max'] = self::api_calls_per_day();
			$options->update( 'adsense_api_quota', $quota );
		}

		if ( ! isset( $quota['calls'] ) || $quota['calls'] < 0 ) {
			$quota['calls'] = 0;
		}

		return $quota['max'] - $quota['calls'];
	}

	/**
	 * Decrease the number of API calls remaining (after changes this technically increases the # of calls made, not decreases remaining.)
	 * Return the quota array.
	 *
	 * @return array
	 */
	public function decrease_remaining() {
		$options = Options::instance();
		$quota   = $options->get( 'adsense_api_quota' );

		if ( ! $quota || ! isset( $quota['max'] ) ) {
			$quota = array(
				'max'   => self::api_calls_per_day(),
				'calls' => 0,
			);
		}

		/**
		 * In case someone removes a custom app or is no longer a pro user.
		 */
		if ( $quota['max'] > self::api_calls_per_day() ) {
			$quota['max'] = self::api_calls_per_day();
		}

		/**
		 * If this is the first call to the API since it reset, there will be no timestamp.
		 */
		if ( ! isset( $quota['timestamp'] ) ) {
			$quota['timestamp'] = time();
		}

		++$quota['calls'];

		$options->update( 'adsense_api_quota', $quota );

		return $quota;
	}
}
