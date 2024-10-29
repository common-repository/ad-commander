<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Check for needed onboarding messages
 */
class AdminOnboarding extends Admin {

	/**
	 * An instance of this class.
	 *
	 * @var null|AdminOnboarding
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return AdminOnboarding
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
			'dismiss-onboarding',
			'create-demo-ad',
		);
	}

	/**
	 * Creates an array of all of the necessary actions.
	 *
	 * @return array
	 */
	public function get_ajax_actions() {
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
	 * AJAX function to to dismiss onboarding
	 *
	 * @return void
	 */
	public function action_dismiss_onboarding() {

		$action = 'dismiss-onboarding';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		$type = isset( $_REQUEST['disableob'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['disableob'] ) ) : 'global';
		$this->set_onboarded( $type );

		wp_send_json_success(
			array(
				'action' => $action,
			)
		);

		wp_die();
	}

	/**
	 * AJAX function to to create a demo ad
	 *
	 * @return void
	 */
	public function action_create_demo_ad() {
		$action = 'create-demo-ad';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		$url         = false;
		$new_post_id = $this->create_demo_ad();

		if ( $new_post_id && $new_post_id > 0 ) {
			$url = Admin::edit_ad_post_url( $new_post_id );
		}

		if ( ! $url ) {
			wp_send_json_error(
				array(
					'action' => $action,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'action' => $action,
					'url'    => sanitize_url( $url ),
				)
			);
		}

		wp_die();
	}

	/**
	 * Create a demo ad.
	 *
	 * @return int|bool|WP_Error
	 */
	private function create_demo_ad() {
		$wo_meta = new WOMeta( AdCommander::ns() );

		$new_post_id = wp_insert_post(
			array(
				'post_title'  => esc_html__( 'Demo Ad', 'ad-commander' ) . ' | ' . get_date_from_gmt( current_time( 'mysql', true ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'post_status' => 'publish',
				'post_type'   => AdCommander::posttype_ad(),
				'meta_input'  => array(
					$wo_meta->make_key( 'adtype' )         => 'bannerad',
					$wo_meta->make_key( 'bannerurl' )      => AdCommander::public_site_url( '', false ),
					$wo_meta->make_key( 'newwindow' )      => 'site_default',
					$wo_meta->make_key( 'noopener' )       => 'site_default',
					$wo_meta->make_key( 'noreferrer' )     => 'site_default',
					$wo_meta->make_key( 'nofollow' )       => 'site_default',
					$wo_meta->make_key( 'sponsored' )      => 'site_default',
					$wo_meta->make_key( 'ad_label' )       => 'site_default',
					$wo_meta->make_key( 'responsive_banners' ) => 'site_default',
					$wo_meta->make_key( 'display_width' )  => '600',
					$wo_meta->make_key( 'display_height' ) => '500',
				),
			)
		);

		if ( $new_post_id && ! is_wp_error( $new_post_id ) ) {
			/**
			 * Create post thumbnail
			 */
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$image_id = media_sideload_image( AdCommander::assets_url() . 'img/demo-300x250.png', $new_post_id, null, 'id' );

			if ( is_int( $image_id ) ) {
				set_post_thumbnail( $new_post_id, $image_id );
			}

			/**
			 * Assign to group
			 */
			$group_name = esc_html__( 'Demo Group', 'ad-commander' );
			$term       = get_term_by( 'name', $group_name, AdCommander::tax_group(), 'ARRAY_A' );

			if ( ! $term || ! isset( $term['term_id'] ) ) {
				$term = wp_insert_term( $group_name, AdCommander::tax_group() );
			}

			if ( $term && isset( $term['term_id'] ) ) {
				wp_set_object_terms( $new_post_id, array( $term['term_id'] ), AdCommander::tax_group() );
			}
		}

		return $new_post_id;
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	protected function enqueue() {
		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		$handle = Util::ns( 'onboarding' );

		wp_register_script(
			$handle,
			AdCommander::assets_url() . 'js/onboarding.js',
			array( 'jquery' ),
			AdCommander::version(),
			array( 'in_footer' => true )
		);

		wp_enqueue_script( $handle );

		$settings = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'actions' => $this->get_ajax_actions(),
		);

		Util::enqueue_script_data( $handle, $settings );
	}

	/**
	 * Determine if the user should be onboarded for certain type of message.
	 *
	 * @param bool|string $type The type of onboarding message.
	 *
	 * @return bool
	 */
	public function should_onboard( $type = false ) {
		if ( ! current_user_can( AdCommander::capability() ) ) {
			return false;
		}

		if ( $type ) {
			return $this->needs_onboarding( $type );
		}

		return $this->needs_onboarding( 'global' ) || $this->needs_onboarding( 'ads' );
	}

	/**
	 * Determine if onboarding is needed (not disabled).
	 *
	 * @param bool|string $type The type of onboarding message.
	 *
	 * @return bool
	 */
	public function needs_onboarding( $type = 'global' ) {
		return ! Options::instance()->get( 'disable_onboarding_' . $type, 'admin', true, false );
	}

	/**
	 * Set an onboarding option to disabled.
	 *
	 * @param bool|string $type The type of onboarding message.
	 *
	 * @return void
	 */
	public function set_onboarded( $type = 'global' ) {
		Options::instance()->update_one( 'disable_onboarding_' . $type, true, 'admin' );
	}

	/**
	 * The support column HTML for onboarding messages - it's used in multiple messages.
	 *
	 * @return void
	 */
	public static function onboarding_support_column() {
		?>
		<div class="adcmdr-ob-col">
			<h3><?php esc_html_e( 'Get support', 'ad-commander' ); ?></h3>
			<ul>
			<li><a href="<?php echo esc_url( Admin::support_admin_url() ); ?>"><?php esc_html_e( 'Plugin support', 'ad-commander' ); ?> &gt;</li>
			<li><a href="<?php echo esc_url( Admin::documentation_url() ); ?>" target="_blank"><?php esc_html_e( 'Documentation', 'ad-commander' ); ?> &gt;</a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * The main/global onboarding message.
	 *
	 * @return void
	 */
	public function onboarding_notice() {
		?>
		<div class="notice adcmdr-ob-notice">
			<div class="adcmdr-ob-row adcmdr-ob-intro">
				<img src="<?php echo esc_url( AdCommander::assets_url() . 'img/logo-inverted.svg' ); ?>" alt="<?php echo esc_attr( AdCommander::title() ); ?>" class="adcmdr-logo" />
				<p><?php esc_html_e( "Thank you for installing. Let's get started!", 'ad-commander' ); ?></p>
				<a href="#" class="adcmdr-ob-dismiss"><?php esc_attr_e( 'Dismiss message', 'ad-commander' ); ?></a>
			</div>
			<div class="adcmdr-ob-row">
				<div class="adcmdr-ob-col">
					<?php
					$new_post_url = self::new_ad_post_url();

					if ( AdSense::instance()->current_adsense_publisher_id() ) {
						$args['adcmdr_default_ad_type'] = 'adsense';
					} else {
						$args['adcmdr_default_ad_type'] = 'bannerad';
					}

					$new_post_url = add_query_arg( $args, $new_post_url );
					?>
					<h3><?php esc_html_e( 'Getting started', 'ad-commander' ); ?></h3>
					<div class="adcmdr-btn-group">
						<a href="<?php echo esc_url( $new_post_url ); ?>" class="button button-primary"><?php esc_html_e( 'Create your first ad', 'ad-commander' ); ?></a>
						<a href="#" class="button button-secondary adcmdr-ob-demo"><?php esc_html_e( 'Generate a demo ad', 'ad-commander' ); ?></a>
					</div>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<ul>
						<li><a href="<?php echo esc_url( Admin::settings_admin_url() ); ?>"><?php esc_html_e( 'Configure plugin settings', 'ad-commander' ); ?> &gt;</a></li>
						<li><a href="<?php echo esc_url( Doc::doc_urls()['first_ad'] ); ?>" target="_blank"><?php esc_html_e( 'Video: Creating your first ad', 'ad-commander' ); ?> &gt;</a></li>
					</ul>
					<?php endif; ?>
				</div>
				<div class="adcmdr-ob-col">
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<h3><?php esc_html_e( 'Configure AdSense', 'ad-commander' ); ?></h3>
					<a href="<?php echo esc_url( Admin::settings_admin_url( 'adsense' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Connect AdSense account', 'ad-commander' ); ?></a>
					<ul>
						<li><a href="<?php echo esc_url( Admin::settings_admin_url( 'adsense' ) ); ?>"><?php esc_html_e( 'Configure AdSense settings', 'ad-commander' ); ?> &gt;</a></li>
						<li><a href="<?php echo esc_url( Doc::doc_urls()['adsense'] ); ?>" target="_blank"><?php esc_html_e( 'Video & Documentation: Implementing AdSense', 'ad-commander' ); ?> &gt;</a></li>
					</ul>
					<?php else : ?>
					<h3><?php esc_html_e( 'Configure plugin', 'ad-commander' ); ?></h3>
					<p><?php esc_html_e( 'Your account does not have permission to configure site options. Please have an administrator configure the plugin settings.', 'ad-commander' ); ?></p>
					<?php endif; ?>
				</div>
				<?php self::onboarding_support_column(); ?>
			</div>
		</div>
		<?php
	}
}
