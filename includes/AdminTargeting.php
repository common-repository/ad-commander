<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Admin targeting meta and related functionality for Ad posts and Group terms.
 */
class AdminTargeting extends Admin {
	/**
	 * The current instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/** Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		foreach ( $this->get_action_keys() as $key ) {
			$key_underscore = self::_key( $key );
			add_action( 'wp_ajax_' . $this->action_string( $key ), array( $this, 'action_' . $key_underscore ) );
		}
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
	 * Get necessary action keys, which will be used to create wp_ajax hooks.
	 *
	 * @return array
	 */
	private function get_action_keys() {
		return array(
			'load-conditions',
			'load-ac-results',
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
	 * Enqueue admin targeting scripts.
	 *
	 * @return string
	 */
	public function enqueue() {

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-core' );
		$repeater_handle = $this->meta()->repeater_enqueue();

		$handle = Util::ns( 'targeting' );

		wp_register_script(
			$handle,
			AdCommander::assets_url() . 'js/targeting.js',
			array(
				'jquery',
				'jquery-ui-core',
				$repeater_handle,
			),
			AdCommander::version(),
			array( 'in_footer' => true )
		);

		wp_enqueue_script( $handle );

		Util::enqueue_script_data(
			$handle,
			array(
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'actions'             => $this->get_ajax_actions(),
				'page_ac_placeholder' => self::page_autocomplete_placeholder_text(),
				'notfound'            => self::no_items_text(),
			)
		);

		return $handle;
	}

	/**
	 * Respond to an ajax request for loading conditions.
	 *
	 * @return void
	 */
	public function action_load_conditions() {
		$action = 'load-conditions';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( ! isset( $_REQUEST['target'] ) || ! isset( $_REQUEST['targeting_type'] ) ) {
			wp_die();
		}

		$target = isset( $_REQUEST['target'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['target'] ) ) : null;
		$type   = isset( $_REQUEST['targeting_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['targeting_type'] ) ) : null;

		$args = array(
			'action'     => $action,
			'conditions' => self::get_comparisons( $target ),
		);

		if ( $type === 'content' ) {
			if ( in_array( $target, array_keys( TargetingMeta::allowed_content_targets() ) ) ) {
				$args['value_type'] = TargetingContent::value_type( $target );
				$args['values']     = TargetingContent::values( $target );
				$args['args']       = TargetingContent::args( $target );
			}
		} elseif ( $type === 'visitor' ) {
			if ( in_array( $target, array_keys( TargetingMeta::allowed_visitor_targets() ) ) ) {
				$args['value_type'] = TargetingVisitor::value_type( $target );

				if ( is_array( $args['value_type'] ) ) {
					$args['values'] = array();
					foreach ( $args['value_type'] as $key => $value_type ) {
						$args['values'][ $key ] = TargetingVisitor::values( $target . '_' . $key );
					}

					$args['value_type_labels'] = TargetingVisitor::value_type_labels( $target );

				} else {
					$args['values'] = TargetingVisitor::values( $target );
				}

				$args['args'] = TargetingVisitor::args( $target );
			}
		}

		wp_send_json_success(
			$args
		);

		wp_die();
	}

	/**
	 * Respond to an ajax request for loading autocomplete results.
	 *
	 * @return void
	 */
	public function action_load_ac_results() {
		$action = 'load-ac-results';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( ! isset( $_REQUEST['target'] ) || ! isset( $_REQUEST['search_term'] ) ) {
			wp_die();
		}

		$search_term = isset( $_REQUEST['search_term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search_term'] ) ) : null;
		$target      = isset( $_REQUEST['target'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['target'] ) ) : null;

		if ( $search_term && $target ) {
			switch ( $target ) {
				case 'parent_page':
					$results = $this->get_pages_by_id_or_title( $search_term );
					break;

				case 'specific_post':
					$results = $this->get_all_posts_by_id_or_title( $search_term );
					break;
			}

			wp_send_json_success(
				array(
					'action'  => $action,
					'results' => $results,
				)
			);
		}

		wp_die();
	}

	/**
	 * Query pages that match the given search term (text or ID).
	 *
	 * @param string|int $search_term The search term from autocomplete.
	 *
	 * @return array
	 */
	private function get_pages_by_id_or_title( $search_term ) {
		return $this->get_posts_by_id_or_title( $search_term, 'page' );
	}

	/**
	 * Query any post type that matches the given search term (text or ID).
	 *
	 * @param string|int $search_term The search term from autocomplete.
	 *
	 * @return array
	 */
	private function get_all_posts_by_id_or_title( $search_term ) {
		return $this->get_posts_by_id_or_title(
			$search_term,
			array_filter(
				get_post_types( array( 'public' => true ) ),
				function ( $post_type ) {
					return ! in_array( $post_type, array( AdCommander::posttype_ad(), AdCommander::posttype_placement() ) );
				}
			)
		);
	}

	/**
	 * Query specified post type that matches the given search term (text or ID).
	 *
	 * @param string|int $search_term The search term from autocomplete.
	 * @param mixed      $post_types The post types to query.
	 *
	 * @return array
	 */
	private function get_posts_by_id_or_title( $search_term, $post_types ) {
		if ( is_numeric( $search_term ) ) {
			$results = Query::by_id( $search_term, Util::any_post_status(), $post_types );
		} else {
			$results = Query::by_title( $search_term, Util::any_post_status(), $post_types );
		}

		return $this->format_results_for_json( $results );
	}

	/**
	 * Format WP_Query results to send as json response.
	 *
	 * @param array  $results The results of a query.
	 * @param string $key The object key to use as the array result key.
	 * @param string $value The object key to use as the array result value.
	 *
	 * @return array
	 */
	private function format_results_for_json( $results, $key = 'ID', $value = 'post_title' ) {
		$formatted = array();
		if ( $results && ! empty( $results ) && ! is_wp_error( $results ) ) {

			$results = Util::arrayify( $results );

			foreach ( $results as $result ) {
				if ( ! isset( $result->$key ) || ! isset( $result->$value ) ) {
					continue;
				}

				$formatted[] = array(
					'id'    => $result->ID,
					'title' => $result->$value,
				);
			}
		}

		return $formatted;
	}

	/**
	 * Input placeholder text for autocompletes.
	 *
	 * @return string
	 */
	public static function page_autocomplete_placeholder_text() {
		return __( 'page id or title', 'ad-commander' );
	}

	/**
	 * Text when no checks are available.
	 *
	 * @return string
	 */
	public static function no_items_text() {
		return __( 'No items found', 'ad-commander' );
	}

	/**
	 * Returns the allowed conditions for a specific content target.
	 *
	 * @param string $target The current target.
	 *
	 * @return array
	 */
	public static function get_comparisons( $target ) {

		$conditions = array();

		foreach ( TargetingMeta::allowed_comparisons() as $key => $label ) {
			$conditions[ $key ] = array( $key => $label );
		}

		switch ( $target ) {
			case 'content_age':
				return array_merge( $conditions['older_than'], $conditions['newer_than'] );
				break;

			case 'pagination':
				return array_merge( $conditions['equals'], $conditions['less_than'], $conditions['greater_than'] );
				break;

			case 'geolocation_radius':
			case 'browser_width':
			case 'site_impressions':
				return array_merge( $conditions['less_than'], $conditions['greater_than'] );
				break;

			case 'max_impressions':
			case 'max_clicks':
				return $conditions['less_than'];
				break;

			case 'post_content':
			case 'url':
			case 'session_referrer_url':
			case 'browser_user_agent':
				return array_merge( $conditions['contains'], $conditions['does_not_contain'], $conditions['starts_with'], $conditions['does_not_start_with'], $conditions['ends_with'], $conditions['does_not_end_with'] );
				break;

			default:
				return array_merge( $conditions['is'], $conditions['is_not'] );
			break;
		}
	}

	/**
	 * Make the condition values for display in the admin.
	 *
	 * @param string $value_type The type of values to display.
	 * @param string $key The key for this field.
	 * @param mixed  $values The values for this field.
	 * @param mixed  $current_condition_values The currently selected values.
	 * @param array  $args The arguments for this field.
	 *
	 * @return string
	 */
	private function make_condition_values( $value_type, $key, $values = '', $current_condition_values = '', $args = array() ) {
		$condition_values = '';

		if ( $value_type === 'select' ) {
			$condition_values = $this->meta()->select( $key, $values, $current_condition_values, array_merge( $args, array( 'display' => false ) ) );
		} elseif ( $value_type === 'checkgroup' ) {
			$condition_values = $this->meta()->checkgroup( $key, $values, $current_condition_values, array_merge( $args, array( 'display' => false ) ) );
			if ( ! $condition_values ) {
				$condition_values = '<span class="woforms-notfound adcmdr-block-label">' . self::no_items_text() . '</span>';
			}
		} elseif ( $value_type === 'number' || $value_type === 'text' ) {
			$condition_values = $this->meta()->input( $key, $current_condition_values, $value_type, array_merge( $args, array( 'display' => false ) ) );
		} elseif ( $value_type === 'words' ) {
			$condition_values = '<span class="adcmdr-block-label">' . esc_html( $values ) . '</span>';
		} elseif ( $value_type === 'autocomplete' ) {
			$condition_values = $this->meta()->input(
				$key,
				'',
				'text',
				array_merge(
					$args,
					array(
						'classes'     => 'init-ac',
						'placeholder' => $this->page_autocomplete_placeholder_text(),
						'display'     => false,
					)
				)
			);

			if ( $current_condition_values && ! empty( $current_condition_values ) ) {
				if ( ! is_array( $current_condition_values ) ) {
					$current_condition_values = array( $current_condition_values );
				}

				$current_selected_autocomplete_ids = implode( ',', array_map( 'intval', $current_condition_values ) );

				$condition_values .= $this->meta()->input(
					str_replace( '[values]', '[selected_post_ids]', $key ),
					$current_selected_autocomplete_ids,
					'hidden',
					array(
						'display' => false,
					)
				);

				$condition_values .= '<ul class="selected_posts_list adcmdr-remove-controls">';
				foreach ( $current_condition_values as $current_condition_value ) {
					$condition_title   = get_the_title( $current_condition_value );
					$condition_values .= '<li><button class="adcmdr-remove-post adcmdr-remove" data-postid="' . absint( $current_condition_value ) . '"><span>' . esc_html( $condition_title ) . '</span><i class="dashicons dashicons-minus"></i></button></li>';
				}
				$condition_values .= '</ul>';
			}
		}

		return $condition_values;
	}

	/**
	 * Create the Targeting meta item.
	 *
	 * @param array  $current_meta_rows The current meta rows that were previously saved.
	 * @param bool   $pro_only If this is only for pro users.
	 * @param string $targeting The type of targeting fields.
	 *
	 * @return void
	 */
	public function metaitem_targeting( $current_meta_rows, $pro_only = false, $targeting = 'content', $context = 'adcmdr_advert' ) {
		$disabled   = false;
		$pro_bridge = ProBridge::instance();

		if ( $pro_only ) {
			$disabled = ! $pro_bridge->is_pro_loaded();
		}

		if ( $targeting === 'content' ) {
			$targets         = TargetingMeta::allowed_content_targets();
			$label_text      = __( 'Content Conditions', 'ad-commander' );
			$doc_target_type = 'content_targeting';
		} else {
			$targets         = TargetingMeta::allowed_visitor_targets();
			$label_text      = __( 'Visitor Conditions', 'ad-commander' );
			$doc_target_type = 'visitor_targeting';

			if ( $context === AdCommander::posttype_placement() ) {
				if ( isset( $targets['max_impressions'] ) ) {
					$targets['max_impressions'] = __( 'Max Placement Impressions', 'ad-commander' );
				}
				if ( isset( $targets['max_clicks'] ) ) {
					unset( $targets['max_clicks'] );
				}
			} elseif ( $context === AdCommander::tax_group() ) {
				if ( isset( $targets['max_impressions'] ) ) {
					unset( $targets['max_impressions'] );
				}
				if ( isset( $targets['max_clicks'] ) ) {
					unset( $targets['max_clicks'] );
				}
			}
		}

		$meta_key = $targeting . '_conditions';

		if ( empty( $current_meta_rows ) ) {

			if ( $disabled ) {
				$disabled_targets = array();
				foreach ( $targets as $key => $value ) {
					$disabled_targets[ 'disabled:' . $key ] = $value . ProBridge::pro_label();
				}

				$targets = $disabled_targets;
			} elseif ( $targeting === 'visitor' ) {
				if ( ! $pro_bridge->is_pro_loaded() ) {
					$disabled_targets = array();
					foreach ( $targets as $key => $value ) {
						if ( in_array( $key, $pro_bridge->pro_visitor_conditions(), true ) ) {
							$key    = 'disabled:' . $key;
							$value .= ProBridge::pro_label();
						}

						$disabled_targets[ $key ] = $value;
					}

					$targets = $disabled_targets;
				}
			}

			$current_rows = array(
				array(
					$this->meta()->select(
						"{$meta_key}[0][target]",
						$targets,
						null,
						array(
							'display'    => false,
							'empty_text' => __( 'Select a target', 'ad-commander' ),
							'classes'    => 'targeting-target',
						)
					),
					'',
					$this->meta()->select(
						"{$meta_key}[0][andor]",
						TargetingMeta::allowed_andor(),
						'or',
						array(
							'display'  => false,
							'classes'  => 'targeting-andor',
							'disabled' => $disabled,
						)
					),
				),
			);
		} else {
			$current_rows = array();
			$i            = 0;
			foreach ( $current_meta_rows as $current_meta_row ) {
				$current_target   = isset( $current_meta_row['target'] ) ? $current_meta_row['target'] : null;
				$modified_targets = $targets;

				if ( $disabled ) {
					/**
					 * Disable all targets except the current one.
					 */
					$disabled_targets = array();
					foreach ( $modified_targets as $key => $value ) {
						if ( $key === $current_target ) {
							$disabled_targets[ $current_target ] = $value;
						} else {
							$disabled_targets[ 'disabled:' . $key ] = $value;
						}
					}

					$modified_targets = $disabled_targets;
				}

				$conditions        = self::get_comparisons( $current_target );
				$current_condition = isset( $current_meta_row['condition'] ) ? $current_meta_row['condition'] : '';

				if ( $targeting === 'content' ) {
					$values     = TargetingContent::values( $current_target );
					$value_type = TargetingContent::value_type( $current_target );
				} else {
					$values     = TargetingVisitor::values( $current_target );
					$value_type = TargetingVisitor::value_type( $current_target );
				}

				$current_condition_values = isset( $current_meta_row['values'] ) ? $current_meta_row['values'] : null;
				$condition_values         = '';

				if ( $value_type === 'autocomplete' ) {
					$current_condition_values = isset( $current_meta_row['selected_post_ids'] ) ? $current_meta_row['selected_post_ids'] : array();
				}

				$meta_value_key = "{$meta_key}[{$i}][values]";
				if ( is_array( $value_type ) ) {
					if ( $targeting === 'content' ) {
						$value_type_labels = TargetingContent::value_type_labels( $current_target );
					} else {
						$value_type_labels = TargetingVisitor::value_type_labels( $current_target );
					}

					foreach ( $value_type as $value_type_key => $value_type_value ) {
						$this_meta_value_key = $meta_value_key . '[' . $value_type_key . ']';
						$this_value          = isset( $current_condition_values[ $value_type_key ] ) ? $current_condition_values[ $value_type_key ] : '';

						if ( $targeting === 'content' ) {
							$values     = TargetingContent::values( $current_target . '_' . $value_type_key );
							$extra_args = TargetingContent::args( $current_target );
						} else {
							$values     = TargetingVisitor::values( $current_target . '_' . $value_type_key );
							$extra_args = TargetingVisitor::args( $current_target );
						}

						if ( ! empty( $value_type_labels ) && isset( $value_type_labels[ $value_type_key ] ) ) {
							$condition_values .= $this->make_condition_values( 'words', '', $value_type_labels[ $value_type_key ] );
						}

						$args = array();

						if ( ! empty( $extra_args ) ) {
							if ( isset( $extra_args[ $value_type_key ] ) ) {
								$args = $extra_args[ $value_type_key ];
							}
						}

						$condition_values .= $this->make_condition_values( $value_type_value, $this_meta_value_key, $values, $this_value, $args );
					}
				} else {
					$condition_values .= $this->make_condition_values( $value_type, $meta_value_key, $values, $current_condition_values );
				}

				$current_andor = isset( $current_meta_row['andor'] ) ? $current_meta_row['andor'] : 'or';

				$current_rows[] = array(
					$this->meta()->select(
						"{$meta_key}[{$i}][target]",
						$modified_targets,
						$current_target,
						array(
							'display'    => false,
							'empty_text' => __( 'Select a target', 'ad-commander' ),
							'classes'    => 'targeting-target',
						)
					),
					'<div class="adcmdr-targeting-conditions">' . $this->meta()->select(
						"{$meta_key}[{$i}][condition]",
						$conditions,
						$current_condition,
						array(
							'display' => false,
							'classes' => 'targeting-conditions',
						)
					) . $condition_values . '</div>',
					$this->meta()->select(
						"{$meta_key}[{$i}][andor]",
						TargetingMeta::allowed_andor(),
						$current_andor,
						array(
							'display' => false,
							'classes' => 'targeting-andor',
						)
					),
				);

				++$i;
			}
		}
		?>
		<div class="<?php echo esc_attr( Admin::metaitem_classes( $targeting . '-targeting' ) ); ?>" data-targetingtype="<?php echo esc_attr( $targeting ); ?>">
			<div class="adcmdr-table-intro">
				<?php
				$this->meta()->label( "{$meta_key}[]", $label_text, array( 'classes' => 'inline' ) );
				Doc::doc_link( $doc_target_type );
				if ( $doc_target_type === 'visitor_targeting' ) {
					echo '<a href="' . esc_url( Admin::latlng_url() ) . '" target="_blank" class="adcmdr-x-link">' . esc_html__( 'Lat/Lng Lookup Tool', 'ad-commander' ) . '<i class="dashicons dashicons-external"></i></a>';
				}
				?>
			</div>
			<?php
				$this->meta()->repeater_table(
					array( 'Target', 'Condition', '' ),
					$current_rows,
					array(
						'classes'        => 'wp-list-table widefat fixed adcmdr-targeting',
						'use_array_keys' => true,
					)
				);
			?>
		</div>
		<?php
	}
}
