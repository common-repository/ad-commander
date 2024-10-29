<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOUtilities;

/**
 * Frontend scripts, styles, ajax handling, etc.
 */
class Frontend {
	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'maybe_track_session_referrer' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		foreach ( self::get_action_keys() as $key ) {
			$key_underscore = self::_key( $key );
			add_action( 'wp_ajax_' . $this->action_string( $key ), array( $this, 'action_' . $key_underscore ) );
			add_action( 'wp_ajax_nopriv_' . $this->action_string( $key ), array( $this, 'action_' . $key_underscore ) );
		}

		$adsense = new AdSense();
		$adsense->hooks();
	}

	/**
	 * Get necessary action keys, which will be used to create wp_ajax hooks.
	 *
	 * @param bool $force_all Force all action keys to load.
	 *
	 * @return array
	 */
	public static function get_action_keys( $force_all = false ) {
		$action_keys = apply_filters( 'adcmdr_frontend_action_keys', array() );

		if ( $force_all || TrackingLocal::instance()->should_track_local( 'impressions', false ) ) {
			$action_keys[] = 'track-impression';
		}

		if ( $force_all || TrackingLocal::instance()->should_track_local( 'clicks', false ) ) {
			$action_keys[] = 'track-click';
		}

		return $action_keys;
	}

	/**
	 * Converts dashes to underscores for key use.
	 *
	 * @param string $key The key to convert.
	 *
	 * @return string
	 */
	protected function _key( $key ) {
		return str_replace( '-', '_', $key );
	}

	/**
	 * Creates an action string for nonce use.
	 *
	 * @param string $action The action key.
	 *
	 * @return string
	 */
	public function action_string( $action ) {
		return self::_key( sanitize_key( Util::ns( $action, '_' ) ) );
	}

	/**
	 * Creates the option key for storing a not_a_nonce in the database.
	 *
	 * @param string $action The action name.
	 *
	 * @return string
	 */
	public static function not_a_nonce_option_key( $action ) {
		return 'notanonce_' . $action;
	}

	/**
	 * An alternative to nonce for the front-end. Generates an md5 string unique to this site and unique to each action.
	 * Then store it in the database. See check_frontend_ajax_action() for explanation.
	 *
	 * @param string $action The action key.
	 *
	 * @return string
	 */
	public function not_a_nonce( $action ) {
		$action     = sanitize_text_field( $action );
		$option_key = self::not_a_nonce_option_key( $action );

		$notanonce = Options::instance()->get( $option_key );

		if ( ! $notanonce ) {
			$notanonce = $this->create_not_a_nonce( $action );
			Options::instance()->update( $option_key, $notanonce );
		}

		return $notanonce;
	}

	/**
	 * Create a not_a_nonce string.
	 * We're running this through sanitize_key and wp_unslash because that is how the input will be treated.
	 *
	 * @param string $action Action name.
	 *
	 * @return string
	 */
	protected function create_not_a_nonce( $action ) {
		return sanitize_key( wp_unslash( md5( site_url() . time() . $this->action_string( $action ) ) ) );
	}

	/**
	 * Checks an incoming action to make sure it is allowed.
	 *
	 * @param string $action_key The action key (contains dashes).
	 * @param array  $required_keys Any $_REQUEST keys that must exist.
	 *
	 * @return void
	 */
	protected function check_frontend_ajax_action( $action_key, $required_keys = array() ) {
		/**
		 * We aren't using nonces on the front-end when not dealing with data that is unique to an individual user.
		 * If the nonce expires because of page caching, tracking and loading ads would break.
		 * Instead, we'll use a a random string generated from the site URL, current time, and the action string. This will be stored in the database.
		 *
		 * This method is only used for loading ads (which are public) and for tracking impressions/clicks, which are not tied to a user.
		 * Nonces wouldn't prevent potential attacks in this case either, because they are not unique to logged-out users.
		 */

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above comment.
		if ( ! isset( $_REQUEST['security'] ) || $this->not_a_nonce( $action_key ) !== sanitize_key( wp_unslash( $_REQUEST['security'] ) ) ) {
			wp_die();
		}

		if ( $required_keys ) {
			$required_keys = Util::arrayify( $required_keys );

			foreach ( $required_keys as $key ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above comment.
				if ( ! isset( $_REQUEST[ $key ] ) ) {
					wp_die();
				}
			}
		}
	}

	/**
	 * Creates an array of all of the necessary actions.
	 *
	 * @param bool $tracking_actions Whether to include tracking actions or not.
	 *
	 * @return array
	 */
	private function get_ajax_actions( $tracking_actions = false ) {
		$actions = array();

		foreach ( self::get_action_keys() as $key ) {
			if ( $tracking_actions && stripos( $key, 'track-' ) === false ) {
				continue;
			} elseif ( ! $tracking_actions && stripos( $key, 'track-' ) !== false ) {
				continue;
			}

			$actions[ self::_key( $key ) ] = array(
				'action'   => $this->action_string( $key ),
				'security' => $this->not_a_nonce( $key ),
			);
		}

		return $actions;
	}

	/**
	 * Action fired to track local impressions.
	 *
	 * @return void
	 */
	public function action_track_impression() {
		$this->check_frontend_ajax_action( 'track-impression', 'ad_ids' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See comment in check_frontend_ajax_action.
		if ( ! isset( $_REQUEST ) || ! isset( $_REQUEST['ad_ids'] ) ) {
			wp_die();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See comment in check_frontend_ajax_action;
		$ad_ids = is_array( $_REQUEST['ad_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['ad_ids'] ) ) : sanitize_text_field( wp_unslash( $_REQUEST['ad_ids'] ) );
		$ad_ids = WOUtilities::sanitize_int_array( $ad_ids ); // Convert comma separated string, array, or int into ints.

		TrackingLocal::instance()->track( $ad_ids, 'impressions' );

		wp_die();
	}

	/**
	 * Action fired to track local clicks.
	 *
	 * @return void
	 */
	public function action_track_click() {
		$this->check_frontend_ajax_action( 'track-click', 'ad_ids' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See comment in check_frontend_ajax_action.
		if ( ! isset( $_REQUEST ) || ! isset( $_REQUEST['ad_ids'] ) ) {
			wp_die();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See comment in check_frontend_ajax_action;
		$ad_ids = is_array( $_REQUEST['ad_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['ad_ids'] ) ) : sanitize_text_field( wp_unslash( $_REQUEST['ad_ids'] ) );
		$ad_ids = WOUtilities::sanitize_int_array( $ad_ids ); // Convert comma separated string, array, or int into ints.

		TrackingLocal::instance()->track( $ad_ids, 'clicks' );

		wp_die();
	}

	/**
	 * Attempt to set the session referrer.
	 * Note: This does not run if page caching is enabled.
	 *
	 * @return void
	 */
	public function maybe_track_session_referrer() {
		Visitor::instance()->set_session_referrer_url();
	}

	/**
	 * Initialize the WordPress filesystem.
	 *
	 * @return bool
	 */
	private function init_wp_filesystem() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		add_filter( 'filesystem_method', array( $this, 'filesystem_direct_method' ) );
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! isset( $wp_filesystem->method ) || $wp_filesystem->method !== 'direct' ) {
			return false;
		}

		return true;
	}

	/**
	 * Remove the filesystem method filter.
	 */
	private function end_wp_filesystem() {
		remove_filter( 'filesystem_method', array( $this, 'filesystem_direct_method' ) );
	}

	/**
	 * Set the WordPress filesystem method.
	 *
	 * @return string
	 */
	public function filesystem_direct_method() {
		return 'direct';
	}

	/**
	 * Convert the plugin stylesheet to a CSS string and print it.
	 *
	 * @param string $prefix The style prefix.
	 * @param string $handle The handle of the front-end stylesheet.
	 * @param string $file_path The path to the stylesheet.
	 * @param string $version The version to append to the stylesheet and transients.
	 * @param bool   $enqueue Whether to delay the printing or print now.
	 *
	 * @return bool
	 */
	private function print_styles_with_prefix( $prefix, $handle, $file_path, $version, $enqueue = true ) {
		try {

			$handle_no_ns = str_replace( AdCommander::ns() . '-', '', $handle );

			$transient = Util::ns( sanitize_title( 'prefix_css_' . $handle_no_ns . '_' . sanitize_key( $version ) ) );
			$css       = get_transient( $transient );

			if ( ! $css ) {
				if ( $this->init_wp_filesystem() ) {
					global $wp_filesystem;

					$css = $wp_filesystem->get_contents( $file_path );

					if ( $css ) {
						$css = str_replace( array( '.adcmdr', '#adcmdr', '--adcmdr' ), array( '.' . $prefix, '#' . $prefix, '--' . $prefix ), $css );

						set_transient( $transient, $css, WEEK_IN_SECONDS );
					}
				}

				$this->end_wp_filesystem();
			}

			if ( $css ) {
				/**
				 * Rather than trying to write and enqueue a dynamic file, we will print the styles to <head>.
				 */
				if ( $enqueue ) {
					add_action(
						'wp_print_styles',
						function () use ( $prefix, $handle_no_ns, $css ) {
							printf( '<style id="%1$s">%2$s</style>', esc_html( $prefix . '-' . $handle_no_ns ), esc_html( $css ) );
						}
					);
				} else {
					printf( '<style id="%1$s">%2$s</style>', esc_html( $prefix . '-' . $handle_no_ns ), esc_html( $css ) );
				}

				Options::instance()->delete( 'custom_css_failure' );

				return true;
			}

			return false;

		} catch ( \Exception $e ) {
			wo_log( $e->getMessage() );
			return false;
		}
	}

	/**
	 * Either enqueue or print the front-end styles.
	 *
	 * @param string $handle The handle of the front-end stylesheet.
	 * @param string $file_path The path to the stylesheet.
	 * @param string $version The version to append to the stylesheet and transients.
	 * @param bool   $enqueue Whether to delay the printing or print now.
	 * @param array  $deps The dependencies of the stylesheet, if enqueued.
	 *
	 * @return string
	 */
	public function enqueue_or_print_styles( $handle, $file_path, $version, $enqueue = true, $deps = array(), $force = false ) {
		$prefix = Util::prefix();

		if ( $force || ! Options::instance()->get( 'disable_stylesheets', 'general', true, false ) ) {

			if ( apply_filters( 'adcmdr_should_enqueue_stylesheet', true, $handle ) ) {
				$printed = false;
				if ( $prefix !== AdCommander::ns() ) {
					$printed = $this->print_styles_with_prefix( $prefix, $handle, $file_path, $version, $enqueue );
				}

				if ( ! $printed ) {
					wp_enqueue_style( $handle, $file_path, $deps, $version );

					/**
					 * There was a problem printing the styles, so we need to reset the prefix.
					 * This is likely occurring because the local stylesheet couldn't be read for some reason.
					 */
					if ( $prefix !== AdCommander::ns() ) {
						Options::instance()->update_one( 'prefix', AdCommander::ns(), 'general' );
						Options::instance()->update( 'custom_css_failure', true );
						return AdCommander::ns();
					}
				}
			}
		}

		return $prefix;
	}

	/**
	 * Enqueue scripts and styles on the front-end.
	 *
	 * @return void
	 */
	public function enqueue() {

		if ( ! is_admin() ) {
			$is_pro       = ProBridge::instance()->is_pro_loaded();
			$front_handle = Util::ns( 'front' );
			$data_handle  = $front_handle;

			$prefix = $this->enqueue_or_print_styles( $front_handle, AdCommander::assets_url() . 'css/style.css', AdCommander::version() );

			if ( $is_pro ) {
				$prefix_pro = FrontendPro::instance()->enqueue_or_print_styles_pro();
			}

			/**
			 * Don't enqueue scripts for AMP.
			 */
			$tracking_methods = Tracking::instance()->get_tracking_methods();

			if ( Amp::instance()->is_amp() ) {
				if ( ! empty( $tracking_methods ) ) {
					$tracking_amp = TrackingAmp::instance();

					if ( in_array( 'local', $tracking_methods ) ) {
						$tracking_amp->hooks_local_tracking();
					}

					if ( in_array( 'ga', $tracking_methods ) ) {
						$tracking_amp->hooks_ga_tracking();
					}
				}
			} else {
				$ga_config  = array();
				$front_deps = array();

				if ( ! empty( $tracking_methods ) ) {
					$track_deps = array();

					/**
					 * GA tracking
					 */
					if ( in_array( 'ga', $tracking_methods ) && $is_pro ) {
						$ga_config = apply_filters( 'adcmdr_pro_ga_config', false );

						if ( $ga_config ) {
							$ga_tracking_handle = apply_filters( 'adcmdr_pro_enqueue_ga_tracking', '' );

							if ( $ga_tracking_handle ) {
								$track_deps[] = $ga_tracking_handle;
							}
						} else {
							$tracking_methods = array_filter(
								$tracking_methods,
								function ( $m ) {
									return ( $m !== 'ga' );
								}
							);
						}
					}

					/**
					 * Local tracking
					 */
					if ( in_array( 'local', $tracking_methods ) ) {
						$track_deps[] = TrackingLocal::instance()->enqueue_track_local();
					}

					/**
					 * Primary tracking script
					 */
					$front_deps[] = Tracking::instance()->enqueue_track( $track_deps, $this->get_ajax_actions( true ), $tracking_methods, $ga_config );
				}

				/**
				 * Primary Front-end script enqueue
				 */
				$front_args = array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'prefix'  => esc_html( $prefix ),
					'cookies' => array(
						'i'   => Visitor::instance()->impression_cookie_name(),
						'i_a' => Visitor::instance()->ad_impression_cookie_name(),
						'c_a' => Visitor::instance()->ad_click_cookie_name(),
						'r'   => Visitor::instance()->session_referrer_cookie_name(),
						'v'   => Visitor::instance()->visitor_cookie_name(),
					),
					'actions' => apply_filters( 'adcmdr_frontend_actions', $this->get_ajax_actions( false ) ),
				);

				/**
				 * Enqueue
				 */
				wp_register_script( 'worotate', AdCommander::assets_url() . 'js/rotate.js', $front_deps, AdCommander::version(), array( 'in_footer' => true ) );
				wp_enqueue_script( 'worotate' );
				$front_deps[] = 'worotate';

				if ( $is_pro ) {
					$front_pro_handle = FrontendPro::instance()->enqueue_pro( $front_deps );

					$data_handle  = $front_pro_handle;
					$front_deps[] = $front_pro_handle;
				}

				wp_register_script( $front_handle, AdCommander::assets_url() . 'js/front.js', $front_deps, AdCommander::version(), array( 'in_footer' => true ) );
				wp_enqueue_script( $front_handle );

				Util::enqueue_script_data(
					$data_handle,
					apply_filters( 'adcmdr_front_js_script_data', $front_args ),
					str_replace( '-', '_', $front_handle )
				);
			}
		}
	}
}
