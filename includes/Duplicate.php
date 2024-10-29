<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Converts a WP_Post instance into a usable Ad.
 */
class Duplicate extends Admin {

	/**
	 * An instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'admin_action_adcmdr-duplicate', array( $this, 'maybe_duplicate' ) );

		add_filter( 'post_row_actions', array( $this, 'duplicate_post_link' ), 10, 2 );
		add_filter( AdCommander::tax_group() . '_row_actions', array( $this, 'duplicate_term_link' ), 10, 2 );

		add_filter( 'adcmdr_duplicate_post_donotcopy_meta_keys', array( $this, 'maybe_donotcopy_placement_items' ), 1, 2 );
	}

	/**
	 * Get an instance of WOMeta.
	 *
	 * @return WOMeta
	 */
	private function wo_meta() {
		if ( ! $this->wo_meta ) {
			$this->wo_meta = new WOMeta( AdCommander::ns() );
		}

		return $this->wo_meta;
	}

	/**
	 * Create 'duplicate' link on ads and placements.
	 *
	 * @param array   $actions The existing actions.
	 * @param WP_Post $post The current post.
	 *
	 * @return array
	 */
	public function duplicate_post_link( $actions, $post ) {

		if ( $post->post_type === AdCommander::posttype_ad() || $post->post_type === AdCommander::posttype_placement() ) {
			$actions['adcmdr_duplicate'] = '<a href="' . esc_url( self::get_duplicate_url( $post->ID, $post->post_type ) ) . '">' . esc_html__( 'Duplicate', 'ad-commander' ) . '</a>';

		}

		return $actions;
	}

	/**
	 * Create 'duplicate' link on groups.
	 *
	 * @param array   $actions The existing actions.
	 * @param WP_Term $term The current term.
	 *
	 * @return array
	 */
	public function duplicate_term_link( $actions, $term ) {

		$actions['adcmdr_duplicate'] = '<a href="' . esc_url( self::get_duplicate_url( $term->term_id, AdCommander::tax_group() ) ) . '">' . esc_html__( 'Duplicate', 'ad-commander' ) . '</a>';

		return $actions;
	}

	/**
	 * Gets the URL for the duplicate action.
	 *
	 * @param int    $id Post or term ID.
	 * @param string $duplicate_type Post type or taxonomy name.
	 *
	 * @return string
	 */
	protected static function get_duplicate_url( $id, $duplicate_type ) {
		$url = admin_url( 'admin.php' );

		$url = add_query_arg(
			array(
				'id'             => absint( $id ),
				'duplicate_type' => sanitize_key( $duplicate_type ),
				'action'         => Util::ns( 'duplicate' ),
			),
			$url
		);

		return sanitize_url( wp_nonce_url( $url, Util::ns( 'duplicate' ) ) );
	}

	/**
	 * Duplicate maybe.
	 *
	 * @return void|false
	 */
	public function maybe_duplicate() {

		if ( ! isset( $_GET['action'] ) ||
			! isset( $_GET['duplicate_type'] ) ||
			! isset( $_GET['id'] ) ||
			isset( $_GET['adcmdr_duplicated'] ) ||
			! check_admin_referer( Util::ns( 'duplicate' ) ) ||
			! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( sanitize_text_field( wp_unslash( $_GET['action'] ) ) !== Util::ns( 'duplicate' ) ) {
			wp_die();
		}

		$duplicate_type = isset( $_REQUEST['duplicate_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['duplicate_type'] ) ) : false;
		$id             = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : false;

		if ( ! $duplicate_type || ! $id || $id <= 0 ) {
			return false;
		}

			$cloned = false;

		switch ( $duplicate_type ) {
			case AdCommander::posttype_ad():
				$cloned   = $this->duplicate_ad( $id );
				$redirect = Admin::admin_ad_post_type_url();
				break;

			case AdCommander::tax_group():
				$cloned   = $this->duplicate_group( $id );
				$redirect = Admin::admin_group_tax_url();
				break;

			case AdCommander::posttype_placement():
				$cloned   = $this->duplicate_placement( $id );
				$redirect = Admin::admin_placement_post_type_url();
				break;

			default:
				return false;
			break;
		}

			$redirect = add_query_arg(
				array( 'adcmdr_duplicated' => ( $cloned !== false ) ? 'success' : 'fail' ),
				$redirect
			);

			wp_safe_redirect( sanitize_url( $redirect ) );
			exit;
	}

	/**
	 * Duplicate a post
	 *
	 * @param WP_Post $old_post The post to duplicate.
	 *
	 * @return int|bool
	 */
	private function duplicate_post( $old_post ) {
		$new_post_params = array(
			'post_status' => apply_filters( 'adcmdr_duplicate_post_new_post_status', 'draft', $old_post ),
		);

		$do_not_copy = apply_filters(
			'adcmdr_duplicate_post_donotcopy_keys',
			array(
				'id',
				'ID',
				'post_date',
				'post_date_gmt',
				'post_modified',
				'post_modified_gmt',
			),
			$old_post
		);

		$title_suffix = apply_filters( 'adcmdr_duplicate_post_title_suffix', __( '(copy)', 'ad-commander' ), $old_post );

		foreach ( get_object_vars( $old_post ) as $key => $value ) {
			if ( isset( $new_post_params[ $key ] ) || in_array( $key, $do_not_copy ) ) {
				continue;
			}

			if ( $key === 'post_title' && $title_suffix ) {
				$value .= ' ' . esc_html( $title_suffix );
			}

			$new_post_params[ $key ] = $value;
		}

		$new_post_id = wp_insert_post( apply_filters( 'adcmdr_duplicate_post_new_post', $new_post_params ) );

		if ( ! $new_post_id || is_wp_error( $new_post_id ) ) {
			return false;
		}

		return $new_post_id;
	}

	/**
	 * Duplicate post meta.
	 *
	 * @param WP_Post $old_post The post to duplicate.
	 * @param int     $new_post_id The ID of the new post.
	 *
	 * @return void
	 */
	private function duplicate_post_meta( $old_post, $new_post_id ) {
		$do_not_copy_meta = apply_filters( 'adcmdr_duplicate_post_donotcopy_meta_keys', array(), $old_post, $new_post_id );

		$meta_keys = get_post_custom_keys( $old_post->ID );

		if ( $meta_keys && ! empty( $meta_keys ) ) {
			foreach ( $meta_keys as $meta_key ) {
				delete_post_meta( $new_post_id, $meta_key );

				if ( in_array( $meta_key, $do_not_copy_meta, true ) ) {
					continue;
				}

				$meta_values = get_post_custom_values( $meta_key, $old_post->ID );

				foreach ( $meta_values as $meta_value ) {
					$meta_value = maybe_unserialize( $meta_value );
					$meta_value = apply_filters( 'adcmdr_duplicate_post_meta_value', $meta_value, $meta_key, $old_post, $new_post_id );

					/**
					 * Using wp_slash on value here, but check in the future if this causes problems with some other plugins/themes
					 */
					add_post_meta( $new_post_id, $meta_key, wp_slash( $meta_value ) );
				}
			}
		}
	}

	/**
	 * Duplicate groups from old post to new.
	 *
	 * @param WP_Post $old_post The original post.
	 * @param int     $new_post_id The new post ID.
	 *
	 * @return void
	 */
	private function duplicate_ad_groups( $old_post, $new_post_id ) {
		$groups = wp_get_object_terms( $old_post->ID, AdCommander::tax_group() );

		if ( $groups && ! empty( $groups ) ) {
			$group_ids = array();
			foreach ( $groups as $group ) {
				$group_ids[] = $group->term_id;
			}

			wp_set_object_terms( $new_post_id, $group_ids, AdCommander::tax_group() );
		}
	}

	/**
	 * Duplicate an ad.
	 *
	 * @param int $ad_id The post ID for the ad.
	 *
	 * @return int|bool
	 */
	private function duplicate_ad( $ad_id ) {
		$ad = Query::ad( $ad_id, Util::any_post_status() );

		if ( ! $ad || is_wp_error( $ad ) ) {
			return false;
		}

		$new_ad_id = $this->duplicate_post( $ad );

		if ( ! $new_ad_id ) {
			return false;
		}

		$this->duplicate_post_meta( $ad, $new_ad_id );

		if ( apply_filters( 'adcmdr_duplicate_ad_copy_groups', true, $ad, $new_ad_id ) ) {
			$this->duplicate_ad_groups( $ad, $new_ad_id );
		}

		return $new_ad_id;
	}

	/**
	 * Duplicate a placement.
	 *
	 * @param int $placement_id The post ID for the placement.
	 *
	 * @return int|bool
	 */
	private function duplicate_placement( $placement_id ) {
		$placement = Query::placement( $placement_id, Util::any_post_status() );

		if ( ! $placement || is_wp_error( $placement ) ) {
			return false;
		}

		$new_placement_id = $this->duplicate_post( $placement );

		if ( ! $new_placement_id ) {
			return false;
		}

		$this->duplicate_post_meta( $placement, $new_placement_id );

		return $new_placement_id;
	}

	/**
	 * Possibly skip duplicating placement items for new placement.
	 *
	 * @param array   $ignore_meta_keys The meta keys to skip.
	 * @param WP_Post $old_post The old post we are copying from.
	 *
	 * @return array
	 */
	public function maybe_donotcopy_placement_items( $ignore_meta_keys, $old_post ) {
		if ( $old_post->post_type === AdCommander::posttype_placement() && apply_filters( 'adcmdr_duplicate_placement_items', true ) === false ) {
			$key = $this->wo_meta()->make_key( 'placement_items' );

			if ( ! in_array( $key, $ignore_meta_keys ) ) {
				$ignore_meta_keys[] = $key;
			}
		}

		return $ignore_meta_keys;
	}

	/**
	 * Duplicate group.
	 *
	 * @param int $old_term_id The term being duplicated.
	 *
	 * @return int|bool
	 */
	private function duplicate_group( $old_term_id ) {

		$old_term = Query::group( $old_term_id );

		if ( ! $old_term ) {
			return false;
		}

		$new_group_args = array(
			'description' => $old_term->description,
			'parent'      => $old_term->parent,
		);

		$term_title = $old_term->name . ' - ' . apply_filters( 'adcmdr_duplicate_group_title_suffix', __( '(copy)', 'ad-commander' ), $old_term );

		while ( term_exists( $term_title, AdCommander::tax_group() ) ) {
			$term_title .= ' ' . time();
		}

		$new_term = wp_insert_term( $term_title, AdCommander::tax_group(), apply_filters( 'adcmdr_duplicate_group_new_term_args', $new_group_args, $old_term, $term_title ) );

		if ( ! $new_term || is_wp_error( $new_term ) ) {
			return false;
		}

		/**
		 * Now copy meta and ads
		 */
		$copy_ads         = apply_filters( 'adcmdr_duplicate_group_copy_ads', true );
		$do_not_copy_meta = apply_filters( 'adcmdr_duplicate_group_donotcopy_meta_keys', array(), $old_term, $new_term );

		if ( ! $copy_ads ) {
			if ( ! in_array( 'ad_order', $do_not_copy_meta ) ) {
				$do_not_copy_meta[] = 'ad_order';
			}
			if ( ! in_array( 'ad_weights', $do_not_copy_meta ) ) {
				$do_not_copy_meta[] = 'ad_weights';
			}
		}

		/**
		 * Duplicate ads
		 */
		if ( $copy_ads ) {
			$ads = Query::ads_by_group( $old_term->term_id, Util::any_post_status() );
			if ( ! empty( $ads ) ) {
				foreach ( $ads as $ad ) {
					wp_add_object_terms( $ad->ID, $new_term['term_id'], AdCommander::tax_group() );
				}

				delete_transient( GroupTermMeta::group_count_transient( $new_term['term_id'] ) );
			}
		}

		$term_meta = get_term_meta( $old_term->term_id );

		if ( ! $term_meta || is_wp_error( $term_meta ) || empty( $term_meta ) ) {
			return $new_term['term_id'];
		}

		foreach ( array_keys( $term_meta ) as $meta_key ) {
			delete_term_meta( $new_term['term_id'], $meta_key );

			if ( in_array( $meta_key, $do_not_copy_meta ) ) {
				continue;
			}

			$meta_value = get_term_meta( $old_term->term_id, $meta_key, true );
			$meta_value = maybe_unserialize( $meta_value );
			$meta_value = apply_filters( 'adcmdr_duplicate_group_meta_value', $meta_value, $meta_key, $old_term, $new_term['term_id'] );

			add_term_meta( $new_term['term_id'], $meta_key, wp_slash( $meta_value ) );
		}

		return $new_term['term_id'];
	}
}
