<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Admin meta and related functionality for Group taxonomy terms.
 */
class AdminGroupTermMeta extends Admin {
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
		$this->nonce   = $this->nonce( basename( __FILE__ ), AdCommander::tax_group() );

		add_action( AdCommander::tax_group() . '_edit_form_fields', array( $this, 'edit_term_fields' ), 10, 1 );
		add_action( AdCommander::tax_group() . '_term_edit_form_top', array( $this, 'before_form_table' ), 10, 1 );
		add_action( AdCommander::tax_group() . '_edit_form', array( $this, 'after_form_table' ), 10, 1 );

		add_action( 'edited_' . AdCommander::tax_group(), array( $this, 'update_group_meta' ), 10, 1 );
		add_action( 'created_' . AdCommander::tax_group(), array( $this, 'update_group_meta' ), 10, 1 );
		add_action( 'set_object_terms', array( $this, 'set_object_terms_count' ), 10, 6 );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		foreach ( $this->get_action_keys() as $key ) {
			$key_underscore = self::_key( $key );
			add_action( 'wp_ajax_' . $this->action_string( $key ), array( $this, 'action_' . $key_underscore ) );
		}

		// Add info to the new columns
		add_action( 'manage_' . AdCommander::tax_group() . '_custom_column', array( $this, 'manage_group_column_data' ), 10, 3 );
		add_filter( 'manage_edit-' . AdCommander::tax_group() . '_columns', array( $this, 'manage_group_columns' ) );
		add_filter( 'manage_edit-' . AdCommander::tax_group() . '_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		add_filter( 'quick_edit_enabled_for_taxonomy', array( $this, 'quick_edit_enabled_for_groups' ), 10, 2 );

		add_filter( 'pre_get_terms', array( $this, 'sort_group_terms' ) );

		$this->admin_targeting()->hooks();
	}

	/**
	 * Disable quick edit for groups.
	 *
	 * @param boolean $bool If quick edit is currently enabled.
	 * @param mixed   $taxonomy The current taxonomy.
	 *
	 * @return bool
	 */
	public function quick_edit_enabled_for_groups( $bool, $taxonomy ) {
		return ( $taxonomy === AdCommander::tax_group() ) ? false : $bool;
	}

	/**
	 * Potentially update the number of ads in a group when a term is saved.
	 *
	 * @param int    $object_id The term that was saved.
	 * @param array  $terms An array of object term IDs or slugs.
	 * @param array  $tt_ids An array of term taxonomy IDs.
	 * @param string $taxonomy Taxonomy slug.
	 * @param bool   $append Whether to append new terms to the old terms.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 *
	 * @return void
	 */
	public function set_object_terms_count( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $taxonomy !== AdCommander::tax_group() ) {
			return;
		}

		/**
		 * Compare tt_ids and $old_ttids and refresh any that are different.
		 */
		$this->refresh_group_count_transient( $tt_ids, $old_tt_ids );
	}

	/**
	 * Compare new and old term IDs. Remove the count transient for any terms that were updated.
	 *
	 * @param array $tt_ids An array of term taxonomy IDs.
	 * @param array $old_tt_ids Old array of term taxonomy IDs.
	 *
	 * @return void
	 */
	private function refresh_group_count_transient( $tt_ids, $old_tt_ids ) {
		$term_ids = array_merge( array_diff( $tt_ids, $old_tt_ids ), array_diff( $old_tt_ids, $tt_ids ) );

		if ( ! empty( $term_ids ) ) {
			foreach ( $term_ids as $term_id ) {
				delete_transient( GroupTermMeta::group_count_transient( $term_id ) );
			}
		}
	}

	/**
	 * Enqueue scripts if we're on a screen that needs them.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( $this->is_screen( array( 'edit-' . AdCommander::tax_group() ) ) ) {

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-ui-autocomplete' );

			$this->enqueue_jquery_ui_styles();

			$targeting_handle = $this->admin_targeting()->enqueue();

			wp_register_script( 'worotate', AdCommander::assets_url() . 'js/rotate.js', array(), AdCommander::version(), array( 'in_footer' => true ) );
			wp_enqueue_script( 'worotate' );

			$handle = Util::ns( 'term-meta' );
			wp_register_script( $handle, AdCommander::assets_url() . 'js/term-meta.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-sortable', 'worotate', $targeting_handle ), AdCommander::version(), array( 'in_footer' => true ) );
			wp_enqueue_script( $handle );

			Util::enqueue_script_data(
				$handle,
				array(
					'ajaxurl'          => admin_url( 'admin-ajax.php' ),
					'terms_url'        => admin_url( 'term.php' ),
					'tax_name'         => AdCommander::tax_group(),
					'post_type'        => AdCommander::posttype_ad(),
					'actions'          => $this->get_ajax_actions(),
					'save_button_text' => esc_html__( 'Save all settings', 'ad-commander' ),
					'sort_handle_url'  => self::sortable_handle_url(),
				)
			);
		}
	}

	/**
	 * Get necessary action keys, which will be used to create wp_ajax hooks.
	 *
	 * @return array
	 */
	private function get_action_keys() {
		return array(
			'delete-ad-from-group',
			'update-ad-order',
			'get-ads-for-search',
			'add-ad-to-term',
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
	 * A wp_ajax hook that updates the order of ads in a group.
	 *
	 * @return void
	 */
	public function action_update_ad_order() {
		$action = 'update-ad-order';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( ! isset( $_POST['group_id'] ) ) {
			wp_die();
		}

		$group_id = ( isset( $_POST['group_id'] ) ) ? absint( $_POST['group_id'] ) : null;
		$ad_order = ( isset( $_POST['ad_order'] ) && ! empty( $_POST['ad_order'] ) ) ? array_map( 'absint', $_POST['ad_order'] ) : array();

		if ( update_term_meta( $group_id, $this->wo_meta->make_key( 'ad_order' ), $ad_order ) ) {
			wp_send_json_success(
				array(
					'action'  => $action,
					'groupid' => $group_id,
				)
			);
		}

		wp_die();
	}

	/**
	 * A wp_ajax hook that deletes an ad from a group.
	 *
	 * @return void
	 */
	public function action_delete_ad_from_group() {
		$action = 'delete-ad-from-group';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( ! isset( $_POST['group_id'] ) || ! isset( $_POST['ad_id'] ) ) {
			wp_die();
		}

		$group_id = ( isset( $_POST['group_id'] ) ) ? absint( $_POST['group_id'] ) : null;
		$ad_id    = ( isset( $_POST['ad_id'] ) ) ? absint( $_POST['ad_id'] ) : null;

		if ( wp_remove_object_terms( $ad_id, $group_id, AdCommander::tax_group() ) ) {
			delete_transient( GroupTermMeta::group_count_transient( $group_id ) );

			wp_send_json_success(
				array(
					'action'  => $action,
					'groupid' => $group_id,
					'adid'    => $ad_id,
				)
			);
		}

		wp_die();
	}

	/**
	 * The name of the transient that stores our filtered ads.
	 *
	 * @return string
	 */
	public static function group_search_ads_transient() {
		return Util::ns( 'group_search_ads', '_' );
	}

	/**
	 * Store the available filter ads in a transient so we don't have to query them every time.
	 *
	 * @return array
	 */
	private function get_ads_for_search() {

		$ads = get_transient( self::group_search_ads_transient() );

		if ( $ads === false ) {
			$available_ads = Query::ads( 'post_title', 'asc', Util::any_post_status( 'trash' ), array(), array() );

			$ads = array();

			if ( ! empty( $available_ads ) ) {
				foreach ( $available_ads as $ad ) {
					$ads[] = array(
						'id'     => $ad->ID,
						'title'  => $ad->post_title,
						'status' => ( $ad->post_status !== 'publish' ) ? wp_strip_all_tags( Util::post_status_label( $ad->post_status ) ) : 'publish',
					);
				}
			}

			set_transient( self::group_search_ads_transient(), $ads, 20 );
		}

		return $ads;
	}

	/**
	 * Wp_ajax hook. Gets all of the ads for use in the filter.
	 *
	 * @return void
	 */
	public function action_get_ads_for_search() {
		$action = 'get-ads-for-search';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		wp_send_json_success(
			array(
				'action' => $action,
				'ads'    => $this->get_ads_for_search(),
			)
		);

		wp_die();
	}

	/**
	 * Wp_ajax hook. Gets all of the ads for use in the filter.
	 *
	 * @return void
	 */
	public function action_add_ad_to_term() {
		$action = 'add-ad-to-term';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( ! isset( $_POST['group_id'] ) || ! isset( $_POST['ad_id'] ) ) {
			wp_die();
		}

		$group_id = ( isset( $_POST['group_id'] ) ) ? absint( $_POST['group_id'] ) : null;
		$ad_id    = ( isset( $_POST['ad_id'] ) ) ? absint( $_POST['ad_id'] ) : null;

		if ( wp_add_object_terms( $ad_id, $group_id, AdCommander::tax_group() ) ) {
			delete_transient( GroupTermMeta::group_count_transient( $group_id ) );

			wp_send_json_success(
				array(
					'action'  => $action,
					'groupid' => $group_id,
					'adid'    => $ad_id,
				)
			);
		}

		wp_die();
	}

	/**
	 * Ad fields before the form table while viewing a Group term.
	 *
	 * @param object $term The current term.
	 *
	 * @return void
	 */
	public function before_form_table( $term ) {
		$this->nonce_field( $this->nonce );
		$this->back_to_groups();
		?>
		<div class="adcmdr-group-meta-wrap">
		<?php
	}

	public function after_form_table( $term ) {
		$targeting_title = __( 'Targeting', 'ad-commander' );

		if ( ! ProBridge::instance()->is_pro_loaded() ) {
			$targeting_title .= ProBridge::pro_label();
		}

		?>
			<div id="adcmdrtargetingdiv" class="postbox">
				<div class="postbox-header"><h2><?php echo esc_html( $targeting_title ); ?></h2></div>
				<?php $this->admin_targeting()->metaitem_targeting( $this->wo_meta->get_value( $this->current_meta, 'content_conditions' ), true, 'content', AdCommander::tax_group() ); ?>
				<?php $this->admin_targeting()->metaitem_targeting( $this->wo_meta->get_value( $this->current_meta, 'visitor_conditions' ), true, 'visitor', AdCommander::tax_group() ); ?>
			</div>
		</div>
		<?php
		$this->group_ad_list( $term );
		$this->group_preview( $term );
	}

	/**
	 * Back to groups link at top of term page.
	 */
	private function back_to_groups() {
		?>
		<div class="adcmdr-goback"><a href="<?php echo esc_url( self::admin_group_tax_url() ); ?>"><?php esc_html_e( '< Back to Groups', 'ad-commander' ); ?></a></div>
		<?php
	}

	/**
	 * List of ads on group term page.
	 *
	 * @param object $term The current term.
	 */
	private function group_ad_list( $term ) {
		$sorted_ids = get_term_meta( $term->term_id, $this->wo_meta->make_key( 'ad_order' ), true );
		$ad_weights = get_term_meta( $term->term_id, $this->wo_meta->make_key( 'ad_weights' ), true );

		if ( ! $sorted_ids || ! is_array( $sorted_ids ) ) {
			$sorted_ids = array();
		}

		$ads = Group::sort_ads_by_sorted_ids( Query::ads_by_group( $term->term_id, Util::any_post_status() ), $sorted_ids );
		?>
		<div class="<?php echo esc_attr( Util::ns( 'group-ad-list' ) ); ?>" data-groupid="<?php echo absint( $term->term_id ); ?>">
		<div class="adcmdr-term-ad-search">
			<label for="adcmdr_search_ads"><?php esc_html_e( 'Add ad to group:', 'ad-commander' ); ?></label>
			<input type="text" value="" id="adcmdr_search_ads" name="adcmdr_search_ads" placeholder="<?php esc_html_e( 'Search by ad title', 'ad-commander' ); ?>" />
		</div>
		<h2><?php esc_html_e( 'Ads in this group', 'ad-commander' ); ?></h2>
		<table class="wp-list-table widefat"
			<?php
			if ( empty( $ads ) ) :
				?>
				style="display:none;"<?php endif; ?>>
				<thead>
					<tr>
						<th class="adcmdr-handle"></th>
						<th class="adcmdr-weight"><?php esc_html_e( 'Weight', 'ad-commander' ); ?></th>
						<th class="adcmdr-title"><?php esc_html_e( 'Ad', 'ad-commander' ); ?></th>
						<th class="adcmdr-action"></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! empty( $ads ) ) : ?>
					<?php foreach ( $ads as $ad ) : ?>
				<tr data-adid="<?php echo absint( $ad->ID ); ?>" class="adcmdr-ad-has-status--<?php echo esc_attr( $ad->post_status ); ?>">
					<td class="adcmdr-handle">
						<?php Admin::sortable_handle(); ?>
					</td>
					<td class="adcmdr-weight">
						<?php
						$current_weight = isset( $ad_weights[ $ad->ID ] ) ? $ad_weights[ $ad->ID ] : 1;
						$this->wo_meta->input(
							'ad_weights[' . absint( $ad->ID ) . ']',
							$current_weight,
							'number'
						);
						?>
					</td>
					<td class="adcmdr-title">
						<a href="<?php echo esc_url( Admin::edit_ad_post_url( $ad->ID, $ad->post_status ) ); ?>"><?php echo esc_html( $ad->post_title ); ?></a>
						<?php if ( $ad->post_status !== 'publish' ) : ?>
							<span class="adcmdr-ad-status">&mdash; <?php echo esc_html( Util::post_status_label( $ad->post_status ) ); ?></span>
						<?php endif; ?>
					</td>
					<td class="adcmdr-action">
						<button title="Remove from group" class="<?php echo esc_attr( Util::ns( 'del' ) ); ?>"><?php include AdCommander::assets_path() . 'img/remove.svg'; ?></button>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<p class="adcmdr-no-ads"
			<?php
			if ( ! empty( $ads ) ) :
				?>
				style="display:none;"<?php endif; ?>><?php esc_html_e( 'There are currently no ads in this group.', 'ad-commander' ); ?></p>
			<input type="submit" class="button button-primary" value="<?php esc_html_e( 'Save all settings', 'ad-commander' ); ?>" style="display:none;">
			<span class="adcmdr-loader"></span>
		</div>
			<?php
	}

	/**
	 * The usage instructions for a Group term.
	 *
	 * @param object $term The current term.
	 */
	private function usage( $term ) {
		if ( isset( $term->term_id ) ) :
			?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'usage', 'group' ) ) ); ?>">
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php $this->wo_meta->label( 'shortcode', __( 'Shortcode', 'ad-commander' ) ); ?>
				<?php
				$this->wo_meta->input(
					'shortcode',
					'[adcmdr_group id="' . $term->term_id . '"]',
					'text',
					array(
						'readonly' => true,
						'classes'  => 'code',
					)
				);
				self::copy_button_group_shortcode( $term->term_id );
				?>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?>">
				<?php $this->wo_meta->label( 'template', __( 'Template function', 'ad-commander' ) ); ?>
				<?php
				$this->wo_meta->input(
					'template',
					'<?php adcmdr_the_group(' . $term->term_id . '); ?>',
					'text',
					array(
						'readonly' => true,
						'classes'  => 'code',
					)
				);
				self::copy_button_group_template_tag( $term->term_id );
				?>
			</div>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem__subitem' ) ); ?> adcmdr-self-flex-end">
				<?php Doc::doc_link( 'manual_placement' ); ?>
			</div>
		</div>
			<?php
		endif;
	}

	/**
	 * Button to copy an ad shortcode.
	 *
	 * @param int $post_id The post ID to copy.
	 *
	 * @return void
	 */
	public static function copy_button_group_shortcode( $post_id ) {
		?>
		<button data-adcmdr-copy='[adcmdr_group id="<?php echo absint( $post_id ); ?>"]' title="<?php echo esc_attr( __( 'Copy', 'ad-commander' ) ); ?>"><i class="dashicons dashicons-clipboard"></i></button>
		<?php
	}

	/**
	 * Button to copy an ad template tag.
	 *
	 * @param int $post_id The post ID to copy.
	 *
	 * @return void
	 */
	public static function copy_button_group_template_tag( $post_id ) {
		?>
		<button data-adcmdr-copy='&lt;?php adcmdr_the_group(<?php echo absint( $post_id ); ?>); ?&gt;' title="<?php echo esc_attr( __( 'Copy', 'ad-commander' ) ); ?>"><i class="dashicons dashicons-clipboard"></i></button>
		<?php
	}


	/**
	 * The preview window for a Group term.
	 *
	 * @param object $term The current term.
	 */
	private function group_preview( $term ) {
		?>
		<div class="<?php echo esc_attr( Util::ns( 'group-preview' ) ); ?>" id="adcmdr-group-preview">
			<h2><?php esc_html_e( 'Group Preview', 'ad-commander' ); ?></h2>
			<?php

			if ( isset( $term->term_id ) ) :
				$this->usage( $term );
				?>
			<div class="<?php echo esc_attr( Util::ns( 'metaitem' ) . ' ' . Util::ns( 'metaitem--divide' ) ); ?>">
				<?php
				$this->info( __( 'Script tags and other unsafe HTML are removed in the admin preview. Ads may appear differently to visitors due to front-end styles from your theme or other plugins.', 'ad-commander' ) );
				/**
				 * Stripping script tags because this is in the admin.
				 */
				echo wp_kses_post(
					adcmdr_display_group(
						$term->term_id,
						array(
							'display'          => false,
							'force_noajax'     => true,
							'disable_wrappers' => false,
						)
					)
				);
				?>
			</div>
				<?php
			endif;
			?>
		</div>
			<?php
	}

	/**
	 * Term fields on the edit Group term page.
	 *
	 * @param object $term The current term.
	 *
	 * @return void
	 */
	public function edit_term_fields( $term ) {

		$term_id = null;

		if ( isset( $term->term_id ) ) {
			$term_id = $term->term_id;
		}

		$this->current_meta = $this->wo_meta->get_term_meta( $term_id, GroupTermMeta::tax_group_meta_keys() );

		/**
		 * Mode
		*/
		$modes        = Group::modes();
		$current_mode = $this->wo_meta->get_value( $this->current_meta, 'mode' );

		if ( ! ProBridge::instance()->is_pro_loaded() ) {

			$nonpro_modes = array();
			foreach ( $modes as $key => $mode ) {
				if ( in_array( $key, ProBridge::instance()->pro_group_modes() ) ) {
					$key   = 'disabled:' . $key;
					$mode .= ProBridge::pro_label();

					if ( $current_mode === $key ) {
						$current_mode = 'single';
					}
				}

				$nonpro_modes[ $key ] = $mode;
			}

			$modes = $nonpro_modes;
		}

		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'mode', __( 'Mode', 'ad-commander' ), array( 'display' => false ) ), // th
			$this->wo_meta->select( 'mode', $modes, $current_mode, array( 'display' => false ) ) . Doc::doc_link( 'group_mode', false ), // td
			array( 'message' => 'Choose a display mode for this group.' )
		);

		/**
		 * Grid
		 */
		$gridrowscols  = '<div class="' . esc_attr( Util::ns( 'multifield' ) ) . '">';
		$gridrowscols .= $this->wo_meta->input( 'grid-cols', $this->wo_meta->get_value( $this->current_meta, 'grid-cols' ), 'number', array( 'display' => false ) );
		$gridrowscols .= $this->wo_meta->label(
			'grid-cols',
			__( 'columns', 'ad-commander' ),
			array(
				'display' => false,
				'classes' => Util::ns( 'inline' ),
			)
		);

		$gridrowscols .= $this->wo_meta->input( 'grid-rows', $this->wo_meta->get_value( $this->current_meta, 'grid-rows' ), 'number', array( 'display' => false ) );
		$gridrowscols .= $this->wo_meta->label(
			'grid-rows',
			__( 'rows', 'ad-commander' ),
			array(
				'display' => false,
				'classes' => Util::ns( 'inline' ),
			)
		);
		$gridrowscols .= '</div>';

		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'grid-cols', __( 'Grid', 'ad-commander' ), array( 'display' => false ) ), // th
			$gridrowscols,
			array(
				'classes' => array( Util::ns( 'mode-restrict' ), Util::ns( 'mode-restrict--grid' ) ),
				'message' => __( 'Number of columns and rows to display in this grid.', 'ad-commander' ),
			)
		);

		/**
		 * Refresh
		 */
		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'refresh', __( 'Rotate Interval', 'ad-commander' ), array( 'display' => false ) ), // th
			$this->wo_meta->input( 'refresh', $this->wo_meta->get_value( $this->current_meta, 'refresh' ), 'number', array( 'display' => false ) ),
			array(
				'classes' => array( Util::ns( 'mode-restrict' ), Util::ns( 'mode-restrict--rotate' ) ),
				'message' => __( 'Rotate ads every X seconds.', 'ad-commander' ),
			)
		);

		/**
		 * Refresh
		 */
		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'stop_tracking_i', __( 'Stop Tracking', 'ad-commander' ), array( 'display' => false ) ), // th
			$this->wo_meta->input( 'stop_tracking_i', $this->wo_meta->get_value( $this->current_meta, 'stop_tracking_i', 0 ), 'number', array( 'display' => false ) ),
			array(
				'classes' => array( Util::ns( 'mode-restrict' ), Util::ns( 'mode-restrict--rotate' ) ),
				'message' => __( 'Stop tracking impressions after X seconds of rotating. 0 to track until visitor closes page.', 'ad-commander' ),
			)
		);

		/**
		 * Ad Order
		 */
		$methods = GroupTermMeta::allowed_order_methods();
		if ( ! ProBridge::instance()->is_pro_loaded() ) {
			$nonpro_methods = array();
			foreach ( $methods as $key => $method ) {
				if ( in_array( $key, ProBridge::instance()->pro_group_order_methods() ) ) {
					$key     = 'disabled:' . $key;
					$method .= ProBridge::pro_label();
				}

				$nonpro_methods[ $key ] = $method;
			}

			$methods = $nonpro_methods;
		}
		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'order_method', __( 'Order Method', 'ad-commander' ), array( 'display' => false ) ),
			$this->wo_meta->radiogroup( 'order_method', $methods, $this->wo_meta->get_value( $this->current_meta, 'order_method', 'random' ), array( 'display' => false ) ) . Doc::doc_link( 'group_order', false ),
		);

		/**
		 * Ad Label
		 */
		$radios = Util::site_default_options();
		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'ad_label', __( 'Ad Label', 'ad-commander' ), array( 'display' => false ) ),
			$this->wo_meta->radiogroup( 'ad_label', $radios, $this->wo_meta->get_value( $this->current_meta, 'ad_label', 'site_default' ), array( 'display' => false ) ),
		);

		/**
		 * Responsive
		 */
		$radios = Util::site_default_options();
		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'responsive_banners', __( 'Responsive IMGs', 'ad-commander' ), array( 'display' => false ) ),
			$this->wo_meta->radiogroup( 'responsive_banners', $radios, $this->wo_meta->get_value( $this->current_meta, 'responsive_banners', 'site_default' ), array( 'display' => false ) ),
			array(
				'classes' => array( Util::ns( 'field-divide' ) ),
			)
		);

		/**
		 * Float
		 */
		$radios      = Util::float_options();
		$float_html  = '<div class="' . Util::ns( 'multifield ' ) . Util::ns( 'multifield--col">' );
		$float_html .= $this->wo_meta->radiogroup( 'float', $radios, $this->wo_meta->get_value( $this->current_meta, 'float', 'no' ), array( 'display' => false ) );
		$float_html .= '<div>';
		$float_html .= $this->wo_meta->checkbox( 'clear_float', $this->wo_meta->get_value( $this->current_meta, 'clear_float' ), 1, array( 'display' => false ) );
		$float_html .= $this->wo_meta->label(
			'clear_float',
			__( 'Attempt to prevent text from wrapping around floated group', 'ad-commander' ),
			array(
				'display' => false,
				'classes' => Util::ns( 'inline' ),
			)
		);
		$float_html .= '</div>';

		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'float', __( 'Float', 'ad-commander' ), array( 'display' => false ) ), // th
			$float_html,
		);
		$float_html .= '</div>';

		/**
		 * Margin
		 */
		$margin_html = '<div class="' . Util::ns( 'multifield">' );
		foreach ( array(
			'top'    => __( 'top', 'ad-commander' ),
			'right'  => __( 'right', 'ad-commander' ),
			'bottom' => __( 'bottom', 'ad-commander' ),
			'left'   => __( 'left', 'ad-commander' ),
		) as $key => $margin ) {

			$key = 'margin_' . $key;

			$margin_html .= $this->wo_meta->input( $key, $this->wo_meta->get_value( $this->current_meta, $key ), 'number', array( 'display' => false ) );
			$margin_html .= $this->wo_meta->label(
				$key,
				$margin,
				array(
					'display' => false,
					'classes' => Util::ns( 'inline' ),
				)
			);
		}
		$margin_html .= '</div>';

		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'margin_top', __( 'Margin', 'ad-commander' ), array( 'display' => false ) ), // th
			$margin_html,
		);

		/**
		 * Custom classes
		 */
		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'custom_classes', __( 'Custom Classes', 'ad-commander' ), array( 'display' => false ) ),
			$this->wo_meta->input( 'custom_classes', $this->wo_meta->get_value( $this->current_meta, 'custom_classes' ), 'text', array( 'display' => false ) ),
			array(
				'classes' => array( Util::ns( 'field-divide' ) ),
			)
		);

		/**
		 * Custom code
		 */
		$this->wo_meta->term_meta_row(
			Doc::doc_link( 'custom_code', false ),
			'',
		);

		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'custom_code_before', __( 'Custom Code - Before Ads', 'ad-commander' ), array( 'display' => false ) ),
			$this->wo_meta->textarea(
				'custom_code_before',
				$this->wo_meta->get_value( $this->current_meta, 'custom_code_before' ),
				array(
					'display' => false,
					'rows'    => 5,
				)
			),
			array(
				'allow_unfiltered_html' => true, // Allow scripts in this meta row.
			)
		);

		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'custom_code_after', __( 'Custom Code - After Ads', 'ad-commander' ), array( 'display' => false ) ),
			$this->wo_meta->textarea(
				'custom_code_after',
				$this->wo_meta->get_value( $this->current_meta, 'custom_code_after' ),
				array(
					'display' => false,
					'rows'    => 5,
				)
			),
			array(
				'allow_unfiltered_html' => true, // Allow scripts in this meta row.
			)
		);

		$this->wo_meta->term_meta_row(
			$this->wo_meta->label( 'disable_consent', __( 'Disable Consent', 'ad-commander' ), array( 'display' => false ) ),
			$this->wo_meta->checkbox( 'disable_consent', $this->wo_meta->get_value( $this->current_meta, 'disable_consent' ), 1, array( 'display' => false ) ) .
			$this->wo_meta->label( 'disable_consent', __( 'Ignore site-wide setting and disable consent requirement for this group.', 'ad-commander' ), array( 'display' => false ) )
		);
	}

	/**
	 * Save posted meta data. Interfaces with WOMeta.
	 *
	 * @param int $term_id The Term ID that has saved.
	 *
	 * @return void
	 */
	public function update_group_meta( $term_id ) {
		$group_allowed_meta = GroupTermMeta::tax_group_meta_keys();
		$this->wo_meta->save_posted_term_metadata( $term_id, $group_allowed_meta, $this->nonce, AdCommander::capability() );

		/**
		 * When term is first created in the admin UI, no meta saves because it was not posted.
		 * Save some required term meta for sorting purposes.
		 */
		$term_meta  = get_term_meta( $term_id );
		$no_empties = array( 'mode', 'order_method' );

		foreach ( $no_empties as $no_empty ) {
			$full_key = $this->wo_meta->make_key( $no_empty );
			if ( ! isset( $term_meta[ $full_key ] ) && isset( $group_allowed_meta[ $no_empty ]['default'] ) ) {
				update_term_meta( $term_id, $full_key, $group_allowed_meta[ $no_empty ]['default'] );
			}
		}
	}

	/**
	 * Output content within an individual column on the Group taxonomy page.
	 *
	 * @param string $string Column name.
	 * @param array  $column Existing columns.
	 * @param int    $term_id The Term ID.
	 *
	 * @return void
	 */
	public function manage_group_column_data( $string, $column, $term_id ) {
		switch ( $column ) {
			case 'mode':
				$modes = Group::modes();
				$key   = get_term_meta( $term_id, $this->wo_meta->make_key( 'mode' ), true );
				if ( ! $key ) {
					$key = 'single';
				}

				echo ( isset( $modes[ $key ] ) ) ? esc_html( $modes[ $key ] ) : '';
				break;

			case 'order_method':
				$order_method = get_term_meta( $term_id, $this->wo_meta->make_key( 'order_method' ), true );
				echo esc_html( ucfirst( $order_method ? $order_method : 'random' ) );
				break;

			case 'shortcode':
				self::copy_button_group_shortcode( $term_id );
				break;

			case 'template_tag':
				self::copy_button_group_template_tag( $term_id );
				break;
		}
	}

	/**
	 * Update columns on the Group taxonomy page.
	 *
	 * @param array $columns The current columns.
	 *
	 * @return array
	 */
	public function manage_group_columns( $columns ) {

		/**
		 * Change existing columns
		 */
		unset( $columns['description'] );
		unset( $columns['slug'] );

		$columns['posts'] = 'Ads';

		/**
		 * Sort columns and add new
		 */
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( $key === 'name' ) {
				$new_columns['mode']         = __( 'Mode', 'ad-commander' );
				$new_columns['order_method'] = __( 'Order Method', 'ad-commander' );
				$new_columns['shortcode']    = __( 'Shortcode', 'ad-commander' );
				$new_columns['template_tag'] = __( 'Template Tag', 'ad-commander' );
			}
		}

		return $new_columns;
	}

	/**
	 * Update sortable columns on the group taxonomy page.
	 *
	 * @param array $columns The current columns.
	 *
	 * @return array
	 */
	public function manage_sortable_columns( $columns ) {
		$columns['mode']         = 'mode';
		$columns['order_method'] = 'order_method';
		return $columns;
	}

	/**
	 * @param mixed $pieces
	 * @param mixed $taxonomies
	 * @param mixed $args
	 *
	 * @return [type]
	 */
	public function sort_group_terms( $term_query ) {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		if ( ! is_admin() || $pagenow !== 'edit-tags.php' || ! isset( $_GET['taxonomy'] ) || sanitize_text_field( $_GET['taxonomy'] ) !== AdCommander::tax_group() || ! isset( $_GET['orderby'] ) ) {
			return $term_query;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return $term_query;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : '';

		if ( $orderby ) {
			$orderby_key = $this->wo_meta->make_key( $orderby );

			$meta_query = array(
				'relation' => 'OR',
				array(
					'key'     => $orderby_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => $orderby_key,
				),
			);

			$term_query->meta_query = new \WP_Meta_Query( $meta_query );

			$term_query->query_vars['orderby'] = 'meta_value';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
			$term_query->query_vars['order'] = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'desc';
		}

		return $term_query;
	}
}
