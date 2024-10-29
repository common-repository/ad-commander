<?php
namespace ADCmdr;

/**
 * Tracking functionality shared between various forms of tracking.
 */
class Tracking {
	/**
	 * An instance of this class.
	 *
	 * @var Tracking|null
	 */
	private static $instance = null;

	/**
	 * Create an instance of self if necessary and return it.
	 *
	 * @return Tracking
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enqueue tracking script.
	 *
	 * @param array $track_deps The dependencies for this enqueue.
	 * @param array $actions The actions to pass in.
	 * @param array $tracking_methods The tracking methods that are enabled.
	 * @param mixed $ga_config The GA config, if one exists.
	 *
	 * @return string
	 */
	public function enqueue_track( $track_deps, $actions, $tracking_methods, $ga_config ) {
		$track_handle = Util::ns( 'track' );

		wp_register_script( $track_handle, AdCommander::assets_url() . 'js/track.js', $track_deps, AdCommander::version(), true );
		wp_enqueue_script( $track_handle );

		$track_data = array(
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'actions'     => $actions,
			'methods'     => $tracking_methods,
			'user_events' => $this->events_to_track(),
		);

		if ( $ga_config ) {
			$track_data['ga'] = $ga_config;
		}

		Util::enqueue_script_data( $track_handle, $track_data );

		return $track_handle;
	}

	/**
	 * Get the enabled tracking methods.
	 *
	 * @return array
	 */
	public function get_tracking_methods() {
		$methods = array();

		if ( $this->is_local_tracking_enabled() ) {
			$methods[] = 'local';
		}

		if ( ProBridge::instance()->is_pro_loaded() && Options::instance()->get( 'enable_ga_tracking', 'tracking', true ) ) {
			$methods[] = 'ga';
		}

		return $methods;
	}

	/**
	 * Determine if we have any tracking methods.
	 *
	 * @return bool
	 */
	public function has_tracking_methods() {
		return ( ! empty( $this->get_tracking_methods() ) );
	}

	/**
	 * Determine if we have impression or click tracking disabled.
	 *
	 * @param string $type String for impressions or clicks.
	 *
	 * @return bool
	 */
	public function is_tracking_disabled_for( $type ) {
		/**
		 * This does not check settings of individual ads for performance reasons.
		 * We're relying on javascript not binding click and impressions to ads that are individually disabled.
		 */

		$type = trim( strtolower( $type ) );

		if ( $type !== 'impressions' && $type !== 'clicks' ) {
			return;
		}

		return Options::instance()->get( 'disable_track_' . $type, 'tracking', true );
	}

	/**
	 * Determine which events we should track, if any.
	 *
	 * @return array
	 */
	public function events_to_track() {
		$actions = array();

		if ( ! $this->is_tracking_disabled_for( 'impressions' ) ) {
			$actions[] = 'impressions';
		}

		if ( ! $this->is_tracking_disabled_for( 'clicks' ) ) {
			$actions[] = 'clicks';
		}

		return $actions;
	}

	/**
	 * Determine if local tracking is possible.
	 *
	 * @return bool
	 */
	public static function can_track_local() {
		return class_exists( 'DateTime' );
	}

	/**
	 * Determine if we should track local for a specific type of tracking.
	 * Also checks for bots.
	 *
	 * @param string $type String for impressions or clicks tracking.
	 * @param bool   $bot_check Whether to check for bots or not.
	 *
	 * @return [type]
	 */
	public function should_track_local( $type, $bot_check = true ) {
		if ( $this->is_local_tracking_enabled() && ! $this->is_tracking_disabled_for( $type ) ) {
			if ( $bot_check ) {
				return ! $this->should_block_bot();
			}

			return true;
		}

		return false;
	}

	/**
	 * Checks for local tracking.
	 * Takes into account both the option, and if we CAN track local.
	 *
	 * @return bool
	 */
	public function is_local_tracking_enabled() {
		return self::can_track_local() && Options::instance()->get( 'enable_local_tracking', 'tracking', true );
	}

	/**
	 * Checks for GA tracking.
	 *
	 * @return bool
	 */
	public function is_ga_tracking_enabled() {
		return ProBridge::instance()->is_pro_loaded() === true && Options::instance()->get( 'enable_ga_tracking', 'tracking', true ) === true;
	}

	/**
	 * Determines if we should block a bot or not.
	 *
	 * @return bool
	 */
	private function should_block_bot() {
		if ( ! Options::instance()->get( 'bots_disable_tracking', 'tracking', true ) ) {
			return false;
		}

		return Bots::is_bot();
	}
}
