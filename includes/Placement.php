<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Converts WP_Post instances into a usable Placements.
 */
class Placement {

	/**
	 * Processed placements.
	 *
	 * @var array
	 */
	private $processed_placements;


	/**
	 * The current instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	protected $wo_meta;

	/**
	 * Does this placement need consent?
	 *
	 * @var bool
	 */
	private $global_needs_consent = null;

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		$priority = absint( Options::instance()->get( 'filter_priority', 'general', false, self::placement_priority_default() ) );

		add_filter( 'the_content', array( $this, 'the_content' ), apply_filters( 'adcmdr_priority_the_content', $priority ) );

		add_action( 'wp_head', array( $this, 'wp_head' ), apply_filters( 'adcmdr_priority_wp_head', $priority ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ), apply_filters( 'adcmdr_priority_wp_footer', $priority ) );
	}

	/**
	 * The default priority for the placement the_content filter.
	 *
	 * @return int
	 */
	public static function placement_priority_default() {
		return 100;
	}

	/**
	 * Create a new WOMeta instance if necessary.
	 *
	 * @return WOMeta
	 */
	protected function meta() {
		if ( ! $this->wo_meta ) {
			$this->wo_meta = new WOMeta( AdCommander::ns() );
		}

		return $this->wo_meta;
	}


	/**
	 * Do we need consent to display?
	 *
	 * @return bool
	 */
	private function global_needs_consent() {
		if ( $this->global_needs_consent === null ) {
			$global_needs_consent       = Consent::instance()->global_needs_consent();
			$this->global_needs_consent = $global_needs_consent;
		}

		return $this->global_needs_consent;
	}

	/**
	 * Determine if we should use ajax loading for this placement.
	 *
	 * @param array $meta The current meta.
	 *
	 * @return bool
	 */
	public function should_ajax( $meta ) {
		if ( ! ProBridge::instance()->is_pro_loaded() || Amp::instance()->is_amp() ) {
			return false;
		}

		$render = Util::render_method();

		if ( $render === 'serverside' ) {
			return false;
		}

		if ( $render === 'clientside' ) {
			return true;
		}

		/**
		 * 'Smart' render method.
		 */

		/**
		 * Use consent
		 * Always use client-side if there's a consent cookie, because page caching would cause false positives.
		 */
		if ( Consent::instance()->requires_consent() ) {
			return true;
		}

		$visitor_conditions = $this->meta()->get_value( $meta, 'visitor_conditions', false );
		return ( ( $visitor_conditions && ! empty( $visitor_conditions ) ) );
	}

	/**
	 * Build HTML for placements. This is used when a placement is loaded over ajax.
	 *
	 * @param array $placement_ids The placement IDs to load.
	 * @param array $args Function arguments.

	 * @return string
	 */
	public function display_placements( $placement_ids = array(), $args = array() ) {
		$force_nocheck = ( isset( $args['force_nocheck'] ) ) ? $args['force_nocheck'] : false;

		$placements = Query::placements( 'publish', array(), Util::arrayify( $placement_ids ) );
		$html       = '';

		if ( ! empty( $placements ) ) {
			$placements = $this->process_placements( $placements, true );

			foreach ( $placements as $position => $placement ) {
				$disable_wrappers = false;
				$is_popup         = false;

				if ( $position === 'head_close_tag' ) {
					$disable_wrappers = true;
				} elseif ( $position === 'popup' ) {
					$is_popup = true;
				}

				$html .= $this->build_placement_ads(
					$placement,
					array(
						'disable_wrappers' => $disable_wrappers,
						'force_noajax'     => true,
						'force_nocheck'    => $force_nocheck,
						'is_popup'         => $is_popup,
					)
				);
			}
		}

		return $html;
	}

	/**
	 * Process items in this placement.
	 *
	 * @param array $items The ads and groups to process into an array.
	 *
	 * @return array
	 */
	private function process_items( $items ) {
		$processed_items = array();

		if ( ! empty( $items ) ) {

			foreach ( $items as $item ) {
				$prefix = substr( $item, 0, 2 );
				$type   = null;

				if ( $prefix === 'a_' ) {
					$type = 'ad';
				} elseif ( $prefix === 'g_' ) {
					$type = 'group';
				}

				if ( $type !== null ) {
					$processed_items[] = array(
						'type' => $type,
						'id'   => absint( substr( $item, 2 ) ),
					);
				}
			}
		}

		return $processed_items;
	}

	/**
	 * Process Placement WP_Posts into usable array.
	 *
	 * @param \WP_Post $placement_posts Placement posts to procss.
	 *
	 * @return array
	 */
	private function process_placements( $placement_posts, $force_noajax = false ) {
		$placements = array();

		foreach ( $placement_posts as $placement_post ) {
			$meta             = $this->meta()->get_post_meta( $placement_post->ID, PlacementPostMeta::post_meta_keys() );
			$position         = $this->meta()->get_value( $meta, 'placement_position', false );
			$disable_wrappers = false;

			if ( ! $position ) {
				continue;
			}

			if ( ! isset( $placements[ $position ] ) ) {
				$placements[ $position ] = array();
			}

			if ( $position === 'body_close_tag' ) {
				if ( ! $force_noajax ) {
					$force_noajax = Util::truthy( $this->meta()->get_value( $meta, 'force_serverside_body', 1 ) );
				}

				$disable_wrappers = Util::truthy( $this->meta()->get_value( $meta, 'disable_wrappers_body', 1 ) );
			}

			$should_ajax = ( ! $force_noajax ) ? $this->should_ajax( $meta ) : false;
			$order       = $this->meta()->get_value( $meta, 'order', false );
			$items       = $this->process_items( $this->meta()->get_value( $meta, 'placement_items', false ) );

			if ( ! empty( $items ) ) {
				$placement = array(
					'placement_id'     => $placement_post->ID,
					'placement_name'   => $placement_post->post_title,
					'order'            => $order ? intval( $order ) : PlacementPostMeta::post_meta_keys()['order']['default'],
					'items'            => $items,
					'meta'             => $meta,
					'should_ajax'      => $should_ajax,
					'disable_wrappers' => $disable_wrappers,
					'force_nocheck'    => $this->meta()->get_value( $meta, 'disable_consent', false ),
				);

				if ( ProBridge::instance()->is_pro_loaded() ) {
					if ( $position === 'post_list' ) {
						$placement = apply_filters( 'adcmdr_pro_post_list_create_placement', $placement, $position, array( 'post_list_position' => $this->meta()->get_value( $meta, 'post_list_position', 1 ) ) );
					}
				}

				$placements[ $position ][] = $placement;
			}
		}

		return $this->sort_placements( $placements );
	}

	/**
	 * Sort placements by manually set order.
	 *
	 * @param array $placements A processed placements array.
	 *
	 * @return array
	 */
	public function sort_placements( $placements ) {
		$sorted_placements = array();

		foreach ( $placements as $position => $placement_position ) {
			usort(
				$placement_position,
				function ( $a, $b ) {
					return $a['order'] <=> $b['order'];
				}
			);

			$sorted_placements[ $position ] = $placement_position;
		}

		return $sorted_placements;
	}

	/**
	 * Get array of placements.
	 *
	 * @return array
	 */
	public function get_placements() {
		if ( ! $this->processed_placements ) {
			$this->processed_placements = $this->process_placements( Query::placements() );
		}

		return $this->processed_placements;
	}

	/**
	 * Filter placements by a given post type.
	 *
	 * @param array  $placements An array of processed placements.
	 * @param string $post_type The post type to filter.
	 *
	 * @return array
	 */
	protected function filter_placements_by_post_type( $placements, $post_type ) {
		return array_filter(
			$placements,
			function ( $placement ) use ( $post_type ) {
				return in_array( $post_type, $placement['post_types'] );
			}
		);
	}

	/**
	 * Determine if a placement needs consent.
	 *
	 * @param int $placement_id The placement ID.
	 *
	 * @return bool
	 */
	private function placement_needs_consent( $placement_id, $force_nocheck = false ) {
		if ( $force_nocheck ) {
			return false;
		}

		/**
		 * Need to check the meta of $placement_id as well as global. This way we can disable consent for a placement.
		 *
		 * TODO: We need some way to store meta so that we aren't re-querying it.
		 */
		$meta = $this->meta()->get_post_meta( $placement_id, PlacementPostMeta::post_meta_keys() );

		return ! $this->meta()->get_value( $meta, 'disable_consent', false ) && $this->global_needs_consent();
	}

	/**
	 * Create an ajax container for this placement.
	 *
	 * @param int $placement_id The ID of the placement.
	 *
	 * @return string
	 */
	public function placement_ajax_container( $placement_id ) {
		/**
		 * Filter: adcmdr_placement_ajax_container_start
		 * Filter: adcmdr_placement_ajax_container_end
		 * Filter: adcmdr_placement_ajax_container
		 *
		 * Filters AJAX container.
		 */
		$classes = Util::prefixed( 't' );

		if ( $this->placement_needs_consent( $placement_id ) ) {
			$classes .= ' ' . Util::prefixed( 'needs-consent' );
		}

		$html  = apply_filters( 'adcmdr_placement_ajax_container_start', '<div class="' . esc_attr( $classes ) . '" data-gid="p' . esc_attr( $placement_id ) . '">', $placement_id );
		$html .= apply_filters( 'adcmdr_placement_ajax_container_end', '</div>', $placement_id );

		return apply_filters( 'adcmdr_placement_ajax_container', $html, $placement_id );
	}

	/**
	 * Append a script with placement IDs to each placement.
	 *
	 * @param int $placement_id The placement ID.
	 *
	 * @return string
	 */
	protected function track_placement_script( $placement_id ) {
		$placement_id = absint( $placement_id );
		$var          = Util::prefixed( 'plids', '_' );

		return '<script type="text/javascript">window.' . $var . ' = window.' . $var . ' || []; window.' . $var . '.push(' . $placement_id . ');</script>';
	}

	/**
	 * Build ads and groups for display.
	 *
	 * @param array $content_placements An array of placements with items to display.
	 * @param array $args An array of function arguments.
	 *
	 * @return string
	 */
	protected function build_placement_ads( $content_placements, $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'disable_wrappers' => false,
				'force_noajax'     => false,
				'force_nocheck'    => false,
				'is_popup'         => false,
			),
		);

		$is_amp           = Amp::instance()->is_amp();
		$disable_wrappers = $args['disable_wrappers'];
		$force_noajax     = $args['force_noajax'];
		$force_nocheck    = $args['force_nocheck'];

		$ads_html = '';

		if ( ! empty( $content_placements ) ) {
			foreach ( $content_placements as $placement ) {

				$this_placement_html = '';

				/**
				 * No need to build popup ads if this is an AMP visit.
				 */
				if ( $args['is_popup'] === true && $is_amp ) {
					continue;
				}

				if ( $force_nocheck === false ) {
					$force_nocheck = $placement['force_nocheck'];
				}

				if ( $disable_wrappers === false ) {
					$disable_wrappers = Util::truthy( $placement['disable_wrappers'] );
				}

				if ( $force_noajax === false ) {
					$force_noajax = ! Util::truthy( $placement['should_ajax'] );
				}

				if ( $placement['should_ajax'] && ! $force_noajax ) {
					$this_placement_html = $this->placement_ajax_container( $placement['placement_id'] );
				} elseif ( ! empty( $placement['items'] ) ) {

					if ( ProBridge::instance()->is_pro_loaded() ) {
						if ( ! apply_filters( 'adcmdr_pro_placement_passes_content_targeting', true, $this->meta()->get_value( $placement['meta'], 'content_conditions', false ), $placement['placement_id'] ) ||
							! apply_filters( 'adcmdr_pro_placement_passes_visitor_targeting', true, $this->meta()->get_value( $placement['meta'], 'visitor_conditions', false ), $placement['placement_id'] ) ) {
							continue;
						}
					}

					foreach ( $placement['items'] as $item ) {
						if ( $item['type'] === 'group' ) {
							$this_placement_html .= adcmdr_display_group(
								$item['id'],
								array(
									'display'          => false,
									'force_noajax'     => $force_noajax,
									'force_nocheck'    => $force_nocheck,
									'disable_wrappers' => $disable_wrappers,
								)
							);
						} else {
							$this_placement_html .= adcmdr_display_ad(
								$item['id'],
								array(
									'display'          => false,
									'force_noajax'     => $force_noajax,
									'force_nocheck'    => $force_nocheck,
									'disable_wrappers' => $disable_wrappers,
								)
							);
						}
					}

					if ( $args['is_popup'] === true && $this_placement_html !== '' ) {
						if ( ProBridge::instance()->is_pro_loaded() ) {
							$this_placement_html = Popup::instance()->build_popup( $placement['placement_id'], $this_placement_html, $this->placement_needs_consent( $placement['placement_id'], $force_nocheck ), $is_amp );
						}
					}

					if ( $args['is_popup'] !== true && $this_placement_html !== '' ) {
						$this_placement_html .= $this->track_placement_script( $placement['placement_id'] );
					}
				}

				$ads_html .= $this_placement_html;
			}
		}

		return $ads_html;
	}

	/**
	 * Determine if the_content will run more than once.
	 *
	 * @return bool
	 */
	private function the_content_once() {
		global $wp_current_filter;

		if ( count( array_keys( $wp_current_filter, 'the_content', true ) ) <= 1 ) {
			return true;
		}

		return false;
	}

	/**
	 * Filter for the_content.
	 * Determine if placements should be injected in the current post.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function the_content( $content ) {
		global $post;

		if ( is_admin() || ! is_main_query() || doing_filter( 'get_the_excerpt' ) || ! $this->the_content_once() ) {
			return $content;
		}

		$placements = $this->get_placements();
		if ( ! empty( $placements ) ) {

			if ( ProBridge::instance()->is_pro_loaded() ) {
				$content = apply_filters( 'adcmdr_pro_the_content', $content, $placements, $post );
			}

			if ( isset( $placements['before_content'] ) && ! empty( $placements['before_content'] ) ) {
				$content = $this->build_placement_ads( $placements['before_content'] ) . $content;
			}

			if ( isset( $placements['after_content'] ) && ! empty( $placements['after_content'] ) ) {
				$content = $content . $this->build_placement_ads( $placements['after_content'] );
			}
		}

		return $content;
	}

	/**
	 * Insert ads into wp_head.
	 *
	 * @return void
	 */
	public function wp_head() {
		$placements = $this->get_placements();
		if ( ! empty( $placements ) && isset( $placements['head_close_tag'] ) ) {
			$ads = $this->build_placement_ads(
				$placements['head_close_tag'],
				array(
					'disable_wrappers' => true,
					'force_noajax'     => true,
				)
			);

			if ( $ads ) {
				Output::process( $ads );
			}
		}
	}

	/**
	 * Insert ads into wp_footer.
	 *
	 * @return void
	 */
	public function wp_footer() {
		$placements = $this->get_placements();
		if ( ! empty( $placements ) && ( isset( $placements['popup'] ) || isset( $placements['body_close_tag'] ) ) ) {

			$ads = '';

			if ( isset( $placements['popup'] ) && ProBridge::instance()->is_pro_loaded() ) {
				$ads .= $this->build_placement_ads( $placements['popup'], array( 'is_popup' => true ) );
			}

			if ( isset( $placements['body_close_tag'] ) ) {
				$ads .= $this->build_placement_ads( $placements['body_close_tag'] );
			}

			if ( $ads ) {
				Output::process( $ads );
			}
		}
	}

	/**
	 * Transient that determines if popup script should be enqueued.
	 *
	 * @return string
	 */
	public static function popups_should_enqueue_transient_name() {
		return Util::ns( 'popups_should_enqueue' );
	}
}
