<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Admin meta and related functionality for Ad posts.
 */
class AdminAdPostMeta extends Admin {
	/**
	 * The current instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * The current post's meta.
	 *
	 * @var array
	 */
	private $current_meta;

	/**
	 * __construct()
	 *
	 * Setup variables and hooks.
	 */
	public function __construct() {
		$this->wo_meta = new WOMeta( AdCommander::ns() );
		$this->nonce   = $this->nonce( basename( __FILE__ ), AdCommander::posttype_ad() );

		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_delete_meta_box_order' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'edit_form_after_title', array( $this, 'posttype_meta_boxes' ), 100 );

		add_action( 'save_post_' . AdCommander::posttype_ad(), array( $this, 'save_posted_metadata' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		/**
		 * Ad post type edit.php
		 */
		add_action( 'manage_' . AdCommander::posttype_ad() . '_posts_custom_column', array( $this, 'manage_column_data' ), 10, 3 );
		add_filter( 'manage_edit-' . AdCommander::posttype_ad() . '_columns', array( $this, 'manage_columns' ) );
		add_filter( 'manage_edit-' . AdCommander::posttype_ad() . '_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'ads_sort_pre_get_posts' ) );

		add_action( 'restrict_manage_posts', array( $this, 'ad_group_filter' ) );
		add_action( 'restrict_manage_posts', array( $this, 'ad_type_filter' ) );
		add_filter( 'parse_query', array( $this, 'filter_ads_by_type' ) );

		/**
		 * Targeting
		 */
		$this->admin_targeting()->hooks();
	}

	/**
	 * Sets the current meta if it is not yet set.
	 *
	 * @return mixed
	 */
	private function current_meta() {
		global $post;

		if ( ! $this->current_meta && isset( $post->ID ) ) {
			$this->current_meta = $this->wo_meta->get_post_meta( $post->ID, AdPostMeta::post_meta_keys() );
		}

		return $this->current_meta;
	}

	/**
	 * Create any needed admin notices.
	 *
	 * @return void
	 */
	public function admin_notices() {
		$this->notice_unfiltered_html();
		$this->notice_invalid_ad();
		$this->new_ad_notice();
	}

	/**
	 * Create notice if unfiltered HTML disabled.
	 *
	 * @return void
	 */
	private function notice_unfiltered_html() {
		if ( $this->is_screen( array( AdCommander::posttype_ad(), AdCommander::posttype_placement(), 'edit-' . AdCommander::tax_group() ) ) && ! self::allow_unfiltered_html() ) {
			$screen = get_current_screen();
			if ( $screen->base === 'edit-tags' ) {
				return;
			}
			?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Your user does not have permission to use unfiltered HTML. Scripts and some other HTML will be stripped from Text/Code ads, Rich Content ads, and custom code.', 'ad-commander' ); ?>
					<?php Doc::doc_link( 'unfiltered_html' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Create notice if ad is invalid.
	 *
	 * @return void
	 */
	private function notice_invalid_ad() {
		if ( $this->is_screen( array( AdCommander::posttype_ad() ) ) && ( $message = $this->invalid_ad_message() ) !== false ) {
			?>
			<div class="notice notice-warning">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Create a message for an invalid ad.
	 *
	 * @return string|bool
	 */
	private function invalid_ad_message() {
		global $post;

		$message = false;

		if ( ! $post || ! isset( $post->ID ) || ! $this->has_ad_saved() || ! isset( $post->post_type ) || $post->post_type !== AdCommander::posttype_ad() ) {
			return false;
		}

		$ad_type = $this->wo_meta->get_value( $this->current_meta(), 'adtype' );

		if ( ! $ad_type ) {
			$message = esc_html__( 'This ad will not display because it does not have an ad type.', 'ad-commander' );
		} elseif ( $ad_type === 'bannerad' && ! has_post_thumbnail( $post->ID ) ) {
			$message = esc_html__( 'This ad will not display because it does not yet have an image.', 'ad-commander' );
		} elseif ( ( $ad_type === 'textcode' && ! $this->wo_meta->get_value( $this->current_meta(), 'adcontent_text' ) ) ||
				( $ad_type === 'richcontent' && ! $this->wo_meta->get_value( $this->current_meta(), 'adcontent_rich' ) ) ) {
			$message = esc_html__( 'This ad will not display because it does not yet have any content.', 'ad-commander' );
		} elseif ( $ad_type === 'adsense' && ! AdSense::instance()->has_valid_ad( $this->current_meta() ) ) {
			$message = esc_html__( 'This ad will not display because it is missing some AdSense data. Please check your settings.', 'ad-commander' );
		}

		return $message;
	}

	/**
	 * Possibly delete a user's meta box order due to an ACF conflict.
	 *
	 * @return void
	 */
	public function maybe_delete_meta_box_order() {
		/**
		 * User meta box order doesn't work right with our meta box setup if ACF is active.
		 * Not enqueuing anything here, but want to delete this early on in the page load.
		 * Without this, sorted meta boxes end up at the bottom.
		 */
		if ( $this->is_screen( array( AdCommander::posttype_ad() ) ) ) {
			$meta_box_order = get_user_meta( get_current_user_id(), 'meta-box-order_' . AdCommander::posttype_ad(), true );
			if ( $meta_box_order && isset( $meta_box_order['acf_after_title'] ) ) {
				delete_user_meta( get_current_user_id(), 'meta-box-order_' . AdCommander::posttype_ad() );
			}
		}
	}

	/**
	 * Enqueue scripts if we're on a screen that needs them.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( $this->is_screen( array( AdCommander::posttype_ad() ) ) ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-datepicker' );

			$this->enqueue_jquery_ui_styles();

			$settingrestrict_handle = $this->enqueue_setting_restrict();
			$repeater_handle        = $this->wo_meta->repeater_enqueue();
			$targeting_handle       = $this->admin_targeting()->enqueue();

			$deps = array(
				'jquery',
				'jquery-ui-core',
				'jquery-ui-datepicker',
				$settingrestrict_handle,
				$repeater_handle,
				$targeting_handle,
			);

			$handle = Util::ns( 'adpost' );

			wp_register_script(
				$handle,
				AdCommander::assets_url() . 'js/ad-post.js',
				$deps,
				AdCommander::version(),
				array( 'in_footer' => true )
			);

			wp_enqueue_script( $handle );

			Util::enqueue_script_data(
				$handle,
				array(
					'media_rest_url' => get_rest_url( null, 'wp/v2/media/' ),
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'adsense'        => array(
						'actions'          => AdminAdsense::instance()->get_ajax_actions( true ),
						'quota'            => array( 'remaining' => AdSenseRateLimiter::instance()->api_calls_remaining() ),
						'ad_table_columns' => array(
							'name'    => esc_html__( 'Name', 'ad-commander' ),
							'slot_id' => esc_html__( 'Slot ID', 'ad-commander' ),
							'type'    => esc_html__( 'Format', 'ad-commander' ),
							'size'    => esc_html__( 'Size', 'ad-commander' ),
						),
						'translations'     => array(
							'unsupported'      => esc_html__( 'UNSUPPORTED', 'ad-commander' ),
							'hide_inactive'    => esc_html__( 'Hide inactive', 'ad-commander' ),
							'hide_unsupported' => esc_html__( 'Hide unsupported', 'ad-commander' ),
							'refresh_ads'      => esc_html__( 'Refresh ads', 'ad-commander' ),
							'total_ads'        => esc_html__( 'Total ads:', 'ad-commander' ),
						),
					),
				)
			);
		}
	}

	/**
	 * Check if user has meta boxes (other than slug) hidden.
	 * This could maybe happen because of another plugin? This is more precautionary than anything.
	 *
	 * NOTE: This function is not currently in use. If we hide Screen Options in the future, may reactivate.
	 *
	 * @return void
	 */
	public function set_user_metaboxes() {
		$current_screen = get_current_screen();

		if ( isset( $current_screen->id ) && $current_screen->id === AdCommander::posttype_ad() ) {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				return;
			}

			$meta_key_hidden = 'metaboxhidden_' . AdCommander::posttype_ad();
			$hidden          = get_user_meta( $user_id, $meta_key_hidden, true );
			$should_hide     = array( 'slugdiv' );

			if ( $hidden !== $should_hide ) {
				update_user_meta( $user_id, $meta_key_hidden, $should_hide );
			}
		}
	}

	/**
	 * Hook function for creating/editing meta boxes for our post type.
	 *
	 * @return void
	 */
	public function posttype_meta_boxes() {
		$current_screen = get_current_screen();

		if ( isset( $current_screen->id ) && $current_screen->id === AdCommander::posttype_ad() ) {
			/**
			 * Insert our nonce field
			 */
			$this->nonce_field( $this->nonce );

			/**
			 * Create meta boxes.
			 */
			global $wp_meta_boxes;

			/**
			 * Settings
			 */
			add_meta_box( 'adcmdrsettingsdiv', __( 'Ad Settings & Usage', 'ad-commander' ), array( $this, 'adcmdrsettings_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );

			/**
			 * Banner Ad
			 */
			add_filter( 'postbox_classes_' . AdCommander::posttype_ad() . '_postimagediv', array( $this, 'add_bannerad_metabox_classes' ) );
			add_filter( 'admin_post_thumbnail_html', array( $this, 'adcmdrbanner_meta_box' ), 10, 2 );

			if ( isset( $wp_meta_boxes[ AdCommander::posttype_ad() ]['side']['low']['postimagediv'] ) ) {
				/**
				 * If the meta boxes were manually sorted, this won't fire.
				 * Becomes a bit of a UI issue if using ACF and manually sorting...
				 */
				$featured_image_meta = $wp_meta_boxes[ AdCommander::posttype_ad() ]['side']['low']['postimagediv'];

				if ( isset( $featured_image_meta['title'] ) ) {
					remove_meta_box( 'postimagediv', AdCommander::posttype_ad(), 'side' );
					add_meta_box( 'postimagediv', $featured_image_meta['title'], 'post_thumbnail_meta_box', AdCommander::posttype_ad(), 'normal', 'high' );
				}
			}

			/**
			 * Ad type meta boxes
			 */
			add_filter( 'postbox_classes_' . AdCommander::posttype_ad() . '_adcmdrtextcodediv', array( $this, 'add_textcode_metabox_classes' ) );
			add_filter( 'postbox_classes_' . AdCommander::posttype_ad() . '_adcmdrrichcontentdiv', array( $this, 'add_richcontent_metabox_classes' ) );
			add_filter( 'postbox_classes_' . AdCommander::posttype_ad() . '_adcmdradsensediv', array( $this, 'add_adsense_metabox_classes' ) );
			add_meta_box( 'adcmdrtextcodediv', __( 'Text or Code Ad', 'ad-commander' ), array( $this, 'adcmdrtextcode_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );
			add_meta_box( 'adcmdrrichcontentdiv', __( 'Rich Content Ad', 'ad-commander' ), array( $this, 'adcmdrrichcontent_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );
			add_meta_box( 'adcmdradsensediv', __( 'AdSense Ad', 'ad-commander' ), array( $this, 'adcmdradsense_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );

			/**
			 * Layout
			 */
			add_meta_box( 'adcmdrlayoutdiv', __( 'Layout', 'ad-commander' ), array( $this, 'adcmdrlayout_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );

			/**
			 * Targeting
			 */
			add_meta_box( 'adcmdrtargetingdiv', __( 'Targeting', 'ad-commander' ), array( $this, 'adcmdrtargeting_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );

			/**
			 * Expirations
			 */
			$expirations_title = __( 'Expirations', 'ad-commander' );
			if ( ! ProBridge::instance()->is_pro_loaded() ) {
				$expirations_title .= ProBridge::pro_label();
			}

			add_meta_box( 'adcmdrexpirationsdiv', $expirations_title, array( $this, 'adcmdrexpirations_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );

			/**
			 * Advanced
			 */
			add_meta_box( 'adcmdradvanceddiv', __( 'Advanced', 'ad-commander' ), array( $this, 'adcmdradvanced_meta_box' ), AdCommander::posttype_ad(), 'normal', 'high' );

			/**
			 * Statistics
			 */
			add_meta_box( 'adcmdrreportdiv', __( 'Tracking', 'ad-commander' ), array( $this, 'adcmdrreport_meta_box' ), AdCommander::posttype_ad(), 'side', 'default' );
		}
	}

	/**
	 * Add classes to a metbox.
	 *
	 * @param array $classes Current classes.
	 *
	 * @return array
	 */
	public function add_bannerad_metabox_classes( $classes ) {
		array_push( $classes, 'adcmdr-mode-restrict adcmdr-mode-restrict--bannerad' );
		return $classes;
	}

	/**
	 * Add classes to a metbox.
	 *
	 * @param array $classes Current classes.
	 *
	 * @return array
	 */
	public function add_adsense_metabox_classes( $classes ) {
		array_push( $classes, 'adcmdr-mode-restrict adcmdr-mode-restrict--adsense' );
		return $classes;
	}

	/**
	 * Add classes to a metbox.
	 *
	 * @param array $classes Current classes.
	 *
	 * @return array
	 */
	public function add_textcode_metabox_classes( $classes ) {
		array_push( $classes, 'adcmdr-mode-restrict adcmdr-mode-restrict--textcode' );
		return $classes;
	}

	/**
	 * Add classes to a metbox.
	 *
	 * @param array $classes Current classes.
	 *
	 * @return array
	 */
	public function add_richcontent_metabox_classes( $classes ) {
		array_push( $classes, 'adcmdr-mode-restrict adcmdr-mode-restrict--richcontent' );
		return $classes;
	}

	/**
	 * Add fields to the Settings meta box.
	 *
	 * @return void
	 */
	public function adcmdrsettings_meta_box() {
		$this->metaitem_addtype();
		$this->metaitem_usage();
	}

	/**
	 * Add fields to the banner meta box.
	 * This filters the HTML in the Featured Image div, so it works a bit differently.
	 *
	 * @param string $content The current content.
	 * @param int    $post_id The current post id.
	 *
	 * @return string
	 */
	public function adcmdrbanner_meta_box( $content, $post_id ) {
		$content .= '<div id="adcmdr-banner-settings" class="adcmdr-inside">';
		// $content .= $this->metaitem_bannerurl();
		$content .= $this->metaitem_display_size();
		$content .= $this->metaitem_bannerurlsettings();
		$content .= '</div>';

		return $content;
	}

	/**
	 * Add fields to the Text/Code meta box.
	 *
	 * @return void
	 */
	public function adcmdrtextcode_meta_box() {
		$this->metaitem_adcontent_textarea();
	}

	/**
	 * Add fields to the Rich Content meta box.
	 *
	 * @return void
	 */
	public function adcmdrrichcontent_meta_box() {
		$this->metaitem_adcontent_richcontent();
	}

	/**
	 * Add fields to the Rich Content meta box.
	 *
	 * @return void
	 */
	public function adcmdradsense_meta_box() {
		$pub_id               = AdSense::instance()->current_adsense_publisher_id();
		$is_adsense_connected = AdminAdsense::instance()->has_access_token( $pub_id );

		$this->metaitem_adsense_connect( $is_adsense_connected, $pub_id );
		$this->metaitem_adsense_meta( $is_adsense_connected, $pub_id );
	}

	/**
	 * Add fields to the Expirations meta box.
	 *
	 * @return void
	 */
	public function adcmdrexpirations_meta_box() {
		$this->metaitem_expire_datetime();
		$this->metaitem_expire_stats();
	}

	/**
	 * Add fields to the Reports meta box.
	 *
	 * @return void
	 */
	public function adcmdrreport_meta_box() {
		$this->metaitem_tracking();
		$this->metaitem_donottrack();
	}

	/**
	 * Add fields to the Layout meta box.
	 *
	 * @return void
	 */
	public function adcmdrlayout_meta_box() {
		$this->info( __( "Apply layout settings to an individual ad. If this ad is used in a group, some of these settings will be ignored in favor of the group's layout.", 'ad-commander' ) );
		$this->metaitem_ad_label();
		$this->metaitem_responsive_adimages();
		$this->metaitem_float();
		$this->metaitem_margin();
		$this->metaitem_custom_classes();
	}

	/**
	 * Add fields to the Targeting meta box.
	 *
	 * @return void
	 */
	public function adcmdrtargeting_meta_box() {
		$this->metaitem_targeting();
	}

	/**
	 * Add fields to the Advanced meta box.
	 *
	 * @return void
	 */
	public function adcmdradvanced_meta_box() {
		$this->metaitem_disable_consent();
		$this->metaitem_additional_code();
	}

	/**
	 * Meta item for connecting adsense
	 *
	 * @param mixed $is_adsense_connected If AdSense is currently connected.
	 * @param mixed $global_publisher_id If we have a publisher ID.
	 */
	private function metaitem_adsense_connect( $is_adsense_connected, $global_publisher_id ) {
		if ( ! $is_adsense_connected && ! $global_publisher_id ) :
			?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adsense-ad', ' adsense-ad--connect' ) ) ); ?>">
			<?php $this->info( esc_html__( 'AdSense is not yet configured. Connect your site to AdSense or specify a publisher ID to build ads manually. Alternatively, you can paste your ad code below. Settings must be configured by an administrator.', 'ad-commander' ), array( 'classes' => 'adcmdr-metaitem__warning' ) ); ?>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<div class="adcmdr-btn-group">
				<a href="<?php echo esc_url( self::settings_admin_url( 'adsense' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Configure AdSense settings', 'ad-commander' ); ?> &gt;</a>
			</div>
			<?php endif; ?>
		</div>
			<?php
		endif;
	}

	/**
	 * Determine the current AdSense ad mode based on cookies, if modes are disabled, and other logic.
	 *
	 * @param bool|string $current The expected current mode.
	 * @param array       $modes All available modes.
	 *
	 * @return string
	 */
	private function determine_current_adsense_ad_mode( $current, $modes ) {

		if ( ! $current ) {
			$current = 'manual';

			if ( isset( $_COOKIE['adcmdr_last_adsense_ad_mode'] ) && $_COOKIE['adcmdr_last_adsense_ad_mode'] ) {
				$current = sanitize_key( $_COOKIE['adcmdr_last_adsense_ad_mode'] );

				if ( ! in_array( $current, array( 'manual', 'direct', 'ad_code' ), true ) ) {
					$current = 'manual';
				}
			} elseif ( isset( $modes['direct'] ) ) {
				$current = 'direct';
			}
		}

		if ( $current === 'direct' && isset( $modes['disabled:direct'] ) ) {
			$current = 'manual';
		}

		if ( $current === 'manual' && isset( $modes['disabled:manual'] ) ) {
			$current = 'ad_code';
		}

		return $current;
	}

	/**
	 * HTML for targeting meta item.
	 *
	 * @param bool        $is_adsense_connected Determines if we have direct integration enabled.
	 * @param bool|string $global_puslisher_id The publisher ID from settings, if one exists.
	 */
	private function metaitem_adsense_meta( $is_adsense_connected, $global_publisher_id ) {
		$classes = array( 'adsense-ad' );
		if ( ! $is_adsense_connected && ! $global_publisher_id ) {
			$classes[] = 'divide';
			$classes[] = 'show';
		}

		$ad_pub_id = $this->wo_meta->get_value( $this->current_meta(), 'adsense_ad_pub_id' );
		if ( ! $ad_pub_id ) {
			$ad_pub_id = $global_publisher_id;
		}
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array_merge( $classes, array( 'adsense-ad-mode', 'group' ) ) ) ); ?>">
			<div class="metaitem__subitem">
			<?php
			if ( ! $is_adsense_connected || ( $ad_pub_id !== $global_publisher_id ) ) {
				$modes['disabled:direct'] = esc_html__( 'Load from account', 'ad-commander' );
			} else {
				$modes['direct'] = esc_html__( 'Load from account', 'ad-commander' );
			}

			if ( ! $global_publisher_id && ! $ad_pub_id ) {
				$modes['disabled:manual'] = __( 'Manual', 'ad-commander' );
			} else {
				$modes['manual'] = __( 'Manual', 'ad-commander' );
			}

			$modes['ad_code'] = __( 'Paste code', 'ad-commander' );

			$current_ad_mode = $this->wo_meta->get_value( $this->current_meta(), 'adsense_ad_mode' );
			$current         = $this->determine_current_adsense_ad_mode( $current_ad_mode, $modes );

			$this->wo_meta->label( 'adsense_ad_mode', __( 'AdSense Ad Mode', 'ad-commander' ) );
			$this->wo_meta->radiogroup( 'adsense_ad_mode', $modes, $current );
			?>
			</div>
			<div class="metaitem__subitem adcmdr-self-flex-end">
				<?php Doc::doc_link( 'adsense' ); ?>
			</div>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'divide' ) ) ); ?>">
		<div class="adcmdr-adsense_ad_mode-restrict adcmdr-adsense_ad_mode-restrict--direct">
			<div id="adcmdr_adsense_unsupported" class="adcmdr-notification adcmdr-metaitem__error">
				<p>
					<strong><?php esc_html_e( 'Data for this ad format is not provided by the Google AdSense API.', 'ad-commander' ); ?></strong>
					<?php $adsense_url = AdminAdSense::adsense_dashboard_url( $ad_pub_id, true ); ?>
					<br />
					<?php
					/* translators: %1$s open anchor tag, %2$s close anchor tag, %3$s open anchor tag, %4$s close anchor tag */
					printf( esc_html__( 'To insert this ad, switch to %1$scode%2$s or %3$smanual%4$s ad mode. Add the required code or data from %5$syour AdSense account.%6$s', 'ad-commander' ), '<a href="#adcmdr_adsense_mode" data-mode="ad_code">', '</a>', '<a href="#adcmdr_adsense_mode" data-mode="manual">', '</a>', '<a href="' . esc_url( $adsense_url ) . '" target="_blank" rel="noopener noreferrer">', '</a>' );
					?>
				</p>
			</div>
			<div id="adcmdr_adsense_inactive" class="adcmdr-notification adcmdr-metaitem__warning">
				<p>
					<strong><?php esc_html_e( 'Inactive ad', 'ad-commander' ); ?></strong>
					<?php $adsense_url = AdminAdSense::adsense_dashboard_url( $ad_pub_id, true ); ?>
					<br />
					<?php
					/* translators: %1$s open anchor tag, %2$s close anchor tag */
					printf( esc_html__( 'This ad is inactive. To display this ad, login to your %1$syour AdSense account%2$s and activate it.', 'ad-commander' ), '<a href="' . esc_url( $adsense_url ) . '" target="_blank" rel="noopener noreferrer">', '</a>' );
					?>
				</p>
			</div>
			<div id="adcmdr_adsense_quota" class="adcmdr-notification adcmdr-metaitem__warning">
				<div>
				<p>
					<strong><?php esc_html_e( 'AdSense API quota reached', 'ad-commander' ); ?></strong><br />
					<?php esc_html_e( 'You have reached the 24-hour AdSense API quota and can no longer modify ads using direct integration. This does not prevent ads displaying on your site.', 'ad-commander' ); ?>
				</p>
				<?php if ( ! ProBridge::instance()->is_pro_loaded() ) : ?>
				<p>
					<?php esc_html_e( 'API requests are limited by Google, so we must limit plugin users\' requests. Increase your limit by becoming a Pro user.', 'ad-commander' ); ?>
				</p>
				<p><a href="<?php echo esc_url( Admin::pro_upgrade_url( array( 'utm_medium' => 'button' ) ) ); ?>" class="button button-primary" target="_blank"><?php esc_html_e( 'Upgrade to Pro', 'ad-commander' ); ?></a></p>
				<?php else : ?>
				<p>
					<?php esc_html_e( 'API requests are limited by Google, so we must limit plugin users\' requests.', 'ad-commander' ); ?>
				</p>
				<?php endif; ?>
				</div>
			</div>
			<div id="adcmdr_adsense_ad_list"></div>
		</div>
		<div id="<?php echo esc_attr( Util::ns( 'adsense-ad-fields--manual' ) ); ?>"  class="adcmdr-adsense_ad_mode-restrict adcmdr-adsense_ad_mode-restrict--manual">
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'group' ) ) ); ?>">
				<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php
					$ad_format = $this->wo_meta->get_value( $this->current_meta(), 'adsense_ad_format' );
					$this->wo_meta->label( 'adsense_ad_format', __( 'Ad type', 'ad-commander' ) );
					$this->wo_meta->select( 'adsense_ad_format', AdSense::instance()::ad_formats(), $ad_format ? $ad_format : 'responsive' );
				?>
				</div>
				<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php
					$this->wo_meta->label( 'adsense_adslot_id', __( 'Ad Slot ID', 'ad-commander' ) );
					$this->wo_meta->input( 'adsense_adslot_id', $this->wo_meta->get_value( $this->current_meta(), 'adsense_adslot_id' ), 'text' );
				?>
				</div>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adsense-ad', ' adsense-ad--fullwidth' ) ) ); ?> adcmdr-adsensetype-restrict adcmdr-adsensetype-restrict--responsive adcmdr-adsensetype-restrict--inarticle">
			<?php
				$this->wo_meta->label( 'adsense_full_width_responsive', __( 'Full width responsive', 'ad-commander' ) );
				$current = $this->wo_meta->get_value( $this->current_meta(), 'adsense_full_width_responsive', 'true' );

				$full_width_modes = array(
					'true'    => 'Yes',
					'false'   => 'No',
					'default' => 'Default',
				);
				$this->wo_meta->radiogroup( 'adsense_full_width_responsive', $full_width_modes, $current ? $current : 'true' );
				?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adsense-ad', 'adsense-ad--display-size', 'group' ) ) ); ?> adcmdr-adsensetype-restrict adcmdr-adsensetype-restrict--normal adcmdr-adsensetype-restrict--multiplex">
				<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
					<?php $this->wo_meta->label( 'adsense_size_width', __( 'Width', 'ad-commander' ) ); ?>
					<?php $this->wo_meta->input( 'adsense_size_width', $this->wo_meta->get_value( $this->current_meta(), 'adsense_size_width' ), 'number' ); ?>
					<span>px</span>
				</div>
				<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
					<?php $this->wo_meta->label( 'adsense_size_height', __( 'Height', 'ad-commander' ) ); ?>
					<?php $this->wo_meta->input( 'adsense_size_height', $this->wo_meta->get_value( $this->current_meta(), 'adsense_size_height' ), 'number' ); ?>
					<span>px</span>
				</div>
			</div>
			<div class="adcmdr-adsensetype-restrict adcmdr-adsensetype-restrict--multiplex">
				<?php $this->message( __( 'Leave dimensions blank to create a responsive multiplex ad.', 'ad-commander' ) ); ?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adsense-ad', 'adsense-ad--display-size', 'group' ) ) ); ?> adcmdr-adsensetype-restrict adcmdr-adsensetype-restrict--multiplex">
				<?php $ui_types = AdSense::multiplex_ui_types(); ?>
				<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
					<?php $this->wo_meta->label( 'adsense_multiplex_uitype', __( 'UI Type', 'ad-commander' ) ); ?>
					<?php $this->wo_meta->select( 'adsense_multiplex_uitype', $ui_types, $this->wo_meta->get_value( $this->current_meta(), 'adsense_multiplex_uitype', 'default' ) ); ?>
				</div>
				<?php
				$multiplex_classes = array( Util::ns( 'metaitem__subitem' ), 'adcmdr-multiplex-restrict' );
				foreach ( array_keys( $ui_types ) as $ui_type ) {
					if ( $ui_type === 'default' ) {
						continue;
					}
					$multiplex_classes[] = 'adcmdr-multiplex-restrict--' . $ui_type;
				}
				?>
				<div class="<?php echo esc_attr( implode( ' ', $multiplex_classes ) ); ?>">
					<?php $this->wo_meta->label( 'adsense_multiplex_cols', __( 'Columns', 'ad-commander' ) ); ?>
					<?php $this->wo_meta->input( 'adsense_multiplex_cols', $this->wo_meta->get_value( $this->current_meta(), 'adsense_multiplex_cols', 4 ), 'number', array( 'min' => 1 ) ); ?>
				</div>
				<div class="<?php echo esc_attr( implode( ' ', $multiplex_classes ) ); ?>">
					<?php $this->wo_meta->label( 'adsense_multiplex_rows', __( 'Rows', 'ad-commander' ) ); ?>
					<?php $this->wo_meta->input( 'adsense_multiplex_rows', $this->wo_meta->get_value( $this->current_meta(), 'adsense_multiplex_rows', 1 ), 'number', array( 'min' => 1 ) ); ?>
				</div>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adsense-ad', ' adsense-ad--layout-key' ) ) ); ?> adcmdr-adsensetype-restrict adcmdr-adsensetype-restrict--infeed">
			<?php
				$this->wo_meta->label( 'adsense_layout_key', __( 'Layout key', 'ad-commander' ) );
				$this->wo_meta->input( 'adsense_layout_key', $this->wo_meta->get_value( $this->current_meta(), 'adsense_layout_key' ), 'text' );
			?>
			</div>
		</div>
		<div id="<?php echo esc_attr( Util::ns( 'adsense-ad-fields--ad_code' ) ); ?>" class="adcmdr-adsense_ad_mode-restrict adcmdr-adsense_ad_mode-restrict--ad_code">
			<?php
			$this->wo_meta->label( 'adsense_ad_code', __( 'Ad code', 'ad-commander' ) );
			$this->wo_meta->textarea( 'adsense_ad_code', $this->wo_meta->get_value( $this->current_meta(), 'adsense_ad_code' ) );
			?>
		</div>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adsense-ad-fields--amp', 'divide', 'group' ) ) ); ?> adcmdr-adsense_ad_mode-restrict adcmdr-adsense_ad_mode-restrict--manual adcmdr-adsense_ad_mode-restrict--direct">
			<div class="metaitem__subitem">
			<?php
			$options  = AdSense::amp_modes();
			$label    = __( 'AMP Ad', 'ad-commander' );
			$disabled = ! ProBridge::instance()->is_pro_loaded();

			if ( $disabled ) {
				$options = Util::disable_options( $options );
				$label  .= ProBridge::instance()->pro_label( true );
			}

			$this->wo_meta->label( 'adsense_amp_ad_mode', $label );
			$this->wo_meta->radiogroup( 'adsense_amp_ad_mode', $options, $this->wo_meta->get_value( $this->current_meta(), 'adsense_amp_ad_mode', 'automatic' ), array( 'disabled' => $disabled ) );
			?>
			</div>
			<div class="metaitem__subitem adcmdr-self-flex-end">
				<?php Doc::doc_link( 'amp' ); ?>
			</div>
			<?php
			if ( ! $disabled && ! Amp::instance()->has_amp_plugin() ) {
				$this->message( esc_html__( 'No AMP plugin found. View documentation for more information.', 'ad-commander' ) );
			}
			?>
			<?php
			/*
			These options are removed for now but may be added in a later version.

			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'close', 'ampmode--dynamic', 'group' ) ) ); ?> adcmdr-ampmode-restrict adcmdr-ampmode-restrict--dynamic">
				<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
					<?php $this->wo_meta->label( 'adsense_amp_dynamic_width', __( 'Width', 'ad-commander' ) ); ?>
					<?php $this->wo_meta->input( 'adsense_amp_dynamic_width', $this->wo_meta->get_value( $this->current_meta(), 'adsense_amp_dynamic_width' ), 'number' ); ?>
					<span>px</span>
				</div>
				<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
					<?php $this->wo_meta->label( 'adsense_amp_dynamic_height', __( 'Height', 'ad-commander' ) ); ?>
					<?php $this->wo_meta->input( 'adsense_amp_dynamic_height', $this->wo_meta->get_value( $this->current_meta(), 'adsense_amp_dynamic_height' ), 'number' ); ?>
					<span>px</span>
				</div>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'close', 'ampmode--fixed_height' ) ) ); ?> adcmdr-ampmode-restrict adcmdr-ampmode-restrict--fixed_height">
				<?php $this->wo_meta->label( 'adsense_amp_fixed_height', __( 'Height', 'ad-commander' ) ); ?>
				<?php $this->wo_meta->input( 'adsense_amp_fixed_height', $this->wo_meta->get_value( $this->current_meta(), 'adsense_amp_fixed_height' ), 'number' ); ?>
				<span>px</span>
			</div>
			*/
			?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adsense-ad', 'adsense-ad--pub_id', 'divide' ) ) ); ?>">
			<?php
			if ( $current_ad_mode !== 'ad_code' ) {
				$ad_pub_id_message = $ad_pub_id ? $ad_pub_id : __( 'No publisher ID set for this ad.', 'ad-commander' );
				$message           = __( 'Publisher ID: ', 'ad-commander' ) . '<span class="adcmdr-pub-id-display">' . esc_html( $ad_pub_id_message ) . '</span>';

				if ( $ad_pub_id !== $global_publisher_id ) {
					$message .= '<br /><em class="adcmdr-pub-id-mismatch adcmdr-danger">' . __( 'The publisher ID for this ad does not match the publisher ID from settings. If this is unintentional, delete this ad and create a new one or update your publisher ID in settings.', 'ad-commander' ) . '</em>';
				}

				$this->message( $message );
			}
			$this->wo_meta->input( 'adsense_ad_pub_id', $ad_pub_id, 'hidden' );
			?>
		</div>
			<?php
	}

	/**
	 * HTML for targeting meta item.
	 */
	private function metaitem_targeting() {
		$this->info( __( 'Only display this ad if certain conditions are met.', 'ad-commander' ) );
		$this->admin_targeting()->metaitem_targeting( $this->wo_meta->get_value( $this->current_meta(), 'content_conditions' ), false, 'content', AdCommander::posttype_ad() );
		$this->admin_targeting()->metaitem_targeting( $this->wo_meta->get_value( $this->current_meta(), 'visitor_conditions' ), false, 'visitor', AdCommander::posttype_ad() );
	}

	/**
	 * HTML/fields for Display Size meta.
	 *
	 * @return string
	 */
	private function metaitem_display_size() {
		return '<div class="' . esc_attr( Admin::metaitem_classes( array( 'displaysize', 'group', 'divide', 'center' ) ) ) . '">
			<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">' .
				$this->wo_meta->label( 'display_width', __( 'Width', 'ad-commander' ), array( 'display' => false ) ) .
				$this->wo_meta->input( 'display_width', $this->wo_meta->get_value( $this->current_meta(), 'display_width' ), 'number', array( 'display' => false ) ) .
				' <span>px</span>
			</div>
			<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">' .
				$this->wo_meta->label( 'display_height', __( 'Height', 'ad-commander' ), array( 'display' => false ) ) .
				$this->wo_meta->input( 'display_height', $this->wo_meta->get_value( $this->current_meta(), 'display_height' ), 'number', array( 'display' => false ) ) .
				' <span>px</span>
			</div>
			<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '"><div class="adcmdr-display-original"></div></div>
		</div>' . $this->message( __( 'Theme styles may override these settings.', 'ad-commander' ), array(), array( 'display' => false ) );
	}

	/**
	 * HTML/fields for Additional Code meta.
	 */
	private function metaitem_additional_code() {
		?>
		<hr class="adcmdr-divide" />
		<?php
		$this->info( __( 'Javascript and CSS allowed (must include style or script tags). <em>Use with caution</em> and test your ad display and tracking.', 'ad-commander' ) );
		Doc::doc_link( 'custom_code' );
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'additional_code_before', 'divide' ) ); ?>">
			<?php $this->wo_meta->label( 'custom_code_before', __( 'Custom Code - Before Ad', 'ad-commander' ) ); ?>
			<?php $this->wo_meta->textarea( 'custom_code_before', $this->wo_meta->get_value( $this->current_meta(), 'custom_code_before' ), array( 'rows' => 5 ) ); ?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'additional_code_after' ) ); ?>">
			<?php $this->wo_meta->label( 'custom_code_after', __( 'Custom Code - After Ad', 'ad-commander' ) ); ?>
			<?php $this->wo_meta->textarea( 'custom_code_after', $this->wo_meta->get_value( $this->current_meta(), 'custom_code_after' ), array( 'rows' => 5 ) ); ?>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Additional Code meta.
	 */
	private function metaitem_disable_consent() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'disable_consent' ) ) ); ?>">
			<?php
			$this->wo_meta->label( 'disable_consent', __( 'Disable Consent Requirement', 'ad-commander' ) );
			$this->wo_meta->checkbox( 'disable_consent', $this->wo_meta->get_value( $this->current_meta(), 'disable_consent' ) );
			$this->wo_meta->label( 'disable_consent', __( 'Ignore site-wide setting and disable consent requirement for this ad.', 'ad-commander' ) );
			$this->message( __( 'Note that this setting will be ignored if ad is used in a group or placement. You must disable consent for the entire group or placement instead.', 'ad-commander' ) );
			?>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Float meta.
	 */
	private function metaitem_float() {
		$radios = Util::float_options();
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'float_settings', 'group', 'divide' ) ) ); ?>">
		<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
			<?php $this->wo_meta->label( 'float', __( 'Float', 'ad-commander' ) ); ?>
			<?php $this->wo_meta->radiogroup( 'float', $radios, $this->wo_meta->get_value( $this->current_meta(), 'float', 'no' ) ); ?>
		</div>
		<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
			<?php
			$this->wo_meta->label( 'clear_float', __( 'Clear float', 'ad-commander' ) );
			$this->wo_meta->checkbox( 'clear_float', $this->wo_meta->get_value( $this->current_meta(), 'clear_float' ) );
			$this->wo_meta->label( 'clear_float', __( 'Attempt to prevent text from wrapping around floated ad', 'ad-commander' ) );
			?>
		</div>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Custom Classes meta.
	 */
	private function metaitem_custom_classes() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'custom_classes', 'divide' ) ) ); ?>">
		<?php
		$this->wo_meta->label( 'custom_classes', __( 'Custom Classes', 'ad-commander' ) );
		$this->wo_meta->input( 'custom_classes', $this->wo_meta->get_value( $this->current_meta(), 'custom_classes' ), 'text' );
		?>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Margin meta.
	 */
	private function metaitem_margin() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'margin', 'group', 'divide' ) ) ); ?>">
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php
				$this->wo_meta->label( 'margin_top', __( 'Margin Top', 'ad-commander' ) );
				$this->wo_meta->input( 'margin_top', $this->wo_meta->get_value( $this->current_meta(), 'margin_top' ), 'number' );
				?>
				<span>px</span>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php
				$this->wo_meta->label( 'margin_right', __( 'Margin Right', 'ad-commander' ) );
				$this->wo_meta->input( 'margin_right', $this->wo_meta->get_value( $this->current_meta(), 'margin_right' ), 'number' );
				?>
				<span>px</span>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php
				$this->wo_meta->label( 'margin_bottom', __( 'Margin Bottom', 'ad-commander' ) );
				$this->wo_meta->input( 'margin_bottom', $this->wo_meta->get_value( $this->current_meta(), 'margin_bottom' ), 'number' );
				?>
				<span>px</span>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php
				$this->wo_meta->label( 'margin_left', __( 'Margin Left', 'ad-commander' ) );
				$this->wo_meta->input( 'margin_left', $this->wo_meta->get_value( $this->current_meta(), 'margin_left' ), 'number' );
				?>
				<span>px</span>
			</div>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Expiration meta.
	 */
	private function metaitem_expire_datetime() {
		$disabled = ! ProBridge::instance()->is_pro_loaded();

		$classes = array( 'expire_datetime', 'group' );

		if ( $disabled ) {
			$classes[] = 'disabled';
		} else {
			$info = __( "Expired ads will move to Draft status. Leave values blank to disable a feature. To schedule a start date, edit this Ad post's Publish date and time in the Publish settings.", 'ad-commander' );
			$this->info( $info );
		}
		Doc::doc_link( 'expiring_ads' );
		$this->message( __( 'Expire ad at the below date and time. Both must be specified.', 'ad-commander' ) );
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( $classes ) ); ?>">
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
		<?php
		$this->wo_meta->label( 'expire_date', __( 'Expiration Date', 'ad-commander' ) );
		$this->wo_meta->input(
			'expire_date',
			$this->wo_meta->get_value( $this->current_meta(), 'expire_date' ),
			'text',
			array(
				'classes'  => 'adcmdr-datepicker',
				'disabled' => $disabled,
			)
		);
		?>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
		<?php
		$this->wo_meta->label( 'expire_hour', __( 'Expiration Time', 'ad-commander' ) );
		$this->wo_meta->select( 'expire_hour', Util::hours_formatted(), $this->wo_meta->get_value( $this->current_meta(), 'expire_hour' ), array( 'disabled' => $disabled ) );
		echo '<span>:</span>';
		$this->wo_meta->select( 'expire_min', Util::mins_formatted(), $this->wo_meta->get_value( $this->current_meta(), 'expire_min' ), array( 'disabled' => $disabled ) );
		$this->wo_meta->select( 'expire_ampm', Util::ampm_formatted(), $this->wo_meta->get_value( $this->current_meta(), 'expire_ampm' ), array( 'disabled' => $disabled ) );
		?>
			</div>
			<?php if ( ! $disabled ) : ?>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem align-self-center' ) ); ?>">
				<a href="#" class="adcmdr-clear">Clear</a>
			</div>
			<?php endif; ?>
		</div>
			<?php
	}

	/**
	 * HTML/fields for Expiration meta.
	 */
	private function metaitem_expire_stats() {
		$disabled       = ! ProBridge::instance()->is_pro_loaded();
		$disabled_c     = false;
		$disabled_i     = false;
		$click_disabled = '';
		$imp_disabled   = '';

		if ( ! $disabled ) {

			if ( ! Tracking::instance()->should_track_local( 'clicks', false ) ) {
				$disabled_c     = true;
				$click_disabled = __( 'Local click tracking is disabled.', 'ad-commander' );
			}

			if ( ! Tracking::instance()->should_track_local( 'impressions', false ) ) {
				$disabled_i   = true;
				$imp_disabled = __( 'Local impression tracking is disabled.', 'ad-commander' );
			}

			if ( $this->wo_meta->get_value( $this->current_meta(), 'donottrack_c' ) ) {
				$disabled_c = true;
				if ( ! $click_disabled ) {
					$click_disabled = __( 'Local click tracking is disabled for this ad.', 'ad-commander' );
				}
			}

			if ( $this->wo_meta->get_value( $this->current_meta(), 'donottrack_i' ) ) {
				$disabled_i = true;
				if ( ! $imp_disabled ) {
					$imp_disabled = __( 'Local impression tracking is disabled for this ad', 'ad-commander' );
				}
			}
		}

		$classes = array( 'expire_stats', 'divide' );

		if ( $disabled ) {
			$classes[] = 'disabled';
		}
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( $classes ) ); ?>">
		<?php
		if ( $disabled_c || $disabled_i ) {
			$disabled_message = array();
			if ( $click_disabled ) {
				$disabled_message[] = $click_disabled;
			}
			if ( $imp_disabled ) {
				$disabled_message[] = $imp_disabled;
			}
			$this->info( implode( ' ', $disabled_message ), 'adcmdr-metaitem__warning' );
		}
		$this->message( __( 'Expire ad if it meets or exceeds either of these statistics.', 'ad-commander' ) );
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'expire_status', 'group' ) ) ); ?>">
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
		<?php
		$this->wo_meta->label( 'expire_clicks', __( 'Maximum Clicks', 'ad-commander' ) );
		$this->wo_meta->input( 'expire_clicks', $this->wo_meta->get_value( $this->current_meta(), 'expire_clicks' ), 'number', array( 'disabled' => $disabled || $disabled_c ) );
		?>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
			<?php
			$this->wo_meta->label( 'expire_impressions', __( 'Maximum Impressions', 'ad-commander' ) );
			$this->wo_meta->input( 'expire_impressions', $this->wo_meta->get_value( $this->current_meta(), 'expire_impressions' ), 'number', array( 'disabled' => $disabled || $disabled_i ) );
			?>
					
			</div>
			<?php if ( ! $disabled || ( ! $disabled_c && ! $disabled_i ) ) : ?>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem align-self-center' ) ); ?>">
				<a href="#" class="adcmdr-clear">Clear</a>
			</div>
			<?php endif; ?>
		</div>
	</div>
			<?php
	}

	/**
	 * HTML/fields for Add Types meta.
	 */
	private function metaitem_addtype() {

		$current_ad_type = $this->wo_meta->get_value( $this->current_meta(), 'adtype' );

		if ( ! $current_ad_type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is executed on the single ad post page; do not want nonce verification here. Input is restricted to pre-defined keys.
			$default_ad_type = isset( $_GET['adcmdr_default_ad_type'] ) ? sanitize_text_field( wp_unslash( $_GET['adcmdr_default_ad_type'] ) ) : false;
			if ( $default_ad_type && in_array( $default_ad_type, array_keys( AdPostMeta::ad_types() ), true ) ) {
				$current_ad_type = $default_ad_type;
			}
		}
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'adtype' ) ); ?>">
		<?php
		$this->wo_meta->label( 'adtype', __( 'Ad Type', 'ad-commander' ) );
		$this->wo_meta->select(
			'adtype',
			AdPostMeta::ad_types(),
			$current_ad_type,
			array(
				'empty_text' => __( 'Select an ad type', 'ad-commander' ),
			)
		);
		Doc::doc_link( 'ad_type' );
		?>
		</div>
		<?php
	}

	/**
	 * HTML for Usage info.
	 */
	private function metaitem_usage() {
		global $post;
		if ( isset( $post->ID ) && $this->has_ad_saved() ) :
			?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'usage', 'group', 'divide' ) ) ); ?>">
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
			<?php $this->wo_meta->label( 'shortcode', __( 'Shortcode', 'ad-commander' ) ); ?>
			<?php
			$this->wo_meta->input(
				'shortcode',
				'[adcmdr_ad id="' . $post->ID . '"]',
				'text',
				array(
					'readonly' => true,
					'classes'  => 'code',
				)
			);
			self::copy_button_ad_shortcode( $post->ID );
			?>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
			<?php $this->wo_meta->label( 'template', __( 'Template function', 'ad-commander' ) ); ?>
			<?php
			$this->wo_meta->input(
				'template',
				'<?php adcmdr_the_ad(' . $post->ID . '); ?>',
				'text',
				array(
					'readonly' => true,
					'classes'  => 'code',
				)
			);
			self::copy_button_ad_template_tag( $post->ID );
			?>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?> adcmdr-self-flex-end"><?php Doc::doc_link( 'manual_placement' ); ?></div>
		</div>
				<?php
				endif;
	}

	/**
	 * HTML/fields for Do Not Track meta.
	 */
	private function metaitem_donottrack() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'donottrack' ) ) ); ?>">
		<?php
		$this->wo_meta->label( '', __( 'Disable ad tracking', 'ad-commander' ) );

		$tracking = new Tracking();
		if ( ! $tracking->has_tracking_methods() ) {
			/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
			$message = sprintf( __( 'There are currently no %1$s tracking methods enabled.%2$s', 'ad-commander' ), '<a href="' . esc_url( self::settings_admin_url( 'tracking' ) ) . '">', '</a>' );
			$this->message( $message );
		} else {
			$disabled_tracking = array();
			if ( Options::instance()->get( 'disable_track_impressions', 'tracking', true ) ) {
				$disabled_tracking[] = __( 'Impression tracking', 'ad-commander' );
			}
			if ( Options::instance()->get( 'disable_track_clicks', 'tracking', true ) ) {
				$disabled_tracking[] = __( 'Click tracking', 'ad-commander' );
			}

			if ( ! empty( $disabled_tracking ) ) {
				if ( count( $disabled_tracking ) > 1 ) {
					/* translators: %1$s: %2$s anchor tag with URL, %2$s: close anchor tag */
					$disabled_text = __( 'Tracking currently disabled %1$sfor all ads%2$s.', 'ad-commander' );
				} else {
					/* translators: %1$s: %2$s anchor tag with URL, %2$s: close anchor tag, starts with a space because tracking method previously translated and prepended. */
					$disabled_text = $disabled_tracking[0] . __( ' tracking currently disabled %1$sfor all ads%2$s.', 'ad-commander' );
				}
				$message = sprintf( esc_html( $disabled_text ), '<a href="' . esc_url( self::settings_admin_url( 'tracking' ) ) . '">', '</a>' );
				$this->message( $message );
			}
			?>
			<div class="adcmdr-disabletracking">
				<div>
			<?php
			$this->wo_meta->checkbox( 'donottrack_c', $this->wo_meta->get_value( $this->current_meta(), 'donottrack_c' ) );
			$this->wo_meta->label( 'donottrack_c', __( 'Do not track clicks for this ad.', 'ad-commander' ) );
			?>
				</div>
				<div>
			<?php
			$this->wo_meta->checkbox( 'donottrack_i', $this->wo_meta->get_value( $this->current_meta(), 'donottrack_i' ) );
			$this->wo_meta->label( 'donottrack_i', __( 'Do not track impressions for this ad.', 'ad-commander' ) );
			?>
				</div>
			</div>
			<?php
		}
		?>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Responsive Images meta.
	 */
	private function metaitem_responsive_adimages() {
		$radios = Util::site_default_options();
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'responsive_banners', 'divide' ) ) ); ?>">
			<?php $this->wo_meta->label( 'responsive_banners', __( 'Responsive Ad Images', 'ad-commander' ) ); ?>
			<?php $this->wo_meta->radiogroup( 'responsive_banners', $radios, $this->wo_meta->get_value( $this->current_meta(), 'responsive_banners', 'site_default' ) ); ?>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Responsive Images meta.
	 */
	private function metaitem_ad_label() {
		$radios = Util::site_default_options();
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'ad_label' ) ) ); ?>">
			<?php $this->wo_meta->label( 'ad_label', __( 'Ad Label', 'ad-commander' ) ); ?>
			<?php $this->wo_meta->radiogroup( 'ad_label', $radios, $this->wo_meta->get_value( $this->current_meta(), 'ad_label', 'site_default' ) ); ?>
		</div>
		<?php
	}

	/**
	 * HTML/fields for Banner link meta.
	 *
	 * @return string
	 */
	private function metaitem_bannerurlsettings() {
		$radios = Util::site_default_options();

		$html = '<div class="' . esc_attr( Admin::metaitem_classes( array( 'bannerurlsettings', 'group', 'divide' ) ) ) . '">';

		$html .= '<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">';
		$html .= $this->wo_meta->label( 'bannerurl', __( 'Banner Link', 'ad-commander' ), array( 'display' => false ) );
		$html .= $this->wo_meta->input(
			'bannerurl',
			$this->wo_meta->get_value( $this->current_meta(), 'bannerurl' ),
			'url',
			array(
				'placeholder' => 'https:// ',
				'display'     => false,
			)
		);
		$html .= '</div>';

		$html .= '<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">';
		$html .= $this->wo_meta->label( 'newwindow', __( 'Open in a new window', 'ad-commander' ), array( 'display' => false ) );
		$html .= $this->wo_meta->radiogroup( 'newwindow', $radios, $this->wo_meta->get_value( $this->current_meta(), 'newwindow', 'site_default' ), array( 'display' => false ) );
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="' . esc_attr( Admin::metaitem_classes( array( 'bannerurlsettings' ) ) ) . '">';

		$html .= '<div class="adcmdr-block-label"><em>' . __( 'rel', 'ad-commander' ) . '</em> ' . __( 'attributes', 'ad-commander' ) . '</div>';
		$html .= '<div class="' . esc_attr( Admin::metaitem_classes( array( 'group' ) ) ) . '">';

		$html .= '<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">';
		$html .= $this->wo_meta->label( 'sponsored', __( 'Sponsored', 'ad-commander' ), array( 'display' => false ) );
		$html .= $this->wo_meta->radiogroup( 'sponsored', $radios, $this->wo_meta->get_value( $this->current_meta(), 'sponsored', 'site_default' ), array( 'display' => false ) );
		$html .= '</div>';

		$html .= '<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">';
		$html .= $this->wo_meta->label( 'nofollow', __( 'Nofollow', 'ad-commander' ), array( 'display' => false ) );
		$html .= $this->wo_meta->radiogroup( 'nofollow', $radios, $this->wo_meta->get_value( $this->current_meta(), 'nofollow', 'site_default' ), array( 'display' => false ) );
		$html .= '</div>';

		$html .= '<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">';
		$html .= $this->wo_meta->label( 'noopener', __( 'Noopener', 'ad-commander' ), array( 'display' => false ) );
		$html .= $this->wo_meta->radiogroup( 'noopener', $radios, $this->wo_meta->get_value( $this->current_meta(), 'noopener', 'site_default' ), array( 'display' => false ) );
		$html .= '</div>';

		$html .= '<div class="' . esc_attr( Util::ns( 'metaitem__subitem' ) ) . '">';
		$html .= $this->wo_meta->label( 'noreferrer', __( 'Noreferrer', 'ad-commander' ), array( 'display' => false ) );
		$html .= $this->wo_meta->radiogroup( 'noreferrer', $radios, $this->wo_meta->get_value( $this->current_meta(), 'noreferrer', 'site_default' ), array( 'display' => false ) );
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * HTML/fields for Content ad meta.
	 */
	private function metaitem_adcontent_textarea() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adcontent', ' adcontent--text' ) ) ); ?>">
			<?php $this->wo_meta->textarea( 'adcontent_text', $this->wo_meta->get_value( $this->current_meta(), 'adcontent_text' ) ); ?>
			<?php
			/* translators: %1$s anchor tag, %2$s close anchor tag */
			$this->message( sprintf( __( 'Paste your entire ad code here. Linebreaks will be removed from output. For text ads with linebreaks, use the %1$sRich Content Editor%2$s.', 'ad-commander' ), '<a href="#_adcmdr_adtype" data-adtype="richcontent">', '</a>' ) );
			?>
		</div>
			<?php
	}

	/**
	 * HTML/fields for Rich Content ad meta.
	 */
	private function metaitem_adcontent_richcontent() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'adcontent', 'adcontent--rich' ) ) ); ?>">
			<?php
			$content   = $this->wo_meta->get_value( $this->current_meta(), 'adcontent_rich' );
			$editor_id = $this->wo_meta->make_key( 'adcontent_rich' );
			$settings  = array(
				'wpautop'       => true, // use wpautop?
				'media_buttons' => true, // show insert/upload button(s)
				'textarea_name' => $editor_id, // set the textarea name to something different, square brackets [] can be used here
				'textarea_rows' => get_option( 'default_post_edit_rows', 10 ), // rows="..."
				'tabindex'      => '',
				'editor_css'    => '', // extra styles for both visual and HTML editors buttons,
				'editor_class'  => '', // add extra class(es) to the editor textarea
				'teeny'         => false, // output the minimal editor config used in Press This
				'dfw'           => false, // replace the default fullscreen with DFW (supported on the front-end in WordPress 3.4)
				'tinymce'       => true, // load TinyMCE, can be used to pass settings directly to TinyMCE using an array()
				'quicktags'     => true, // load Quicktags, can be used to pass settings directly to Quicktags using an array()
			);
			wp_editor( $content === null ? '' : $content, $editor_id, $settings );
			?>
		</div>
			<?php
	}

	/**
	 * HTML for Tracking info.
	 */
	private function metaitem_tracking() {
		global $post;
		if ( isset( $post->ID ) && $this->has_ad_saved() ) :
			$reports = $this->admin_reports();
			?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'tracking', 'tracking--impressions' ) ) ); ?>">
				<?php $this->message( __( 'Stats may take a minute to update.', 'ad-commander' ) ); ?>
				<?php $this->wo_meta->label( 'impressions', __( 'Impressions:', 'ad-commander' ) ); ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
				<tr>
					<th><?php esc_html_e( 'All time', 'ad-commander' ); ?></th>
					<th><?php esc_html_e( '30 days', 'ad-commander' ); ?></th>
					<th><?php esc_html_e( '7 days', 'ad-commander' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td><?php echo esc_html( $reports->ad_stats( $post->ID ) ); ?></td>
					<td><?php echo esc_html( $reports->ad_stats( $post->ID, 30 ) ); ?></td>
					<td><?php echo esc_html( $reports->ad_stats( $post->ID, 7 ) ); ?></td>
				</tr>
				</tbody>
			</table>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'tracking', 'tracking--clicks' ) ) ); ?>">
				<?php $this->wo_meta->label( 'clicks', __( 'Clicks:', 'ad-commander' ) ); ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
				<tr>
					<th><?php esc_html_e( 'All time', 'ad-commander' ); ?></th>
					<th><?php esc_html_e( '30 days', 'ad-commander' ); ?></th>
					<th><?php esc_html_e( '7 days', 'ad-commander' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td><?php echo esc_html( $reports->ad_stats( $post->ID, 'all', 'clicks' ) ); ?></td>
					<td><?php echo esc_html( $reports->ad_stats( $post->ID, 30, 'clicks' ) ); ?></td>
					<td><?php echo esc_html( $reports->ad_stats( $post->ID, 7, 'clicks' ) ); ?></td>
				</tr>
				</tbody>
			</table>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'tracking', 'tracking--report' ) ) ); ?>">
				<?php
				$report_url = add_query_arg(
					array(
						'filter_by_ad_ids' => $post->ID,
					),
					self::reports_admin_url()
				);

				$report_url = wp_nonce_url( $report_url, $reports->nonce['action'], $reports->nonce['name'] );
				?>
			<a href="<?php echo esc_url( $report_url ); ?>">View full report ></a>
		</div>
				<?php
				endif;
	}

	/**
	 * Interfaces with WOMeta message and adds classes.
	 *
	 * @param string $message The message to display.
	 * @param array  $classes Any additional classes.
	 * @param array  $args Additional arguments.
	 *
	 * @return string
	 */
	private function message( $message, $classes = array(), $args = array() ) {
		$classes[] = 'adcmdr-metaitem__message';

		$args['classes'] = $classes;

		return $this->wo_meta->message( $message, $args );
	}

	/**
	 * Check if this ad has saved previously.
	 *
	 * @return bool
	 */
	private function has_ad_saved() {
		global $post;
		return isset( $post->post_status ) && $post->post_status !== 'auto-draft';
	}

	/**
	 * The onboarding message (maybe) displayed after a post is published.
	 *
	 * @return void
	 */
	public function new_ad_notice() {
		global $post;

		if ( ! $post ||
			! isset( $post->ID ) ||
			! $this->has_ad_saved() ||
			! isset( $post->post_type ) ||
			$post->post_type !== AdCommander::posttype_ad() ||
			! $this->is_screen( AdCommander::posttype_ad() ) ||
			$post->post_status !== 'publish' ||
			! AdminOnboarding::instance()->should_onboard( 'ads' ) ) {
			return;
		}

		$ad_type = $this->wo_meta->get_value( $this->current_meta(), 'adtype' );

		if ( ! $ad_type || $this->invalid_ad_message() !== false ) {
			return;
		}

		/**
		 * TODO: Consider some restrictions on when this should show.
		 * We could do by X minutes, but that seems unreliable.
		 * Could check if the post is in a placement or not?
		 * Or a combination of the two.
		 * Right now it shows until the onboarding messages are disabled.
		 */
		?>
		<div class="notice adcmdr-ob-notice adcmdr-ob-notice--published">
			<div class="adcmdr-ob-row adcmdr-ob-intro">
				<h4><?php esc_html_e( 'This ad is ready to be placed on your site.', 'ad-commander' ); ?></h4>
				<a href="#" class="adcmdr-ob-dismiss" data-disable-ob="ads"><?php esc_html_e( 'Disable this message', 'ad-commander' ); ?></a>
			</div>
			<div class="adcmdr-ob-row">
				<div class="adcmdr-ob-col">
					<h3><?php esc_html_e( 'Place this ad', 'ad-commander' ); ?></h3>
					<div class="adcmdr-btn-group">
						<a href="<?php echo esc_url( self::new_placement_post_url() ); ?>" class="button button-primary"><?php esc_html_e( 'Create automatic placement', 'ad-commander' ); ?></a>
						<a href="<?php echo esc_url( self::admin_placement_post_type_url() ); ?>" class="button button-secondary"><?php esc_html_e( 'Manage placements', 'ad-commander' ); ?></a>
					</div>
					<ul>
						<li><a href="<?php echo esc_url( Doc::doc_urls()['automantic_placement'] ); ?>" target="_blank"><?php esc_html_e( 'Automatic placement documentation', 'ad-commander' ); ?> &gt;</a></li>
						<li><a href="<?php echo esc_url( Doc::doc_urls()['manual_placement'] ); ?>" target="_blank"><?php esc_html_e( 'Manual placement documentation', 'ad-commander' ); ?> &gt;</a></li>
					</ul>
				</div>
				<div class="adcmdr-ob-col">
					<h3><?php esc_html_e( 'Groups', 'ad-commander' ); ?></h3>
					<a href="<?php echo esc_url( self::admin_group_tax_url() ); ?>" class="button button-primary"><?php esc_html_e( 'Manage groups', 'ad-commander' ); ?></a>
					<ul>
						<li><a href="<?php echo esc_url( AdCommander::public_site_url( 'documentation-category/groups-ads' ) ); ?>" target="_blank"><?php esc_html_e( 'Groups & ads documentation', 'ad-commander' ); ?> &gt;</a></li>
					</ul>
				</div>
				<?php AdminOnboarding::onboarding_support_column(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save posted meta data. Interfaces with WOMeta, and then performs additional tasks.
	 *
	 * @param int    $post_id The Post ID that has saved.
	 * @param object $post The WP_Post object that has saved.
	 *
	 * @return void
	 */
	public function save_posted_metadata( $post_id, $post ) {
		if ( $this->wo_meta->save_posted_metadata( $post, AdPostMeta::post_meta_keys(), $this->nonce, AdCommander::capability() ) ) {
			$this->save_expire_gmt( $post_id );
			$this->save_adtype_meta( $post_id );
		}

		delete_transient( AdminReports::filter_ads_transient() );
	}

	/**
	 * Button to copy an ad shortcode.
	 *
	 * @param int $post_id The post ID to copy.
	 *
	 * @return void
	 */
	public static function copy_button_ad_shortcode( $post_id ) {
		?>
		<button data-adcmdr-copy='[adcmdr_ad id="<?php echo absint( $post_id ); ?>"]' title="<?php echo esc_attr( __( 'Copy', 'ad-commander' ) ); ?>"><i class="dashicons dashicons-clipboard"></i></button>
		<?php
	}

	/**
	 * Button to copy an ad template tag.
	 *
	 * @param int $post_id The post ID to copy.
	 *
	 * @return void
	 */
	public static function copy_button_ad_template_tag( $post_id ) {
		?>
		<button data-adcmdr-copy='&lt;?php adcmdr_the_ad(<?php echo absint( $post_id ); ?>); ?&gt;' title="<?php echo esc_attr( __( 'Copy', 'ad-commander' ) ); ?>"><i class="dashicons dashicons-clipboard"></i></button>
		<?php
	}

	/**
	 * Convert the saved expire meta into a GMT timestamp and save it.
	 *
	 * @param int $post_id The post ID that was saved.
	 *
	 * @return void
	 */
	private function save_expire_gmt( $post_id ) {
		$post_meta = $this->wo_meta->get_post_meta( $post_id, AdPostMeta::post_meta_keys() );

		$expire_date = $this->wo_meta->get_value( $post_meta, 'expire_date' );
		$expire_hour = $this->wo_meta->get_value( $post_meta, 'expire_hour' );
		$expire_min  = $this->wo_meta->get_value( $post_meta, 'expire_min' );
		$expire_ampm = $this->wo_meta->get_value( $post_meta, 'expire_ampm' );
		$expire_gmt  = null;

		if ( $expire_date && $expire_hour && $expire_min && $expire_ampm ) {
			$expire_date = Util::datetime_wp_timezone( $expire_date . ' ' . $expire_hour . ':' . $expire_min . ' ' . $expire_ampm );
			$expire_gmt  = $expire_date->getTimestamp();
		}

		$this->wo_meta->update_post_meta( $post_id, 'expire_gmt', $expire_gmt );
	}

	/**
	 * Save publisher ID for adsense ads.
	 *
	 * @param int $post_id The post ID that was saved.
	 *
	 * @return void
	 */
	private function save_adtype_meta( $post_id ) {
		$post_meta = $this->wo_meta->get_post_meta( $post_id, AdPostMeta::post_meta_keys() );
		$ad_type   = $this->wo_meta->get_value( $post_meta, 'adtype' );

		if ( $ad_type !== 'bannerad' ) {
			delete_post_thumbnail( $post_id );
		}

		if ( $ad_type === 'adsense' ) {
			/**
			 * Publisher ID
			 */
			$adsense_ad_mode = $this->wo_meta->get_value( $this->current_meta(), 'adsense_ad_mode' );
			$ad_pub_id       = $this->wo_meta->get_value( $this->current_meta(), 'adsense_ad_pub_id' );
			$adsense         = AdSense::instance();

			if ( $adsense_ad_mode ) {
				setcookie( 'adcmdr_last_adsense_ad_mode', $adsense_ad_mode, 0, '/' );
			} else {
				setcookie( 'adcmdr_last_adsense_ad_mode', '', 1, '/' );
			}

			if ( $adsense_ad_mode !== 'ad_code' && ( ! $ad_pub_id || ! $adsense->is_publisher_id_valid( $ad_pub_id ) ) ) {
				$pub_id = $adsense->current_adsense_publisher_id();

				if ( $pub_id ) {
					$this->wo_meta->update_post_meta( $post_id, 'adsense_ad_pub_id', sanitize_text_field( $pub_id ) );
				}
			}

			/**
			 * Erase unecessary meta if we have an ad_code ad
			 */
			$ad_code = $this->wo_meta->get_value( $this->current_meta(), 'adsense_ad_code' );

			if ( $adsense_ad_mode !== 'ad_code' && $ad_code ) {
				$this->wo_meta->update_post_meta( $post_id, 'adsense_ad_code', null );
			} elseif ( $adsense_ad_mode === 'ad_code' ) {
				$this->wo_meta->update_post_meta( $post_id, 'adsense_ad_pub_id', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_adslot_id', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_size_width', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_size_height', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_layout_key', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_ad_format', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_full_width_responsive', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_multiplex_uitype', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_multiplex_cols', null );
				$this->wo_meta->update_post_meta( $post_id, 'adsense_multiplex_rows', null );
			}
		}
	}


	/**
	 * Output content within an individual column on the Ad post page.
	 *
	 * @param string $column Current column.
	 * @param int    $post_id The post ID.
	 *
	 * @return void
	 */
	public function manage_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'type':
				$meta   = $this->wo_meta->get_post_meta( $post_id, AdPostMeta::post_meta_keys() );
				$adtype = $this->wo_meta->get_value( $meta, 'adtype', null );
				if ( $adtype ) {
					if ( $adtype === 'bannerad' && has_post_thumbnail( $post_id ) ) {
						?>
						<img src="<?php echo esc_url( get_the_post_thumbnail_url( $post_id, 'full' ) ); ?>" alt="" width="50" height="auto" />
						<?php
					} else {
						echo esc_html( AdPostMeta::ad_types()[ $adtype ] );
					}
				}

				$valid_ad = Util::has_valid_ad( $adtype, $meta, $this->wo_meta, $post_id );

				if ( $valid_ad && $adtype === 'adsense' && $this->wo_meta->get_value( $meta, 'adsense_ad_mode', null ) !== 'ad_code' ) {
					$global_publisher_id = AdSense::instance()->current_adsense_publisher_id();
					$ad_pub_id           = $this->wo_meta->get_value( $meta, 'adsense_ad_pub_id', null );

					if ( $global_publisher_id !== $ad_pub_id ) {
						$valid_ad = false;
					}
				}

				if ( ! $valid_ad ) {
					Util::wp_kses_icon( '<i class="adcmdr-danger adcmdr-invalid-ad-warning dashicons dashicons-warning" title="' . esc_attr__( 'There is a problem with this ad\'s settings.', 'ad-commander' ) . '"></i>' );
				}

				break;

			case 'shortcode':
				self::copy_button_ad_shortcode( $post_id );
				break;

			case 'template_tag':
				self::copy_button_ad_template_tag( $post_id );
				break;

			case 'stats':
				$stat_parts = array();

				if ( ! Tracking::instance()->is_tracking_disabled_for( 'impressions' ) && ! Util::truthy( get_post_meta( $post_id, $this->wo_meta->make_key( 'donottrack_i' ), true ) ) ) {
					$impressions = $this->admin_reports()->ad_stats( $post_id, 'all', 'impressions' );
					/* translators: %d - number of impressoins */
					$stat_parts[] = '<span>' . sprintf( esc_html__( 'Impressions: %d' ), intval( $impressions ) ) . '</span>';
				}

				if ( ! Tracking::instance()->is_tracking_disabled_for( 'clicks' ) && ! Util::truthy( get_post_meta( $post_id, $this->wo_meta->make_key( 'donottrack_c' ), true ) ) ) {
					$clicks = $this->admin_reports()->ad_stats( $post_id, 'all', 'clicks' );
					/* translators: %d - number of clicks */
					$stat_parts[] = '<span>' . sprintf( esc_html__( 'Clicks: %d' ), intval( $clicks ) ) . '</span>';
				}

				if ( ! empty( $stat_parts ) ) :
					?>
				<span class="adcmdr-stat-row"><?php echo wp_kses( implode( ' / ', $stat_parts ), array( 'span' => array() ) ); ?></span>
					<?php
				endif;
		}
	}

	/**
	 * Update columns on the Ad post page.
	 *
	 * @param array $columns The current columns.
	 *
	 * @return array
	 */
	public function manage_columns( $columns ) {

		/**
		 * Change existing columns
		 */
		unset( $columns['author'] );

		/**
		 * Sort columns and add new
		 */
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( $key === 'title' ) {
				$new_columns['type'] = __( 'Type', 'ad-commander' );
			} elseif ( $key === 'taxonomy-adcmdr_group' ) {
				$new_columns['shortcode']    = __( 'Shortcode', 'ad-commander' );
				$new_columns['template_tag'] = __( 'Template Tag', 'ad-commander' );

				if ( Tracking::instance()->is_local_tracking_enabled() ) {
					$new_columns['stats'] = __( 'Stats', 'ad-commander' );
				}
			}
		}

		return $new_columns;
	}

	/**
	 * Update sortable columns on the ad page.
	 *
	 * @param array $columns The current columns.
	 *
	 * @return array
	 */
	public function manage_sortable_columns( $columns ) {
		$columns['type'] = 'type';
		return $columns;
	}

	/**
	 * Create a group filter on ad table.
	 *
	 * @param string $post_type The current post type.
	 *
	 * @return void
	 */
	public function ad_group_filter( $post_type ) {

		if ( AdCommander::posttype_ad() !== $post_type ) {
			return;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		$taxonomy_name = AdCommander::tax_group();

		$groups = get_terms(
			array(
				'taxonomy'   => $taxonomy_name,
				'hide_empty' => false,
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$selected = isset( $_GET[ $taxonomy_name ] ) && $_GET[ $taxonomy_name ] ? sanitize_text_field( $_GET[ $taxonomy_name ] ) : '';

		if ( $groups ) {
			?>
				<select name="<?php echo esc_attr( $taxonomy_name ); ?>">
					<option value=""><?php echo esc_html__( 'All groups', 'ad-commander' ); ?></option>
					<?php foreach ( $groups as $group ) : ?>
						<option value="<?php echo esc_attr( $group->slug ); ?>"<?php selected( $selected, $group->slug ); ?>><?php echo esc_html( $group->name ); ?></option>
					<?php endforeach ?>
				</select>
			<?php
		}
	}

	/**
	 * Create an ad type filter on ad table.
	 *
	 * @param string $post_type The current post type.
	 *
	 * @return void
	 */
	public function ad_type_filter( $post_type ) {

		if ( AdCommander::posttype_ad() !== $post_type ) {
			return;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		$ad_types = AdPostMeta::ad_types();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$selected = isset( $_GET['adcmdr_ad_type'] ) && $_GET['adcmdr_ad_type'] ? sanitize_text_field( $_GET['adcmdr_ad_type'] ) : '';

		if ( $ad_types ) {
			?>
				<select name="adcmdr_ad_type">
					<option value=""><?php echo esc_html__( 'All ad types', 'ad-commander' ); ?></option>
					<?php foreach ( $ad_types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $selected, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach ?>
				</select>
			<?php
		}
	}

	/**
	 * Sort the current query.
	 *
	 * @param mixed $query The current $query.
	 *
	 * @return void
	 */
	public function ads_sort_pre_get_posts( $query ) {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		if ( ! is_admin() || $pagenow !== 'edit.php' || ! isset( $_GET['post_type'] ) || AdCommander::posttype_ad() !== sanitize_text_field( $_GET['post_type'] ) ) {
			return;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		/**
		 * Sorting by position while filtering by position has unexpected results.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$filtered_ad_type = isset( $_GET['adcmdr_ad_type'] ) ? sanitize_text_field( $_GET['adcmdr_ad_type'] ) : '';
		$orderby          = $query->get( 'orderby' );

		if ( $filtered_ad_type !== '' && $orderby === 'type' ) {
			return;
		}

		/**
		 * Order our query.
		 */
		if ( 'type' == $orderby ) {
			$adtype_key = $this->wo_meta->make_key( 'adtype' );
			$meta_query = array(
				'relation' => 'OR',
				array(
					'key'     => $adtype_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => $adtype_key,
				),
			);

			$query->set( 'meta_query', $meta_query );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Filter the admin query to add ad type meta_query.
	 *
	 * @param mixed $query The current query.
	 *
	 * @return void
	 */
	public function filter_ads_by_type( $query ) {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		if ( ! is_admin() || $pagenow !== 'edit.php' || ! isset( $_GET['post_type'] ) || AdCommander::posttype_ad() !== sanitize_text_field( $_GET['post_type'] ) ) {
			return;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$ad_type = isset( $_GET['adcmdr_ad_type'] ) ? sanitize_text_field( $_GET['adcmdr_ad_type'] ) : '';

		if ( $ad_type !== '' ) {
			$query->query_vars['meta_query'][] = array(
				'key'     => $this->wo_meta->make_key( 'adtype' ),
				'value'   => $ad_type,
				'compare' => '=',
			);
		}
	}
}
