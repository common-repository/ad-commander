<?php
namespace ADCmdr;

/**
 * Amp-specific tracking functions.
 */
class TrackingAmp {
	/**
	 * Ad impressions to track
	 *
	 * @var array
	 */
	protected $ad_impressions;

	/**
	 * Tracking methods completed.
	 *
	 * @var array
	 */
	protected $tracking_complete = array();

	/**
	 * Priority for hooks
	 *
	 * @var int
	 */
	protected $priority;

	/**
	 * An instance of this class.
	 *
	 * @var TrackingAmp|null
	 */
	private static $instance = null;

	/**
	 * Create an instance of self if necessary and return it.
	 *
	 * @return TrackingAmp
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function queue_impression( $ad ) {
		if ( ! is_array( $this->ad_impressions ) ) {
			$this->ad_impressions = array();
		}

		$this->ad_impressions[] = $ad;

		if ( ProBridge::instance()->is_pro_loaded() && class_exists( '\ADCmdr\TrackingAmpPro' ) ) {
			TrackingAmpPro::instance()->queue_impression( $ad );
		}
	}

	protected function priority() {
		if ( $this->priority ) {
			/**
			 * Make sure amp-pixel runs after all placements.
			 */
			$this->priority = apply_filters( 'adcmdr_amp_action_priority', absint( Options::instance()->get( 'filter_priority', 'general', false, Placement::placement_priority_default() ) ) + 10 );
		}

		return $this->priority;
	}

	public function hooks_local_tracking() {
		if ( Options::instance()->get( 'disable_amp_pixel', 'tracking', true ) || ! is_ssl() ) {
			return;
		}

		$priority = $this->priority();

		add_action( 'wp_footer', array( $this, 'amp_pixel' ), $priority );

		// https://wordpress.org/plugins/amp/
		// https://wordpress.org/plugins/accelerated-mobile-pages/
		add_action( 'amp_post_template_footer', array( $this, 'amp_pixel' ), $priority );
	}

	public function hooks_ga_tracking() {
		if ( ProBridge::instance()->is_pro_loaded() && class_exists( '\ADCmdr\TrackingAmpPro' ) ) {
			TrackingAmpPro::instance()->hooks_ga_tracking();
		}
	}

	public function amp_pixel() {
		if ( ! $this->ad_impressions || empty( $this->ad_impressions ) || in_array( 'local', $this->tracking_complete, true ) ) {
			return;
		}

		$action        = 'track-impression';
		$frontend      = new Frontend();
		$action_string = $frontend->action_string( $action );
		$security      = $frontend->not_a_nonce( $action );

		/**
		 * AMP Pixel must be served over HTTPS.
		 */
		$url = admin_url( 'admin-ajax.php' );

		if ( stripos( $url, 'http://', 0 ) !== false ) {
			$url = str_ireplace( 'http://', 'https://', $url );
		}

		$amp_pixel = sprintf(
			'<amp-pixel src="%s" layout="nodisplay"></amp-pixel>',
			esc_url(
				add_query_arg(
					array(
						'ad_ids'   => array_map( 'absint', wp_list_pluck( $this->ad_impressions, 'ad_id' ) ),
						'action'   => $action_string,
						'security' => $security,
						'referrer' => rawurlencode( $this->get_referrer() ),
					),
					$url
				)
			)
		);

		echo wp_kses(
			$amp_pixel,
			array(
				'amp-pixel' => array(
					'src'    => array(),
					'layout' => array(),
					'class'  => array(),
				),
			)
		);

		$this->tracking_complete[] = 'local';
	}

	private function get_referrer() {
		global $wp;

		$referrer = isset( $wp->request ) && ! is_null( $wp->request ) ? $wp->request : '';
		$referrer = ( $referrer === '' && isset( $_SERVER['REQUEST_URI'] ) ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : $referrer;
		$referrer = preg_replace( '%^/?(.+?)[/?&]*amp(?:=1|/)?$%', '$1', $referrer );
		$referrer = ( substr( $referrer, 0, 1 ) !== '/' ) ? '/' . $referrer : $referrer;

		return $referrer;
	}
}
