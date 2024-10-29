<?php
namespace ADCmdr;

/**
 * Admin notifications regarding potential issues.
 */
class AdminNotifications extends Admin {

	/**
	 * The current notifications.
	 *
	 * @var array
	 */
	protected $notifications;

	/**
	 * The current hidden notifications.
	 *
	 * @var array
	 */
	protected $hidden_notifications;

	/**
	 * An instance of this class.
	 *
	 * @var null|AdminNotifications
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return AdminNotifications
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		foreach ( $this->get_action_keys() as $key ) {
			$key_underscore = self::_key( $key );
			add_action( 'wp_ajax_' . $this->action_string( $key ), array( $this, 'action_' . $key_underscore ) );
		}
	}

	/**
	 * Get necessary action keys, which will be used to create wp_ajax hooks.
	 *
	 * @return array
	 */
	private function get_action_keys() {
		return array(
			'notification-visibility',
		);
	}

	/**
	 * Creates an array of all of the necessary actions.
	 *
	 * @return array
	 */
	private function get_ajax_actions() {
		$actions = array();

		foreach ( $this->get_action_keys() as $key ) {
			$actions[ self::_key( $key ) ] = array(
				'action'   => $this->action_string( $key ),
				'security' => wp_create_nonce( $this->nonce_string( $key ) ),
			);
		}

		return $actions;
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue() {

		if ( $this->is_screen( array( 'toplevel_page_ad-commander' ) ) ) {
			wp_enqueue_script( 'jquery' );

			$handle = Util::ns( 'notifications' );

			wp_register_script(
				$handle,
				AdCommander::assets_url() . 'js/notifications.js',
				array(
					'jquery',
				),
				AdCommander::version(),
				array( 'in_footer' => true )
			);

			wp_enqueue_script( $handle );

			Util::enqueue_script_data(
				$handle,
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'actions' => $this->get_ajax_actions(),
				)
			);
		}
	}


	/**
	 * Toggle visibility.
	 */
	public function action_notification_visibility() {
		$action = 'notification-visibility';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		$hidden = Options::instance()->get( 'notifications_hidden', null, array() );

		$notification_key = isset( $_REQUEST['notification_key'] ) ? sanitize_key( $_REQUEST['notification_key'] ) : false;

		if ( ! $notification_key ) {
			wp_die();
		}

		if ( in_array( $notification_key, $hidden ) ) {
			$hide   = false;
			$hidden = array_filter( $hidden, fn( $value ) => $value !== $notification_key );
		} else {
			$hide     = true;
			$hidden[] = $notification_key;
		}

		Options::instance()->update( 'notifications_hidden', $hidden, true );
		$this->hidden_notifications = $hidden;

		wp_send_json_success(
			array(
				'action' => $action,
				'hide'   => ( $hide ) ? 1 : 0,
			)
		);

		wp_die();
	}

	public function run_tests() {
		$this->notifications        = array();
		$this->hidden_notifications = Options::instance()->get( 'notifications_hidden', null, array() );

		$tests = array( 'caching', 'consent', 'custom_css_failure', 'bots', 'adsense_alerts' );

		foreach ( $tests as $test ) {
			try {
				$func = 'test_' . $test;
				if ( method_exists( $this, $func ) ) {
					$result = $this->$func();

					if ( ! $result ) {
						continue;
					}

					if ( ! is_array( $result ) ) {
						$this->notifications[ $test ] = $result;
					} else {
						foreach ( $result as $r ) {
							if ( ! is_array( $r ) ) {
								$this->notifications[ $test ] = $result;
								break;
							} else {
								$this->notifications[ $test . '_' . $r['id'] ] = array(
									'title' => $r['title'],
									'text'  => $r['text'],
								);
							}
						}
					}
				}
			} catch ( \Exception $e ) {
				continue;
			}
		}

		/**
		 * Remove any unneccessary (old) hidden notification keys.
		 */
		$filtered_hidden = array_filter( $this->hidden_notifications, fn( $value ) => in_array( $value, array_keys( $this->notifications ) ) );

		if ( $filtered_hidden !== $this->hidden_notifications ) {
			$this->hidden_notifications = $filtered_hidden;
			Options::instance()->update( 'notifications_hidden', $this->hidden_notifications, true );
		}
	}

	/**
	 * Count active notifications.
	 *
	 * @return int
	 */
	public function count() {
		$this->run_tests();

		if ( ! $this->notifications || count( $this->notifications ) <= 0 ) {
			return 0;
		}

		return count(
			array_filter(
				$this->notifications,
				fn ( $notification ) => ! in_array( $notification, $this->hidden_notifications ),
				ARRAY_FILTER_USE_KEY
			)
		);
	}

	/**
	 * Build list of notifications.
	 */
	public function build() {
		$this->run_tests();

		/**
		 * Move ignored notifications to the end.
		 */
		uksort(
			$this->notifications,
			function ( $a, $b ) {
				return in_array( $a, $this->hidden_notifications ) <=> in_array( $b, $this->hidden_notifications );
			}
		);
	}

	/**
	 * Display notifications.
	 *
	 * @return string The notification content.
	 */
	public function display() {
		$this->build();

		if ( empty( $this->notifications ) ) {
			return esc_html__( 'Everything looks good! You do not have any notifications at this time.', 'ad-commander' );
		}

		$visible_content = '';
		$hidden_content  = '';

		foreach ( $this->notifications as $key => $text ) {
			$notification = $this->notification( ( isset( $text['text'] ) ) ? $text['text'] : $text, $key, ( isset( $text['title'] ) ) ? $text['title'] : '', ( isset( $text['button'] ) ) ? $text['button'] : '' );

			if ( in_array( $key, $this->hidden_notifications ) ) {
				$hidden_content .= $notification;
			} else {
				$visible_content .= $notification;
			}
		}

		if ( $hidden_content ) {
			/* translators: %d: The number of hidden notifications */
			$button_text    = sprintf( esc_html__( 'Hidden notifications (%d)', 'ad-commander' ), count( $this->hidden_notifications ) );
			$hidden_content = '<div class="adcmdr-hidden-notifications">
						<div><button class="adcmdr-toggle-visibility">' . $button_text . '</button></div>
						<div class="adcmdr-hidden-notifications__list">' . $hidden_content . '</div>
					</div>';
		}

		$content = $visible_content . $hidden_content;

		/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
		$content .= '<p><em>' . sprintf( esc_html__( 'If you feel any of these notifications were received in error, please ignore or %1$scontact support%2$s.', 'ad-commander' ), '<a href="' . esc_url( Admin::bug_report_url() ) . '" target="_blank">', '</a>' ) . '</em></p>';

		return $content;
	}

	/**
	 * Create an individual notification.
	 *
	 * @param string $text The text to display inside a notification.
	 * @param string $key The key of the notification.
	 * @param string $title The notification title text.
	 * @param string $button The button text.
	 *
	 * @return string
	 */
	private function notification( $text, $key, $title = '', $button = '' ) {
		$classes = 'adcmdr-notification adcmdr-notice-error';
		$icon    = 'visibility';

		if ( in_array( $key, $this->hidden_notifications ) ) {
			$classes .= ' adcmdr-ignored';
			$icon     = 'hidden';
		}

		if ( $title ) {
			$title = '<strong>' . esc_html( $title ) . '</strong><br />';
		}

		return '<div class="' . esc_attr( $classes ) . '">
					<div class="adcmdr-notification-in">' .
						wpautop( $title . $text ) .
						$button .
					'</div>
					<button data-n-key="' . esc_attr( sanitize_key( $key ) ) . '"><i class="dashicons dashicons-' . esc_attr( $icon ) . '"></i></button>
				</div>';
	}

	/**
	 * Test if caching is enabled and settings aren't optimized.
	 *
	 * @return bool
	 */
	private function test_caching() {
		if ( Util::cache_detected() && Util::render_method() === 'serverside' ) {
			return array(
				/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
				'text'   => sprintf( esc_html__( 'Your site appears to use page caching and server-side rendering. This can lead to unexpected behavior. %1$sEdit your rendering settings here.%2$s', 'ad-commander' ), '<a href="' . esc_url( self::settings_admin_url( 'general' ) ) . '">', '</a>' ),
				'title'  => __( 'Page caching', 'ad-commander' ),
				'button' => Doc::doc_link( 'rendering', false, __( 'Learn more', 'ad-commander' ) ),
			);
		}

		return false;
	}

	/**
	 * Block bots + caching + server-side rendering = danger
	 *
	 * @return bool
	 */
	private function test_bots() {
		if ( Util::render_method() === 'serverside' && ( Options::instance()->get( 'bots_disable_ads', 'general', true ) || Options::instance()->get( 'bots_disable_tracking', 'tracking', true ) ) ) {
			return array(
				/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
				'text'   => sprintf( esc_html__( 'You are limiting ad display or tracking for bots, while also using server-side rendering. This can potentially cause problems. %1$sEdit your rendering settings here.%2$s', 'ad-commander' ), '<a href="' . esc_url( self::settings_admin_url( 'general' ) ) . '">', '</a>' ),
				'title'  => __( 'Bot blocking', 'ad-commander' ),
				'button' => Doc::doc_link( 'bots', false, __( 'Learn more', 'ad-commander' ) ),
			);
		}

		return false;
	}

	/**
	 * Test if consent is enabled and has server-side rendermethod
	 *
	 * @return bool
	 */
	private function test_consent() {
		if ( Consent::instance()->requires_consent() !== false && Util::render_method() === 'serverside' ) {
			return array(
				/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
				'text'   => sprintf( esc_html__( 'Consent management only partially works with server-side rendering. %1$sEdit your rendering settings here.%2$s', 'ad-commander' ), '<a href="' . esc_url( self::settings_admin_url( 'general' ) ) . '">', '</a>' ),
				'title'  => __( 'Consent required', 'ad-commander' ),
				'button' => Doc::doc_link( 'requiring_consent', false, __( 'Learn more', 'ad-commander' ) ),
			);
		}

		return false;
	}

	/**
	 * Test if generating custom CSS has failed.
	 *
	 * @return bool
	 */
	private function test_custom_css_failure() {
		if ( Options::instance()->get( 'custom_css_failure', null, true, false ) ) {
			return array(
				'text'  => sprintf( esc_html__( 'Your site failed to generate CSS for your custom prefix. This is likely due to permissions in your hosting environment. Your CSS prefix was reset to the default.', 'ad-commander' ), '<a href="' . esc_url( self::settings_admin_url( 'general' ) ) . '">', '</a>' ),
				'title' => __( 'Custom CSS Prefix', 'ad-commander' ),
			);
		}

		return false;
	}

	public function test_adsense_alerts() {
		if ( Options::instance()->get( 'disable_adsense_account_alerts', 'adsense', true ) ) {
			return false;
		}

		$admin_instance = AdminAdSense::instance();
		$accounts       = $admin_instance->get_adsense_api_account();
		$pub_id         = AdSense::instance()->current_adsense_publisher_id();

		if ( $pub_id && $accounts && isset( $accounts['accounts'] ) && ! empty( $accounts['accounts'] ) && isset( $accounts['accounts'][ $pub_id ] ) && isset( $accounts['accounts'][ $pub_id ]['alerts'] ) && ! empty( $accounts['accounts'][ $pub_id ]['alerts'] ) ) {
			$alerts = array();

			if ( ! empty( $accounts['accounts'][ $pub_id ]['alerts']['items'] ) ) {
				foreach ( $accounts['accounts'][ $pub_id ]['alerts']['items'] as $alert ) {
					$alerts[] = array(
						'id'    => esc_html( $alert['name'] ),
						/* translators: %1$s AdSense publisher ID. */
						'title' => sprintf( esc_html__( 'AdSense Account (%1$s)', 'ad-commander' ), $pub_id ) . ' &ndash; ' . esc_html( $alert['severity'] ),
						'text'  => esc_html( $alert['message'] ),
					);
				}
			}

			if ( isset( $accounts['accounts'][ $pub_id ]['alerts']['last_refresh'] ) ) {
				if ( $admin_instance->should_resync_adsense_alerts( $accounts['accounts'][ $pub_id ]['alerts']['last_refresh'] ) ) {
					$admin_instance->update_account_alerts();
				}
			}

			return $alerts;
		}

		return false;
	}
}
