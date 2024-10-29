<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Admin meta and related functionality for Placement posts.
 */
class AdminPlacementPostMeta extends Admin {
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
		$this->nonce = $this->nonce( basename( __FILE__ ), AdCommander::posttype_placement() );

		add_action( 'edit_form_after_title', array( $this, 'posttype_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'save_post_' . AdCommander::posttype_placement(), array( $this, 'save_posted_metadata' ), 10, 2 );

		/**
		 * Placement post type edit.php
		 */
		add_action( 'manage_' . AdCommander::posttype_placement() . '_posts_custom_column', array( $this, 'manage_column_data' ), 10, 3 );
		add_filter( 'manage_edit-' . AdCommander::posttype_placement() . '_columns', array( $this, 'manage_columns' ) );
		add_filter( 'manage_edit-' . AdCommander::posttype_placement() . '_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'placement_sort_pre_get_posts' ) );

		add_action( 'restrict_manage_posts', array( $this, 'placement_position_filter' ) );
		add_filter( 'parse_query', array( $this, 'filter_placements_by_position' ) );

		/**
		 * Targeting
		 */
		$this->admin_targeting()->hooks();
	}

	/**
	 * Create a new WOMeta instance if necessary.
	 *
	 * @return WOMeta
	 */
	public function meta() {
		if ( ! $this->wo_meta ) {
			$this->wo_meta = new WOMeta( AdCommander::ns() );
		}

		return $this->wo_meta;
	}

	/**
	 * Sets the current meta if it is not yet set.
	 *
	 * @return mixed
	 */
	private function current_meta() {
		global $post;

		if ( ! $this->current_meta && isset( $post->ID ) ) {
			$this->current_meta = $this->meta()->get_post_meta( $post->ID, PlacementPostMeta::post_meta_keys() );
		}

		return $this->current_meta;
	}

	/**
	 * Enqueue scripts if we're on a screen that needs them.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( $this->is_screen( array( AdCommander::posttype_placement() ) ) ) {

			wp_enqueue_style( 'wp-color-picker' );

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_register_script( 'wp-color-picker-alpha', AdCommander::assets_url() . 'vendor/wp-color-picker-alpha.js', array( 'wp-color-picker' ), AdCommander::version(), array( 'in_footer' => true ) );
			wp_enqueue_script( 'wp-color-picker-alpha' );

			$settingrestrict_handle = $this->enqueue_setting_restrict();
			$repeater_handle        = $this->meta()->repeater_enqueue();
			$targeting_handle       = $this->admin_targeting()->enqueue();

			$deps = array(
				'jquery',
				'wp-color-picker-alpha',
				$settingrestrict_handle,
				$repeater_handle,
				$targeting_handle,
			);

			$handle = Util::ns( 'placement' );

			wp_register_script(
				$handle,
				AdCommander::assets_url() . 'js/placement-post.js',
				$deps,
				AdCommander::version(),
				array( 'in_footer' => true )
			);

			wp_enqueue_script( $handle );
		}
	}

	/**
	 * Hook function for creating/editing meta boxes for our post type.
	 *
	 * @return void
	 */
	public function posttype_meta_boxes() {
		$current_screen = get_current_screen();

		if ( isset( $current_screen->id ) && $current_screen->id === AdCommander::posttype_placement() ) {

			/**
			 * Insert our nonce field
			 */
			$this->nonce_field( $this->nonce );
			/**
			 * General
			 */
			add_meta_box( 'adcmdrsettingsdiv', __( 'Placement Display Settings', 'ad-commander' ), array( $this, 'adcmdrsettings_meta_box' ), AdCommander::posttype_placement(), 'normal', 'high' );
			add_meta_box( 'adcmdritemsdiv', __( 'Placement Groups & Ads', 'ad-commander' ), array( $this, 'adcmdritems_meta_box' ), AdCommander::posttype_placement(), 'normal', 'high' );
			add_meta_box( 'adcmdrorderdiv', __( 'Placement Settings', 'ad-commander' ), array( $this, 'adcmdrorderdiv_meta_box' ), AdCommander::posttype_placement(), 'side' );

			/**
			 * Targeting
			 */
			$targeting_title = __( 'Targeting', 'ad-commander' );
			if ( ! ProBridge::instance()->is_pro_loaded() ) {
				$targeting_title .= ProBridge::pro_label();
			}

			add_meta_box( 'adcmdrtargetingdiv', $targeting_title, array( $this, 'adcmdrtargeting_meta_box' ), AdCommander::posttype_placement(), 'normal', 'high' );
		}
	}

	/**
	 * Add fields to the Settings meta box.
	 *
	 * @return void
	 */
	public function adcmdrsettings_meta_box() {
		$this->metaitem_placement_position();
		// $this->metaitem_placement_post_types();
	}

	/**
	 * Add fields to the Items meta box.
	 *
	 * @return void
	 */
	public function adcmdritems_meta_box() {
		$this->metaitem_placement_items();
	}

	/**
	 * Add fields to the Order meta box.
	 *
	 * @return void
	 */
	public function adcmdrorderdiv_meta_box() {
		$this->metaitem_placement_order();
	}

	/**
	 * Add fields to the Targeting meta box.
	 *
	 * @return void
	 */
	public function adcmdrtargeting_meta_box() {
		$this->metaitem_content_targeting();
	}

	/**
	 * Placement position meta fields.
	 *
	 * TODO: Improvements to placement admin; Only show 'Post Types' when applicable, for example.
	 */
	private function metaitem_placement_position() {
		$positions        = PlacementPostMeta::placement_positions();
		$current_position = $this->meta()->get_value( $this->current_meta(), 'placement_position' );

		if ( ! ProBridge::instance()->is_pro_loaded() ) {

			$nonpro_positions = array();
			foreach ( $positions as $key => $position ) {
				if ( in_array( $key, ProBridge::instance()->pro_placement_positions() ) ) {
					$key       = 'disabled:' . $key;
					$position .= ProBridge::pro_label();

					if ( $current_position === $key ) {
						$current_position = null;
					}
				}

				$nonpro_positions[ $key ] = $position;
			}

			$positions = $nonpro_positions;
		}
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'placement_position' ) ); ?>">
			<?php
			$this->meta()->label( 'placement_position', __( 'Placement Position', 'ad-commander' ) );
			$this->meta()->select(
				'placement_position',
				$positions,
				$current_position,
			);
			Doc::doc_link( 'placement_position' );
			?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'disable_wrappers_body' ) ) ); ?>  adcmdr-mode-restrict adcmdr-mode-restrict--body_close_tag">
			<?php
			$this->meta()->label( 'disable_wrappers_body', __( 'Disable HTML Wrappers', 'ad-commander' ) );
			$this->meta()->checkbox( 'disable_wrappers_body', $this->meta()->get_value( $this->current_meta(), 'disable_wrappers_body', 1 ) );
			$this->meta()->label( 'disable_wrappers_body', __( 'Disable wrappers around ads and groups.', 'ad-commander' ) );
			$this->meta()->message( __( 'Recommended for most script placements. Disabling wrappers will break rotating and grid group layouts.', 'ad-commander' ) );
			?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'force_serverside' ) ) ); ?>  adcmdr-mode-restrict adcmdr-mode-restrict--body_close_tag">
			<?php
			$this->meta()->label( 'force_serverside_body', __( 'Force server-side rendering', 'ad-commander' ) );
			$this->meta()->checkbox( 'force_serverside_body', $this->meta()->get_value( $this->current_meta(), 'force_serverside_body', 1 ) );
			$this->meta()->label( 'force_serverside_body', __( 'Ignore global setting and load this placement using server-side rendering.', 'ad-commander' ) );
			$this->meta()->message( __( 'Visitor targeting and consent management may not work as expected if using page caching and server-side rendering.', 'ad-commander' ) )
			?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'head_close_tag' ) ) ); ?>  adcmdr-mode-restrict adcmdr-mode-restrict--head_close_tag">
			<?php
			$this->meta()->message( __( 'Intended for inserting scripts. All &lt;head&gt; placements are loaded with server-side rendering and HTML wrappers on ads and groups are disabled. Visitor targeting and consent management may not work as expected if using page caching.' ) )
			?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'post_list_position' ) ); ?> adcmdr-mode-restrict adcmdr-mode-restrict--post_list">
			<?php
			$this->meta()->label( 'post_list_position', __( 'Before Post #', 'ad-commander' ) );
			$this->meta()->input(
				'post_list_position',
				absint( $this->meta()->get_value( $this->current_meta(), 'post_list_position', 1 ) ),
				'number'
			);
			?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'within_content_position' ) ); ?> adcmdr-mode-restrict adcmdr-mode-restrict--within_content">
			<?php
			$this->meta()->label( 'within_content_position', __( 'Position in Content', 'ad-commander' ) );
			$this->meta()->select(
				'within_content_position',
				array(
					10 => 10,
					20 => 20,
					30 => 30,
					40 => 40,
					50 => 50,
					60 => 60,
					70 => 70,
					80 => 80,
					90 => 90,
				),
				$this->meta()->get_value( $this->current_meta(), 'within_content_position' ),
			);
			?>
			<span>%</span>
			<?php $this->meta()->message( __( 'Ads will display after this percentage of text content. Ads will only inject after a first-level element or within a Group block.', 'ad-commander' ) ); ?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'placement_popup' ) ); ?> adcmdr-mode-restrict adcmdr-mode-restrict--popup">
			<?php
			if ( ! ProBridge::instance()->pro_version_required( '1.0.4' ) ) {
				$this->info( __( 'Popup ads require Ad Commander Pro 1.0.4 or greater to function correctly. Please update your version of Pro.', 'ad-commander' ), 'adcmdr-notification adcmdr-notice-error' );
			}

			$when = array(
				'after_num_seconds'    => __( 'After # of seconds', 'ad-commander' ),
				'after_percent_scroll' => __( 'After user scrolls % of page', 'ad-commander' ),
			);
			$this->meta()->label( 'popup_display_when', __( 'When to display popup', 'ad-commander' ) );
			$this->meta()->radiogroup( 'popup_display_when', $when, $this->meta()->get_value( $this->current_meta(), 'popup_display_when', 'after_num_seconds' ) )
			?>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( 'popup_after_num_seconds' ) ); ?> adcmdr-popup-restrict adcmdr-popup-restrict--after_num_seconds">
				<?php
				$this->meta()->label( 'popup_after_num_seconds', __( 'Number of seconds before display', 'ad-commander' ) );
				$this->meta()->input(
					'popup_after_num_seconds',
					absint( $this->meta()->get_value( $this->current_meta(), 'popup_after_num_seconds', 20 ) ),
					'number'
				)
				?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( 'popup_after_percent_scroll' ) ); ?> adcmdr-popup-restrict adcmdr-popup-restrict--after_percent_scroll">
				<?php
				$this->meta()->label( 'popup_after_percent_scroll', __( 'Scroll percentage before display', 'ad-commander' ) );
				$this->meta()->input(
					'popup_after_percent_scroll',
					absint( $this->meta()->get_value( $this->current_meta(), 'popup_after_percent_scroll', 20 ) ),
					'number'
				)
				?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'popup_position', 'divide' ) ) ); ?>">
				<?php
					$position = $this->wo_meta->get_value( $this->current_meta(), 'popup_position', 'center-center' );
					$this->meta()->label( 'popup_position', __( 'Popup ad position', 'ad-commander' ) );
					$this->meta()->radiogroup(
						'popup_position',
						PlacementPostMeta::allowed_popup_positions(),
						$position ? $position : 'center-center',
						array(
							'classes'      => Util::ns( 'position-picker' ),
							'label_wrap'   => true,
							'text_classes' => 'screen-reader-text',
						)
					);
				?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'popup_overlay_bg' ) ) ); ?>">
				<?php
					$current_color = $this->wo_meta->get_value( $this->current_meta(), 'popup_overlay_bg', false );
					$current_color = $current_color ? $current_color : PlacementPostMeta::post_meta_keys()['popup_overlay_bg']['default'];
					$this->meta()->label( 'popup_overlay_bg', __( 'Popup overlay background', 'ad-commander' ) );
					$this->meta()->input(
						'popup_overlay_bg',
						$current_color,
						'text',
						array(
							'classes' => array( Util::ns( 'color-picker' ), 'color-picker' ),
							'data'    => array(
								'alpha-enabled' => 'true',
								'default-color' => PlacementPostMeta::post_meta_keys()['popup_overlay_bg']['default'],
							),
						)
					);
				?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'popup_hide_close_btn', 'divide' ) ) ); ?>">
				<?php
					$this->meta()->label( 'popup_hide_close_btn', __( 'Hide close button', 'ad-commander' ) );
					$this->meta()->checkbox( 'popup_hide_close_btn', $this->wo_meta->get_value( $this->current_meta(), 'popup_hide_close_btn', false ) );
					$this->meta()->label( 'popup_hide_close_btn', __( 'Hide the close button.', 'ad-commander' ) );
					$this->meta()->message( __( 'The overlay will still be closable by clicking outside the ad.', 'ad-commander' ) );
				?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( 'popup_auto_close_seconds' ) ); ?>">
				<?php
				$this->meta()->label( 'popup_auto_close_seconds', __( 'Auto close after number of seconds', 'ad-commander' ) );
				$this->meta()->input(
					'popup_auto_close_seconds',
					absint( $this->meta()->get_value( $this->current_meta(), 'popup_auto_close_seconds', 0 ) ),
					'number'
				);
				$this->meta()->message( __( 'Set to 0 to disable auto close.', 'ad-commander' ) );
				?>
			</div>
			<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'popup_learn_more' ) ) ); ?>">
				<?php Doc::doc_link( 'popup_placement', true, 'Learn more about popups' ); ?>
			</div>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'after_p_tag' ) ); ?> adcmdr-mode-restrict adcmdr-mode-restrict--after_p_tag">
			<?php
			$this->meta()->label( 'paragraph_number', __( 'After Paragraph Number', 'ad-commander' ) );
			$this->meta()->input(
				'paragraph_number',
				absint( $this->meta()->get_value( $this->current_meta(), 'paragraph_number', 30 ) ),
				'number'
			)
			?>
			<?php $this->meta()->message( __( 'Ads will inject after this paragraph number. They will inject into the parent of the paragraph.', 'ad-commander' ) ); ?>
		</div>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'disable_consent', 'divide' ) ) ); ?>">
			<?php
			$this->meta()->label( 'disable_consent', __( 'Disable Consent Requirement', 'ad-commander' ) );
			$this->meta()->checkbox( 'disable_consent', $this->wo_meta->get_value( $this->current_meta(), 'disable_consent', false ) );
			$this->meta()->label( 'disable_consent', __( 'Ignore site-wide setting and disable consent requirement for this placement.', 'ad-commander' ) );
			?>
		</div>
		<?php
	}

	/**
	 * Placement Post Type meta fields.
	 */
	private function metaitem_placement_post_types() {
		$post_types = Util::get_post_types();
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( array( 'placement_post_types', 'divide' ) ) ); ?>">
			<?php
			$current_values = $this->meta()->get_value( $this->current_meta(), 'placement_post_types' );

			if ( ! $current_values ) {
				$current_values = array( 'post' );
			}

			if ( ! empty( $post_types ) ) :
				$this->meta()->label( 'placement_post_types', __( 'Post Types', 'ad-commander' ) );
				$this->meta()->checkgroup( 'placement_post_types', $post_types, $current_values );
			else :
				$this->meta()->message( __( 'No public post types currently available.', 'ad-commander' ) );
			endif;
			?>
		</div>
		<?php
	}

	/**
	 * Placement Order meta fields.
	 */
	private function metaitem_placement_order() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'order' ) ); ?>">
			<?php
			$this->meta()->label( 'order', __( 'Placement Order', 'ad-commander' ) );
			$this->meta()->input(
				'order',
				$this->meta()->get_value( $this->current_meta(), 'order' ),
				'number',
				array(
					'default' => PlacementPostMeta::post_meta_keys()['order']['default'],
					'min'     => 1,
				)
			);
			$this->meta()->message( __( 'If you have multiple Placements displaying in the same position, the Placements will be ordered by this value.', 'ad-commander' ) );
			?>
		</div>
		<?php
	}

	/**
	 * Placement Items meta fields.
	 */
	private function metaitem_placement_items() {
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( 'placement_items' ) ); ?>">
			<?php
			$options      = array();
			$groups_terms = Query::groups();
			$ads          = Query::ads( 'post_title', 'asc', Util::any_post_status( array( 'trash' ) ) );

			if ( ! empty( $groups_terms ) ) {
				$options['disabled:groups'] = 'Groups:';
				foreach ( $groups_terms as $term ) {
					$options[ 'g_' . $term->term_id ] = $term->name;
				}
			}

			if ( ! empty( $ads ) ) {
				$options['disabled:ads'] = 'Ads:';
				foreach ( $ads as $ad ) {
					$options[ 'a_' . $ad->ID ] = $ad->post_title;

					if ( $ad->post_status !== 'publish' ) {
						$status                    = ( $ad->post_status === 'future' ) ? __( 'Scheduled', 'ad-commander' ) : ucfirst( $ad->post_status );
						$options[ 'a_' . $ad->ID ] = $ad->post_title . ' &ndash; ' . strtoupper( $status );
					}
				}
			}

			$current_meta_rows = $this->meta()->get_value( $this->current_meta(), 'placement_items' );

			if ( empty( $current_meta_rows ) ) {
				$current_rows = array(
					array(
						$this->meta()->select(
							'placement_items[]',
							$options,
							null,
							array(
								'display'    => false,
								'empty_text' => __( 'Select a group or an ad', 'ad-commander' ),
							)
						),
					),
				);
			} else {
				$current_rows = array();
				foreach ( $current_meta_rows as $current_meta_row ) {
					$current_rows[] = array(
						$this->meta()->select(
							'placement_items[]',
							$options,
							$current_meta_row,
							array(
								'display'    => false,
								'empty_text' => __( 'Select a group or an ad', 'ad-commander' ),
							)
						),
					);
				}
			}

			$this->info( __( 'Add groups and ads to this placement. These items will be inserted in the order below.', 'ad-commander' ) );
			$this->meta()->label( 'placement_items[]', __( 'Placement Items', 'ad-commander' ) );
			$this->meta()->repeater_table( array(), $current_rows );
			?>
		</div>
		<?php
	}

	/**
	 * HTML for targeting meta item.
	 */
	private function metaitem_content_targeting() {
		$info_message = __( 'Only display this placement if certain conditions are met.', 'ad-commander' );
		$pro_message  = ProBridge::instance()->is_pro_loaded() ? '' : ProBridge::instance()->pro_label();

		if ( $pro_message ) {
			$pro_message = '<strong>' . $pro_message . '</strong>';
		}

		$this->info( $info_message . $pro_message );
		$this->admin_targeting()->metaitem_targeting( $this->meta()->get_value( $this->current_meta(), 'content_conditions' ), true, 'content', AdCommander::posttype_placement() );
		$this->admin_targeting()->metaitem_targeting( $this->meta()->get_value( $this->current_meta(), 'visitor_conditions' ), true, 'visitor', AdCommander::posttype_placement() );
	}


	/**
	 * Save posted meta data. Interfaces with WOMeta.
	 *
	 * @param int    $post_id The Post ID that has saved.
	 * @param object $post  The WP_Post that has saved.
	 *
	 * @return void
	 */
	public function save_posted_metadata( $post_id, $post ) {
		$this->meta()->save_posted_metadata( $post, PlacementPostMeta::post_meta_keys(), $this->nonce, AdCommander::capability() );

		delete_transient( Placement::popups_should_enqueue_transient_name() );
	}


	/**
	 * Output content within an individual column on the Placement post page.
	 *
	 * @param string $column Current column.
	 * @param int    $post_id The post ID.
	 *
	 * @return void
	 */
	public function manage_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'position':
				$position = get_post_meta( $post_id, $this->meta()->make_key( 'placement_position' ), true );
				if ( $position ) {
					echo esc_html( PlacementPostMeta::placement_positions()[ $position ] );
				}
				break;

			case 'items':
				$items = maybe_unserialize( get_post_meta( $post_id, $this->meta()->make_key( 'placement_items' ), true ) );
				echo is_array( $items ) ? count( $items ) : 0;
				break;

			case 'placement_order':
				$order = get_post_meta( $post_id, $this->meta()->make_key( 'order' ), true );
				echo ( $order ) ? absint( $order ) : 1;
				break;
		}
	}

	/**
	 * Update columns on the placement post page.
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
				$new_columns['position']        = __( 'Position', 'ad-commander' );
				$new_columns['items']           = __( 'Items', 'ad-commander' );
				$new_columns['placement_order'] = __( 'Placement Order', 'ad-commander' );
			}
		}

		return $new_columns;
	}

	/**
	 * Update sortable columns on the placement page.
	 *
	 * @param array $columns The current columns.
	 *
	 * @return array
	 */
	public function manage_sortable_columns( $columns ) {
		$columns['position'] = 'position';
		return $columns;
	}

	/**
	 * Create an ad type filter on ad table.
	 *
	 * @param string $post_type The current post type.
	 *
	 * @return void
	 */
	public function placement_position_filter( $post_type ) {

		if ( AdCommander::posttype_placement() !== $post_type ) {
			return;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		$positions = PlacementPostMeta::placement_positions();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$selected = isset( $_GET['adcmdr_placement_position'] ) && $_GET['adcmdr_placement_position'] ? sanitize_text_field( $_GET['adcmdr_placement_position'] ) : '';

		if ( $positions ) {
			?>
				<select name="adcmdr_placement_position">
					<option value=""><?php echo esc_html__( 'All placement positions', 'ad-commander' ); ?></option>
					<?php foreach ( $positions as $key => $label ) : ?>
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
	public function placement_sort_pre_get_posts( $query ) {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		if ( ! is_admin() || $pagenow !== 'edit.php' || ! isset( $_GET['post_type'] ) || AdCommander::posttype_placement() !== sanitize_text_field( $_GET['post_type'] ) ) {
			return;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		/**
		 * Sorting by position while filtering by position has unexpected results.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$filtered_position = isset( $_GET['adcmdr_placement_position'] ) ? sanitize_text_field( $_GET['adcmdr_placement_position'] ) : '';
		$orderby           = $query->get( 'orderby' );

		if ( $filtered_position !== '' && $orderby === 'position' ) {
			return;
		}

		/**
		 * Order our query.
		 */
		if ( 'position' == $orderby ) {
			$position_key = $this->meta()->make_key( 'placement_position' );
			$meta_query   = array(
				'relation' => 'OR',
				array(
					'key'     => $position_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => $position_key,
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
	public function filter_placements_by_position( $query ) {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		if ( ! is_admin() || $pagenow !== 'edit.php' || ! isset( $_GET['post_type'] ) || AdCommander::posttype_placement() !== sanitize_text_field( $_GET['post_type'] ) ) {
			return;
		}

		if ( ! current_user_can( AdCommander::capability() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce not needed in this case
		$position = isset( $_GET['adcmdr_placement_position'] ) ? sanitize_text_field( $_GET['adcmdr_placement_position'] ) : '';

		if ( $position !== '' ) {
			$query->query_vars['meta_query'][] = array(
				'key'     => $this->meta()->make_key( 'placement_position' ),
				'value'   => $position,
				'compare' => '=',
			);
		}
	}
}
