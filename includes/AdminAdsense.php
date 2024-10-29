<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Admin functionality for connecting AdSense
 */
class AdminAdSense extends Admin {

	const ADCMDR_DEFAULT_CLIENT_ID     = '668677518280-ja8831au5qsq4q9eeijvlp4v4nvciujp.apps.googleusercontent.com';
	const ADCMDR_DEFAULT_CLIENT_SECRET = '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';
	const ADCMDR_HOSTED_APP_URL        = 'https://auth.wpadcommander.com/adsense.php';

	/**
	 * An instance of this class.
	 *
	 * @var null|AdminAdSense
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return AdminAdSense
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'load-ad-commander_page_ad-commander-settings', array( $this, 'maybe_do_adsense_action' ) );

		foreach ( $this->get_action_keys() as $key ) {
			$key_underscore = self::_key( $key );
			add_action( 'wp_ajax_' . $this->action_string( $key ), array( $this, 'action_' . $key_underscore ) );
		}

		add_action( 'adcmdr_adsense_publisher_id_changed', array( $this, 'update_adsense_pub_id_where_missing' ), 10, 1 );

		add_action( 'admin_init', array( $this, 'schedule_event_maybe_sync_adsense_alerts' ) );
		add_action( Util::ns( 'maybe_sync_adsense_alerts', '_' ), array( $this, 'maybe_sync_adsense_alerts' ) );
	}

	/**
	 * Schedule the maybe_sync_adsense_alerts event.
	 */
	public function schedule_event_maybe_sync_adsense_alerts() {
		if ( ! wp_next_scheduled( Util::ns( 'maybe_sync_adsense_alerts', '_' ) ) ) {
			if ( ! Options::instance()->get( 'disable_adsense_account_alerts', 'adsense', true ) ) {
				wp_schedule_event( time(), 'daily', Util::ns( 'maybe_sync_adsense_alerts', '_' ) );
			}
		}
	}

	/**
	 * Maybe sync alerts.
	 *
	 * @return void
	 */
	public function maybe_sync_adsense_alerts() {
		/**
		 * Only sync alerts if this is an admin visit. We don't want to slow down a front-end visit with an API call.
		 * This is scheduled by cron, so it's possible visitors will keep triggering it and the cron won't run.
		 *
		 * We're also going to check if a refresh is needed when ads sync or when notifications are built, so there are other times these will be updated.
		 */
		if ( ! is_admin() ) {
			return;
		}

		if ( $this->should_resync_adsense_alerts() ) {
			$this->update_account_alerts();
		}
	}

	/**
	 * Determine if an account is ready for an alert-resync
	 *
	 * @param bool|int $last_refresh The last refresh timestamp.
	 *
	 * @return bool
	 */
	public function should_resync_adsense_alerts( $last_refresh = false ) {
		if ( Options::instance()->get( 'disable_adsense_account_alerts', 'adsense', true ) ) {
			return false;
		}

		if ( ! $last_refresh ) {
			$pub_id            = AdSense::instance()->current_adsense_publisher_id();
			$adsense_connected = AdminAdsense::instance()->has_access_token( $pub_id );

			if ( $pub_id && $adsense_connected ) {
				$accounts = self::get_adsense_api_account();

				if ( $accounts && isset( $accounts['accounts'][ $pub_id ] ) && isset( $accounts['accounts'][ $pub_id ]['alerts'] ) ) {
					if ( ! isset( $accounts['accounts'][ $pub_id ]['alerts']['last_refresh'] ) ) {
						return true;
					}

					if ( time() >= ( $accounts['accounts'][ $pub_id ]['alerts']['last_refresh'] + DAY_IN_SECONDS ) ) {
						return true;
					}
				}
			}
		} elseif ( $last_refresh && time() >= ( $last_refresh + DAY_IN_SECONDS ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Hosted app client ID
	 *
	 * TODO: In the future, consider making the default client ID is different for Pro vs non-pro users.
	 * This would mean not using constants here or when checking if it's a native app.
	 *
	 * @return string
	 */
	private function client_id() {
		return self::ADCMDR_DEFAULT_CLIENT_ID;
	}

	/**
	 * Hosted app client secret
	 *
	 * @return string
	 */
	private function client_secret() {
		return self::ADCMDR_DEFAULT_CLIENT_SECRET;
	}

	/**
	 * Hosted app URL
	 *
	 * @return string
	 */
	private function hosted_app_url() {
		return self::ADCMDR_HOSTED_APP_URL;
	}

	/**
	 * Determine if this is native or user credentials.
	 *
	 * @return bool
	 */
	public function is_user_credentials() {
		return ( $this->client_id() !== self::ADCMDR_DEFAULT_CLIENT_ID ) && ( $this->client_secret() !== self::ADCMDR_DEFAULT_CLIENT_SECRET );
	}

	/**
	 * Google auto URL
	 *
	 * @return string
	 */
	private static function google_auth_url() {
		return 'https://accounts.google.com/o/oauth2/v2/auth';
	}

	/**
	 * Google token URL
	 *
	 * @return string
	 */
	private static function google_token_url() {
		return 'https://www.googleapis.com/oauth2/v4/token';
	}

	/**
	 * Google scope URL
	 *
	 * @return string
	 */
	private static function google_scope_readonly_url() {
		return 'https://www.googleapis.com/auth/adsense.readonly';
	}

	/**
	 * Gets the current AdSense API account details from the database.
	 *
	 * @return bool|array
	 */
	public static function get_adsense_api_account() {
		return Options::instance()->get( 'adsense_api' );
	}

	/**
	 * Retrieve URL to AdSense dashboard. Optionally to ad units.
	 *
	 * @param bool $publisher_id The publisher ID.
	 * @param bool $ad_units Whether to link to ad units or just home screen.
	 *
	 * @return string
	 */
	public static function adsense_dashboard_url( $publisher_id = false, $ad_units = false ) {
		$adsense_url = 'https://www.google.com/adsense/new/u/2/';

		if ( $publisher_id ) {
			$adsense_url .= $publisher_id;

			if ( $ad_units ) {
				$adsense_url .= '/myads/units';
			} else {
				$adsense_url .= '/home';
			}
		}

		return sanitize_url( $adsense_url );
	}

	/**
	 * Get necessary action keys, which will be used to create wp_ajax hooks.
	 *
	 * @param bool $ads_only Only load actions for ads, not API access.
	 *
	 * @return array
	 */
	private function get_action_keys( $ads_only = false ) {
		$actions = array(
			'get-ad-units',
			'get-ad-by-code',
		);

		if ( $ads_only ) {
			return $actions;
		}

		return array_merge( $actions, array( 'revoke-api-access' ) );
	}

	/**
	 * Creates an array of all of the necessary actions.
	 *
	 * @param bool $ads_only Only load actions for ads, not API access.
	 *
	 * @return array
	 */
	public function get_ajax_actions( $ads_only = false ) {
		$actions = array();

		foreach ( $this->get_action_keys( $ads_only ) as $key ) {
			$actions[ self::_key( $key ) ] = array(
				'action'   => $this->action_string( $key ),
				'security' => wp_create_nonce( $this->nonce_string( $key ) ),
			);
		}

		return $actions;
	}

	/**
	 * Enqueue the adsense admin script.
	 * This should only be called when we know we're on the settings page.
	 * So, for example, Admin->admin_enqueue_scripts() currently calls this function.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		if ( ! isset( $_GET['tab'] ) || sanitize_text_field( $_GET['tab'] ) !== 'adcmdr_adsense' ) {
			return;
		}

		$settings = array(
			'auth_url' => $this->get_auth_url(),
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'actions'  => $this->get_ajax_actions(),
		);

		$handle = Util::ns( 'admin-adsense' );

		wp_register_script(
			$handle,
			AdCommander::assets_url() . 'js/settings-adsense.js',
			array( 'jquery', Util::ns( 'settings' ) ),
			AdCommander::version(),
			array( 'in_footer' => true )
		);

		wp_enqueue_script( $handle );

		Util::enqueue_script_data( $handle, $settings );
	}

	/**
	 * Create nonce for converting adsense code to tokens.
	 *
	 * @return string
	 */
	private function create_adsense_token_nonce() {
		return wp_create_nonce( $this->nonce_string( 'adsense-code-to-token' ) );
	}

	/**
	 * Fires during adcmdr_adsense_publisher_id_changed hook.
	 * Do any updating of AdSense ads - such as adding a publisher ID.
	 *
	 * @param mixed $new_pub_id The new publisher ID.
	 */
	public function update_adsense_pub_id_where_missing( $new_pub_id ) {

		if ( ! $new_pub_id || ! AdSense::instance()->is_publisher_id_valid( $new_pub_id ) ) {
			return;
		}

		$wo_meta            = new WOMeta( AdCommander::ns() );
		$adsense_pub_id_key = $wo_meta->make_key( 'adsense_ad_pub_id' );
		$ad_type_key        = $wo_meta->make_key( 'adtype' );

		add_filter( 'posts_where', array( $this, 'where_null_ad_pub_ids' ) );
		$ad_query = new \WP_Query(
			array(
				'post_type'      => AdCommander::posttype_ad(),
				'post_status'    => Util::any_post_status(),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'desc',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- necessary, rarely run meta_query.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => $ad_type_key,
						'value'   => 'adsense',
						'compare' => '=',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => $adsense_pub_id_key,
							'compare' => '=',
							'value'   => '',
						),
						array(
							'key'     => $adsense_pub_id_key,
							'compare' => 'NOT EXISTS',
						),
					),
				),
			)
		);
		remove_filter( 'posts_where', array( $this, 'where_null_ad_pub_ids' ) );

		if ( $ad_query->have_posts() ) {
			foreach ( $ad_query->posts as $ad ) {
				update_post_meta( $ad->ID, $adsense_pub_id_key, $new_pub_id );
			}
		}
	}

	/**
	 * Adds additional mta_value IS NULL where clause to query from $this->adsense_publisher_id_changed().
	 * Could not get this working with just WP_Query builder.
	 *
	 * @param string $where Current where clause.
	 *
	 * @return string
	 */
	public function where_null_ad_pub_ids( $where ) {
		$meta1 = "( mt1.meta_key = '_adcmdr_adsense_ad_pub_id' AND mt1.meta_value = '' )";

		if ( stripos( $where, $meta1 ) !== false ) {
			$meta2 = $meta1 . " OR ( mt1.meta_key = '_adcmdr_adsense_ad_pub_id' AND mt1.meta_value IS NULL )";
			$where = str_ireplace( $meta1, $meta2, $where );
		}

		return $where;
	}

	/**
	 * URL to Google's oauth2, with additional state information
	 *
	 * @return string
	 */
	public function get_auth_url() {
		/**
		 * Current state
		 */
		$return_url = self::settings_admin_url( 'adsense' );
		$return_url = add_query_arg(
			array(
				'action' => 'adcmdr-adsense-code-to-token',
			),
			$return_url
		);

		$state = array(
			'adcmdr_v'   => AdCommander::version(),
			'nonce'      => $this->create_adsense_token_nonce(),
			'return_url' => $return_url,
		);

		/**
		 * Google Auth
		 */
		$url = self::google_auth_url();

		$url = add_query_arg(
			array(
				'scope'                  => rawurlencode( self::google_scope_readonly_url() ),
				'client_id'              => $this->client_id(),
				'redirect_uri'           => rawurlencode( $this->hosted_app_url() ),
				'state'                  => rawurlencode( base64_encode( wp_json_encode( $state ) ) ),
				'access_type'            => 'offline',
				'include_granted_scopes' => 'true',
				'prompt'                 => 'consent',
				'response_type'          => 'code',
			),
			$url
		);

		return $url;
	}

	/**
	 * AJAX function to Revoke API access.
	 *
	 * @return void
	 */
	public function action_revoke_api_access() {

		$action = 'revoke-api-access';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$this->revoke_access( Options::instance()->get( 'adsense_account', 'adsense' ) );

		wp_send_json_success(
			array(
				'action'   => $action,
				'redirect' => self::settings_admin_url( 'adsense' ),
			)
		);
		wp_die();
	}

	/**
	 * Maybe run $this->connect_adsense_by_code() if we have received a code back from the hosted app.
	 *
	 * @return void
	 */
	public function maybe_do_adsense_action() {

		/**
		 * Only execute if we're on the right settings tab, have the right action, and pass security checks.
		 */
		if ( ! isset( $_GET['tab'] ) || sanitize_text_field( wp_unslash( $_GET['tab'] ) ) !== 'adcmdr_adsense' ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || sanitize_text_field( wp_unslash( $_GET['action'] ) ) !== Util::ns( 'adsense-code-to-token' ) ) {
			return;
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), $this->nonce_string( 'adsense-code-to-token' ) ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( __( 'Security check', 'ad-commander' ) ) );
		}

		/**
		 * Determine if we have an error message.
		 */
		if ( isset( $_GET['adcmdr_adsense_error'] ) ) {
			switch ( sanitize_text_field( wp_unslash( $_GET['adcmdr_adsense_error'] ) ) ) {
				case 'no_code_found':
					add_action( 'admin_notices', array( $this, 'admin_notice_no_code_found' ) );
					break;

				case 'token_failed':
					add_action( 'admin_notices', array( $this, 'admin_notice_token_failed' ) );
					break;
			}

			return;
		}

		/**
		 * Or a success message.
		 */
		if ( isset( $_GET['adcmdr_adsense_success'] ) ) {
			switch ( sanitize_text_field( wp_unslash( $_GET['adcmdr_adsense_success'] ) ) ) {
				case 'account_connected':
					add_action( 'admin_notices', array( $this, 'admin_notice_account_connected' ) );
					break;
			}

			return;
		}

		/**
		 * If we have a code, connect AdSense and redirect.
		 */
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( rawurldecode( $_GET['code'] ) ) ) : false;

		if ( ! $code ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_no_code_found' ) );
			return;
		}

		$settings_url = self::settings_admin_url( 'adsense' );
		$settings_url = add_query_arg(
			array(
				'action' => 'adcmdr-adsense-code-to-token',
				'nonce'  => $this->create_adsense_token_nonce(),
			),
			$settings_url
		);

		if ( $this->connect_adsense_by_code( $code ) ) {
			$settings_url = add_query_arg(
				array(
					'adcmdr_adsense_success' => 'account_connected',
				),
				$settings_url
			);
		} else {
			$settings_url = add_query_arg(
				array(
					'adcmdr_adsense_error' => 'token_failed',
				),
				$settings_url
			);
		}

		wp_safe_redirect( $settings_url );
		exit();
	}

	/**
	 * Convert an API code to tokens and connect AdSense.
	 *
	 * @param string $code The code received from our hosted app.
	 *
	 * @return bool
	 */
	private function connect_adsense_by_code( $code ) {

		$args = array(
			'timeout' => 10,
			'body'    => array(
				'code'          => $code,
				'client_id'     => $this->client_id(),
				'client_secret' => $this->client_secret(),
				'redirect_uri'  => $this->hosted_app_url(),
				'grant_type'    => 'authorization_code',
			),
		);

		$response = wp_remote_post( self::google_token_url(), $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$token = json_decode( trim( $response['body'] ), true );

		if ( null !== $token && isset( $token['refresh_token'] ) ) {
			$account_details = $this->get_account_details( $token );

			if ( $account_details && isset( $account_details['pub_id'] ) ) {
				/**
				 * Save full account details to database.
				 */
				$this->update_api_details( $account_details['pub_id'], $token, $account_details );

				/**
				 * Save publisher ID to individual option field.
				 */
				Options::instance()->update_one( 'adsense_account', $account_details['pub_id'], 'adsense' );

				/**
				 * Get account alerts
				 */
				$this->update_account_alerts();

				return true;
			}
		}

		return false;
	}

	/**
	 * Filter unwanted alert types from adsense alerts.
	 *
	 * @param array $alerts The current alerts.
	 *
	 * @return array
	 */
	private static function filter_adsense_alerts( $alerts ) {
		if ( empty( $alerts ) || ! is_array( $alerts ) ) {
			return array();
		}

		$skip_alert_types = array(
			'sellers-json-consent',
			'reporting-horizon-legacy-data-notice',
		);

		return array_filter(
			$alerts,
			fn( $alert ) => ! isset( $alert['type'] ) || ! in_array( strtolower( str_replace( '_', '-', $alert['type'] ) ), $skip_alert_types, true )
		);
	}

	/**
	 * Get alerts from AdSense account.
	 *
	 * @return bool|array
	 */
	public function update_account_alerts() {
		$adsense_id = AdSense::instance()->current_adsense_publisher_id();

		if ( ! $adsense_id ) {
			return false;
		}

		if ( ! AdSenseRateLimiter::instance()->has_api_calls_remaining() ) {
			return false;
		}

		$now          = time();
		$url          = sanitize_url( 'https://adsense.googleapis.com/v2/accounts/' . $adsense_id . '/alerts' );
		$access_token = $this->get_access_token( $adsense_id );

		if ( ! $access_token ) {
			return false;
		}

		AdSenseRateLimiter::instance()->decrease_remaining();
		$response = wp_remote_get( $url, array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$accounts         = self::get_adsense_api_account();
		$processed_alerts = array();

		$body = trim( $response['body'] );
		if ( $body !== '{}' ) {

			$response_body = json_decode( $body, true );
			$alerts        = isset( $response_body['alerts'] ) && $response_body['alerts'] && ! empty( $response_body['alerts'] ) ? $response_body['alerts'] : array();

			if ( ! empty( $alerts ) ) {
				foreach ( $alerts as $alert ) {
					// process alert for saving to options.
					$name = sanitize_text_field( $alert['name'] );
					$key  = $name;

					if ( strpos( $key, '/' ) !== false ) {
						$key = explode( '/', $key );
						$key = $key[ count( $key ) - 1 ];
					}

					$processed_alerts[ $key ] = array(
						'name'      => $key,
						'name_full' => $name,
						'severity'  => sanitize_text_field( $alert['severity'] ),
						'message'   => sanitize_text_field( $alert['message'] ),
						'type'      => sanitize_text_field( $alert['type'] ),
					);
				}

				$processed_alerts = self::filter_adsense_alerts( $processed_alerts );
			}
		}

		$accounts['accounts'][ $adsense_id ]['alerts'] = array(
			'items'        => $processed_alerts,
			'last_refresh' => $now,
		);

		Options::instance()->update( 'adsense_api', $accounts );

		return $accounts;
	}

	/**
	 * Get account details using a token.
	 *
	 * @param array $token The current token.
	 *
	 * @return bool|array
	 */
	private function get_account_details( $token ) {
		$url = 'https://adsense.googleapis.com/v2/accounts';
		// $list_child_url = $url . '/%pubid%:listChildAccounts';

		$headers  = array( 'Authorization' => 'Bearer ' . $token['access_token'] );
		$response = wp_remote_get( $url, array( 'headers' => $headers ) );

		if ( is_wp_error( $response ) || ! isset( $response['body'] ) ) {
			return false;
		}

		$body = trim( $response['body'] );

		if ( $body === '{}' ) {
			return false;
		}

		$accounts = json_decode( $body, true );

		if ( isset( $accounts['accounts'] ) ) {
			$parent_account = $accounts['accounts'][0];
			$pub_id         = explode( '/', $parent_account['name'] )[1];

			return array(
				'pub_id'       => sanitize_text_field( $pub_id ),
				'account_name' => sanitize_text_field( $parent_account['displayName'] ),
			);

			/*
			$child_accounts = wp_remote_get( str_replace( '%pubid%', $pub_id, $list_child_url ), array( 'headers' => $headers ) );

			if ( is_wp_error( $child_accounts ) ) {
				return false;
			}

			if ( trim( $child_accounts['body'] ) === '{}' ) {
				// no child accounts
			}

			// $accounts_list = json_decode( trim( $child_accounts['body'] ), true );
			// TODO: Save multiple accounts
			*/
		}

		return false;
	}

	/**
	 * Update API details for a specific AdSense account.
	 *
	 * @param string $adsense_id The publisher ID.
	 * @param array  $token The API tokens.
	 * @param array  $account_details Option account details if we are just connecting this account.
	 *
	 * @return bool|array
	 */
	private function update_api_details( $adsense_id, $token, $account_details = array() ) {
		if ( ! isset( $token['expires_in'] ) || ! isset( $token['refresh_token'] ) ) {
			return false;
		}

		$token['expires'] = time() + absint( $token['expires_in'] );

		foreach ( array( 'expires_in', 'scope' ) as $unset ) {
			if ( isset( $token[ $unset ] ) ) {
				unset( $token[ $unset ] );
			}
		}

		$options     = Options::instance();
		$adsense_api = $options->get( 'adsense_api' );

		if ( ! $adsense_api ) {
			$adsense_api = array( 'accounts' => array( $adsense_id => array() ) );
		} elseif ( isset( $adsense_api['accounts'][ $adsense_id ]['app'] ) ) {
			unset( $adsense_api['accounts'][ $adsense_id ]['app'] );
		}

		/**
		 * Tokens
		 */
		$adsense_api['accounts'][ $adsense_id ]['app'] = $token;

		/**
		 * Account details
		 */
		if ( ! empty( $account_details ) ) {
			$adsense_api['accounts'][ $adsense_id ]['account_details'] = $account_details;
		}

		/**
		 * Default values
		 */
		if ( ! isset( $adsense_api['ad_codes'] ) ) {
			$adsense_api['ad_codes'] = array();
		}

		if ( ! isset( $adsense_api['accounts'][ $adsense_id ]['account_details'] ) ) {
			$adsense_api['accounts'][ $adsense_id ]['account_details'] = array();
		}

		if ( ! isset( $adsense_api['accounts'][ $adsense_id ]['ad_units'] ) ) {
			$adsense_api['accounts'][ $adsense_id ]['ad_units'] = array();
		}

		if ( ! isset( $adsense_api['accounts'][ $adsense_id ]['unavailable_ad_code'] ) ) {
			$adsense_api['accounts'][ $adsense_id ]['unavailable_ad_code'] = array();
		}

		if ( ! isset( $adsense_api['accounts'][ $adsense_id ]['alerts'] ) ) {
			$adsense_api['accounts'][ $adsense_id ]['alerts'] = array();
		}

		/**
		 * Update database
		 */
		$options->update( 'adsense_api', $adsense_api );

		return $adsense_api['accounts'][ $adsense_id ];
	}

	/**
	 * Revoke access for a specific account.
	 *
	 * @param string $adsense_id The publisher ID to revoke.
	 *
	 * @return bool|void
	 */
	protected function revoke_access( $adsense_id ) {

		$accounts = self::get_adsense_api_account();

		if ( ! isset( $accounts['accounts'][ $adsense_id ] ) ) {
			return false;
		}

		$token = isset( $accounts['accounts'][ $adsense_id ]['app'] ) ? $accounts['accounts'][ $adsense_id ]['app'] : false;

		unset( $accounts['accounts'][ $adsense_id ] );
		Options::instance()->update( 'adsense_api', $accounts );

		if ( $token && isset( $token['refresh_token'] ) ) {

			$url  = 'https://accounts.google.com/o/oauth2/revoke?token=' . $token['refresh_token'];
			$args = array(
				'timeout' => 5,
				'header'  => array( 'Content-type' => 'application/x-www-form-urlencoded' ),
			);

			wp_remote_post( $url, $args );
		}
	}

	/**
	 * Determine if we have an access token in the database.
	 *
	 * @param string $adsense_id The publisher ID.
	 *
	 * @return bool
	 */
	public function has_access_token( $adsense_id ) {
		return $this->get_token_data( $adsense_id ) !== false;
	}

	/**
	 * Get token information for a publisher ID.
	 *
	 * @param string $adsense_id The publisher ID.
	 *
	 * @return bool|array
	 */
	private function get_token_data( $adsense_id ) {
		$accounts = self::get_adsense_api_account();

		if ( ! isset( $accounts['accounts'][ $adsense_id ] ) ||
			! isset( $accounts['accounts'][ $adsense_id ]['app'] ) ||
			empty( $accounts['accounts'][ $adsense_id ]['app'] ) ||
			! $accounts['accounts'][ $adsense_id ]['app']['refresh_token'] ) {
				return false;
		}

		return $accounts['accounts'][ $adsense_id ]['app'];
	}

	/**
	 * Renew an existing access token.
	 *
	 * @param string $adsense_id The publisher ID.
	 * @param array  $token The current token.
	 *
	 * @return bool|array
	 */
	private function renew_access_token( $adsense_id, $token = false ) {
		if ( ! $token ) {
			$token = $this->get_token_data( $adsense_id );
		}

		$refresh_token = isset( $token['refresh_token'] ) ? $token['refresh_token'] : false;

		if ( $refresh_token ) {
			$args = array(
				'body' => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $this->client_id(),
					'client_secret' => $this->client_secret(),
					'grant_type'    => 'refresh_token',
				),
			);

			$response = wp_remote_post( self::google_token_url(), $args );

			if ( ! is_wp_error( $response ) ) {
				$tokens = json_decode( $response['body'], true );

				if ( null !== $tokens && isset( $tokens['expires_in'] ) ) {
					if ( ! isset( $tokens['refresh_token'] ) ) {
						$tokens['refresh_token'] = $refresh_token;
					}

					if ( $account = $this->update_api_details( $adsense_id, $tokens ) ) {
						return $account['app'];
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get the access token from token data for a publisher ID.
	 * Possibly renew it if expired.
	 *
	 * @param string $adsense_id The publisher ID.
	 *
	 * @return string|bool
	 */
	protected function get_access_token( $adsense_id ) {
		$token = $this->get_token_data( $adsense_id );

		if ( ! $token ) {
			return false;
		}

		if ( time() > intval( $token['expires'] ) ) {
			$token = $this->renew_access_token( $adsense_id, $token );
		}

		return isset( $token['access_token'] ) ? $token['access_token'] : false;
	}

	/**
	 * Ajax request to fetch ad units.
	 *
	 * @return void|
	 */
	public function action_get_ad_units() {
		$action = 'get-ad-units';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die( esc_html( __( 'Security check', 'ad-commander' ) ) );
		}

		$force_refresh = isset( $_REQUEST['force_refresh'] ) ? Util::truthy( sanitize_text_field( wp_unslash( $_REQUEST['force_refresh'] ) ) ) : false;

		if ( $force_refresh ) {
			$adsense_id = AdSense::instance()->current_adsense_publisher_id();
		} else {
			$adsense_id = isset( $_REQUEST['adsense_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['adsense_id'] ) ) : AdSense::instance()->current_adsense_publisher_id();
		}

		if ( ! $adsense_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your publishder ID is invalid.', 'ad-commander' ),
					'quota'   => array( 'remaining' => AdSenseRateLimiter::instance()->api_calls_remaining() ),
				)
			);
			wp_die();
		}

		$ads = $this->get_ads( $adsense_id, $force_refresh );

		if ( $this->should_resync_adsense_alerts() ) {
			$this->update_account_alerts();
		}

		if ( is_array( $ads ) && ! empty( $ads ) ) {
			$parsed_ads = array();
			foreach ( $ads as $key => $value ) {
				$parsed_ads[] = new AdSenseNetworkAdUnit( $value, $key, false );
			}

			wp_send_json_success(
				array(
					'ads'        => $parsed_ads,
					'adsense_id' => $adsense_id,
					'quota'      => array( 'remaining' => AdSenseRateLimiter::instance()->api_calls_remaining() ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'There was an error fetching your ads. This could mean you do not yet have any AdSense ads, or there is a problem with your account connection.', 'ad-commander' ),
					'quota'   => array( 'remaining' => AdSenseRateLimiter::instance()->api_calls_remaining() ),
				)
			);
		}

		wp_die();
	}

	/**
	 * Get ads from database or from API.
	 *
	 * @param bool|string $adsense_id The publsiher ID.
	 * @param bool        $force_refresh Force fetch from API.
	 *
	 * @return array|bool
	 */
	private function get_ads( $adsense_id = false, $force_refresh = false ) {
		if ( ! $adsense_id ) {
			$adsense_id = AdSense::instance()->current_adsense_publisher_id();
		}

		if ( ! $adsense_id ) {
			return false;
		}

		$adsense_api = self::get_adsense_api_account();

		if ( ! isset( $adsense_api['accounts'][ $adsense_id ] ) ) {
			return false;
		}

		$ad_units = $adsense_api['accounts'][ $adsense_id ]['ad_units'];

		if ( $force_refresh || ! isset( $ad_units['last_refresh'] ) || ( absint( $ad_units['last_refresh'] ) + DAY_IN_SECONDS ) <= time() ) {
			if ( AdSenseRateLimiter::instance()->has_api_calls_remaining() ) {
				$ad_units = $this->refresh_ad_units();
			}
		}

		return isset( $ad_units['ads'] ) ? $ad_units['ads'] : array();
	}

	/**
	 * Get ad units from API.
	 *
	 * @param string $adsense_id The current publisher ID.
	 *
	 * @return bool|array|string
	 */
	private function refresh_ad_units( $adsense_id = false ) {
		if ( ! $adsense_id ) {
			$adsense_id = AdSense::instance()->current_adsense_publisher_id();
		}

		if ( ! $adsense_id ) {
			return false;
		}

		if ( ! AdSenseRateLimiter::instance()->has_api_calls_remaining() ) {
			return false;
		}

		$url          = sanitize_url( 'https://adsense.googleapis.com/v2/accounts/' . $adsense_id . '/adclients/ca-' . $adsense_id . '/adunits?pageSize=350' );
		$access_token = $this->get_access_token( $adsense_id );

		if ( ! $access_token ) {
			return false;
		}

		AdSenseRateLimiter::instance()->decrease_remaining();
		$response = wp_remote_get( $url, array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = trim( $response['body'] );

		if ( $body === '{}' ) {
			// Empty account.
			return array();
		}

		$response_body = json_decode( $body, true );

		if ( null === $response_body || ! isset( $response_body['adUnits'] ) ) {
			$error = '';
			if ( ! empty( $response_body['error']['errors'] ) ) {
				foreach ( $response_body['error']['errors'] as $err ) {
					$error .= sprintf(
						'<p>%1$s %2$s<br>%3$s %4$s</p>',
						_x( 'Reason:', 'Reason of the API call error', 'ad-commander' ),
						$err['reason'],
						_x( 'Message:', 'Error message from Google', 'ad-commander' ),
						$err['message']
					);
				}
			}

			return $error;
		}

		$ad_units          = array();
		$response_ad_units = isset( $response_body['adUnits'] ) ? $response_body['adUnits'] : array();

		while ( $response_ad_units && ! empty( $response_ad_units ) ) {
			foreach ( $response_ad_units as $item ) {
				$item                    = $this->format_ad_unit( $item );
				$ad_units[ $item['id'] ] = $item;
			}

			if ( ! isset( $response_body['nextPageToken'] ) ) {
				break;
			}

			/**
			 * In case the access token expires during this loop...
			 */
			$access_token = $this->get_access_token( $adsense_id );

			if ( ! $access_token ) {
				break;
			}

			$next_url = $url . '&pageToken=' . urlencode( $response_body['nextPageToken'] );
			$headers  = array( 'Authorization' => 'Bearer ' . $access_token );

			AdSenseRateLimiter::instance()->decrease_remaining();
			$response = wp_remote_get( $next_url, array( 'headers' => $headers ) );

			if ( is_wp_error( $response ) ) {
				break;
			}

			$body = trim( $response['body'] );

			if ( $body === '{}' ) {
				break;
			}

			$response_body     = json_decode( $body, true );
			$response_ad_units = isset( $response_body['adUnits'] ) ? $response_body['adUnits'] : array();
		}

		return $this->save_ad_units( $ad_units, $adsense_id );
	}

	/**
	 * Save ad units to database.
	 *
	 * @param array    $ad_units The ad units.
	 * @param string   $adsense_id The publisher ID.
	 * @param bool|int $refresh_time The time of last refresh.
	 *
	 * @return array
	 */
	private function save_ad_units( $ad_units, $adsense_id, $refresh_time = false ) {
		$options     = Options::instance();
		$adsense_api = $options->get( 'adsense_api' );

		if ( ! isset( $adsense_api['accounts'][ $adsense_id ] ) ) {
			$adsense_api['accounts'][ $adsense_id ] = array();
		}

		$adsense_api['accounts'][ $adsense_id ]['ad_units'] = array(
			'last_refresh' => $refresh_time ? $refresh_time : time(),
			'ads'          => $ad_units,
		);

		$options->update( 'adsense_api', $adsense_api );

		return $adsense_api['accounts'][ $adsense_id ]['ad_units'];
	}

	/**
	 * Parse an ad unit into a formatted array.
	 *
	 * @param array $ad_unit The ad unit from Google.
	 *
	 * @return array
	 */
	private function format_ad_unit( $ad_unit ) {
		$parts = explode( '/', $ad_unit['name'] );

		return array(
			'display_name'         => isset( $ad_unit['displayName'] ) ? $ad_unit['displayName'] : $ad_unit['name'],
			'name'                 => $ad_unit['name'],
			'id'                   => $ad_unit['reportingDimensionId'],
			'code'                 => $parts[ count( $parts ) - 1 ],
			'status'               => $ad_unit['state'],
			'contentAdsSettings'   => $ad_unit['contentAdsSettings'],
			'reportingDimensionId' => $ad_unit['reportingDimensionId'],
		);
	}

	/**
	 * Ajax request to fetch an ad code
	 *
	 * @return void|
	 */
	public function action_get_ad_by_code() {
		$action = 'get-ad-by-code';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die( esc_html( __( 'Security check', 'ad-commander' ) ) );
		}

		$ad_id      = isset( $_REQUEST['ad_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ad_id'] ) ) : false;
		$adsense_id = isset( $_REQUEST['adsense_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['adsense_id'] ) ) : AdSense::instance()->current_adsense_publisher_id();

		if ( ! $ad_id || ! $adsense_id ) {
			wp_die();
		}

		$ad = $this->get_ad_with_code( $ad_id, $adsense_id );

		if ( $this->should_resync_adsense_alerts() ) {
			$this->update_account_alerts();
		}

		if ( $ad ) {
			wp_send_json_success(
				array(
					'ad'         => $ad,
					'adsense_id' => $adsense_id,
					'quota'      => array( 'remaining' => AdSenseRateLimiter::instance()->api_calls_remaining() ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'There was an error retrieving this ad. This could mean the ad no longer exists, there is a problem with your account connection, or we were unable to connect to the Google AdSense API.', 'ad-commander' ),
					'quota'   => array( 'remaining' => AdSenseRateLimiter::instance()->api_calls_remaining() ),
				)
			);
		}

		wp_die();
	}

	/**
	 * Updates the unavailable_ad_code array and saves it to the database.
	 *
	 * @param mixed $accounts The current accounts array.
	 * @param mixed $ad_id The ID for this ad.
	 * @param mixed $mode Whether we're adding or removing the code from the array.
	 *
	 * @return void
	 */
	private function update_unavailable_ad_code( $accounts, $ad_id, $mode ) {
		if ( ! isset( $accounts['unavailable_ad_code'] ) ) {
			$accounts['unavailable_ad_code'] = array();
		}

		if ( $mode === 'add' && ! in_array( $ad_id, $accounts['unavailable_ad_code'], true ) ) {
			$accounts['unavailable_ad_code'][] = $ad_id;
		} elseif ( $mode === 'remove' && in_array( $ad_id, $accounts['unavailable_ad_code'], true ) ) {
			$accounts['unavailable_ad_code'] = array_values( array_filter( $accounts['unavailable_ad_code'], fn( $e ) => $e !== $ad_id ) );
		}

		Options::instance()->update( 'adsense_api', $accounts );
	}

	/**
	 * Get an ad, and also set the code for the ad.
	 *
	 * @param string      $ad_id The ad ID.
	 * @param bool|string $adsense_id The publishder ID.
	 *
	 * @return bool|object
	 */
	private function get_ad_with_code( $ad_id, $adsense_id = false ) {
		if ( ! $adsense_id ) {
			$adsense_id = AdSense::instance()->current_adsense_publisher_id();
		}

		/**
		 * We don't actually use our code here, but this function is setting the ad code array and we may need that.
		 */
		$code = $this->get_ad_code( $ad_id, $adsense_id );

		/**
		 * Get our ad unit
		 */
		$ad_units = AdSense::instance()->get_google_ad_units( self::get_adsense_api_account(), $adsense_id, $ad_id, false );

		if ( ! isset( $ad_units[ $ad_id ] ) ) {
			return false;
		}

		$ad_unit = $ad_units[ $ad_id ];

		/**
		 * No code was set, but we need a code for this ad.
		 */
		if ( in_array( $ad_unit->type, AdSense::available_ad_code_types(), true ) && ! $ad_unit->ad_code ) {
			return false;
		}

		return $ad_units[ $ad_id ];
	}

	/**
	 * Get an ad code from the API.
	 *
	 * @param string      $ad_id The ad ID.
	 * @param bool|string $adsense_id The publishder ID.
	 *
	 * @return bool|string
	 */
	private function get_ad_code( $ad_id, $adsense_id = false ) {
		if ( ! $adsense_id ) {
			$adsense_id = AdSense::instance()->current_adsense_publisher_id();
		}

		$ad_id   = trim( $ad_id );
		$slot_id = explode( ':', $ad_id )[1];

		if ( ! $slot_id ) {
			return false;
		}

		/**
		 * Get the access token first in case it is refreshed.
		 */
		$access_token = $this->get_access_token( $adsense_id );
		$accounts     = self::get_adsense_api_account();
		$ad_units     = AdSense::instance()->get_google_ad_units( $accounts, $adsense_id, $ad_id );

		if ( ! isset( $ad_units[ $ad_id ] ) ) {
			return false;
		}

		$ad_unit = $ad_units[ $ad_id ];

		if ( ! isset( $accounts['ad_codes'] ) ) {
			$accounts['ad_codes'] = array();
		}

		if ( ! $ad_unit->ad_code || $ad_unit->ad_code === '' ) {
			$this->update_unavailable_ad_code( $accounts, $ad_id, 'add' );

			/**
			 * Only end if this is an unsupported ad type or one that we know does not have ad codes over the API.
			 * This avoids a situation where maybe a supported ad type couldn't reach the API for some reason,
			 * and it keeps us from wasting API calls on unsupported ad types.
			 */
			if ( $ad_unit->unsupported || ! in_array( $ad_unit->type, AdSense::available_ad_code_types(), true ) ) {
				return false;
			}
		} else {
			$this->update_unavailable_ad_code( $accounts, $ad_id, 'remove' );
		}

		if ( ! AdSenseRateLimiter::instance()->has_api_calls_remaining() ) {
			return false;
		}

		if ( ! $access_token ) {
			return false;
		}

		AdSenseRateLimiter::instance()->decrease_remaining();

		$url      = sanitize_url( 'https://adsense.googleapis.com/v2/accounts/' . $adsense_id . '/adclients/ca-' . $adsense_id . '/adunits/' . $slot_id . '/adcode' );
		$headers  = array( 'Authorization' => 'Bearer ' . $access_token );
		$response = wp_remote_get( $url, array( 'headers' => $headers ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$ad_code = json_decode( $response['body'], true );

		if ( ! $ad_code || ! isset( $ad_code['adCode'] ) ) {
			if ( isset( $ad_code['error'] ) &&
				isset( $ad_code['error']['errors'] ) &&
				$ad_code['error'] &&
				$ad_code['error']['errors'] &&
				isset( $ad_code['error']['errors'][0]['reason'] ) &&
				$ad_code['error']['errors'][0]['reason'] === 'doesNotSupportAdUnitType'
			) {
				$this->update_unavailable_ad_code( $accounts, $ad_id, 'add' );
			}

			return false;
		}

		$accounts['ad_codes'][ $ad_id ] = $ad_code['adCode'];
		/**
		 * This will also save the new ad code to our accounts object.
		 */
		$this->update_unavailable_ad_code( $accounts, $ad_id, 'remove' );

		return $ad_code['adCode'];
	}

	/**
	 * Admin notice that an AdSense acocunt has been connected.
	 *
	 * @return void
	 */
	public function admin_notice_account_connected() {
		?>
			<div class="notice notice-success">
				<p>
					<?php esc_html_e( 'Your AdSense account has been successfully connected.', 'ad-commander' ); ?>
				</p>
			</div>
		<?php
	}

	/**
	 * Admin notice that an AdSense code was not found.
	 *
	 * @return void
	 */
	public function admin_notice_no_code_found() {
		?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Your AdSense auth code was not found. Please contact support.', 'ad-commander' ); ?>
				</p>
			</div>
		<?php
	}

	/**
	 * Admin notice that connecting AdSense failed.
	 *
	 * @return void
	 */
	public function admin_notice_token_failed() {
		?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'There was an error connecting your AdSense account. Please make sure the Google account you are connecting is associated with an AdSense publisher account. Contact support for help.', 'ad-commander' ); ?>
				</p>
			</div>
		<?php
	}
}
