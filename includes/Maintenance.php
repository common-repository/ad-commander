<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Various maintenance tasks that run during post/term deletion, plugin deactivation, etc.
 */
class Maintenance {
	/**
	 * The current instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'before_delete_post', array( $this, 'check_ad_deleted' ), 10, 2 );
		add_action( 'pre_delete_term', array( $this, 'check_group_deleted' ), 10, 2 );
	}

	/**
	 * Create a new WOMeta instance if necessary.
	 *
	 * @return WOMeta
	 */
	private function meta() {
		if ( ! $this->wo_meta ) {
			$this->wo_meta = new WOMeta( AdCommander::ns() );
		}

		return $this->wo_meta;
	}

	/**
	 * When a group or ad is deleted, remove it from any placements that it may have been part of.
	 *
	 * @param string $object_id The object identifier (a_# or g_#) as stored in the Placement meta.
	 *
	 * @return void
	 */
	private function remove_item_from_placements( $object_id ) {

		$placements = Query::placements(
			Util::any_post_status(),
			array(
				array(
					'key'     => $this->meta()->make_key( 'placement_items' ),
					'compare' => 'LIKE',
					'value'   => '"' . $object_id . '"',
				),
			)
		);

		if ( ! empty( $placements ) ) {
			foreach ( $placements as $placement ) {
				$meta  = $this->meta()->get_post_meta( $placement->ID, PlacementPostMeta::post_meta_keys() );
				$items = $this->meta()->get_value( $meta, 'placement_items', false );

				while ( ( $key = array_search( $object_id, $items ) ) !== false ) {
					unset( $items[ $key ] );
				}

				$this->meta()->update_post_meta( $placement->ID, 'placement_items', array_values( $items ) );
			}
		}
	}

	/**
	 * Remove an ad from any group ordering meta.
	 *
	 * @param mixed $post_id The ad post ID.
	 *
	 * @return void
	 */
	private function remove_ad_from_groups( $post_id ) {
		$key = $this->meta()->make_key( 'ad_order' );

		$groups = Query::groups(
			false,
			array(
				array(
					'key'     => $key,
					'compare' => 'LIKE',
					'value'   => 'i:' . $post_id . ';',
				),
			)
		);

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $group ) {
				$term_meta  = $this->meta()->get_term_meta( $group->term_id, GroupTermMeta::tax_group_meta_keys() );
				$sorted_ids = $this->meta()->get_value( $term_meta, 'ad_order', array() );

				while ( ( $key = array_search( $post_id, $sorted_ids ) ) !== false ) {
					unset( $sorted_ids[ $key ] );
				}

				update_term_meta( $group->term_id, $key, array_values( $sorted_ids ) );
			}
		}
	}

	/**
	 * Delete ad tracking for a particular ad.
	 * This is currently not in use, because it would break overall statistics.
	 *
	 * TODO: In the future, we'll add a tool to manually do this.
	 *
	 * @param mixed $post_id The ad post ID.
	 *
	 * @return void
	 */
	private function delete_ad_tracking( $post_id ) {
		global $wpdb;

		$tracking_tables = array( TrackingLocal::get_tracking_table( 'impressions' ), TrackingLocal::get_tracking_table( 'clicks' ) );
		foreach ( $tracking_tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'ad_id' => $post_id ) );
		}
	}

	/**
	 * Check if an ad was deleted, and perform tasks if so.
	 *
	 * @param int      $post_id The post ID that has been deleted.
	 * @param \WP_Post $post The post object that has been deleted.
	 *
	 * @return void
	 */
	public function check_ad_deleted( $post_id, $post ) {
		if ( ! isset( $post->post_type ) || $post->post_type !== AdCommander::posttype_ad() ) {
			return;
		}

		$this->remove_item_from_placements( 'a_' . $post_id );
		$this->remove_ad_from_groups( $post_id );

		delete_transient( AdminReports::filter_ads_transient() );
	}

	/**
	 * Check if a group was deleted, and perform tasks if so.
	 *
	 * @param int    $term_id The term ID that has been deleted.
	 * @param string $taxonomy The taxonomy of the deleted term.
	 *
	 * @return void
	 */
	public function check_group_deleted( $term_id, $taxonomy ) {
		if ( $taxonomy !== AdCommander::tax_group() ) {
			return;
		}

		$this->remove_item_from_placements( 'g_' . $term_id );
	}

	/**
	 * Delete transients using a wildcard.
	 * This is used to remove all group/ad transients that use a specific format.
	 *
	 * @param mixed $transient_key The wildcard key to query by.
	 *
	 * @return void
	 */
	public static function delete_wildcard_transients( $transient_key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM `{$wpdb->options}`
									 WHERE option_name LIKE %s;",
				'_transient_' . $wpdb->esc_like( $transient_key ) . '%'
			)
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$transient = substr( $result->option_name, 11 ); // remove _transient_.
				delete_transient( $transient );
			}
		}
	}

	/**
	 * Delete group transients using a wildcard.
	 *
	 * @return void
	 */
	private function delete_group_transients() {
		self::delete_wildcard_transients( GroupTermMeta::group_count_transient( '' ) );
	}

	/**
	 * Delete tracking transients using wildcards.
	 *
	 * @return void
	 */
	private function delete_tracking_transients() {
		$total_transients = array(
			TrackingLocal::total_transient( '', 'impressions' ),
			TrackingLocal::total_transient( '', 'clicks' ),
		);

		foreach ( $total_transients as $transient ) {
			self::delete_wildcard_transients( $transient );
		}
	}

	/**
	 * Delete the report transient.
	 *
	 * @return void
	 */
	private function delete_report_transients() {
		delete_transient( AdminReports::filter_ads_transient() );
	}

	/**
	 * Delete frontend CSS transients.
	 *
	 * @param bool $flush_old Whether to flush the pre-1.0.15 transients.
	 *
	 * @return void
	 */
	public static function flush_css_transients( $flush_old = false ) {
		if ( $flush_old ) {
			self::delete_wildcard_transients( Util::ns( 'frontend_prefix_css_' ) );
		}

		self::delete_wildcard_transients( Util::ns( 'prefix_css_' ) );
	}

	/**
	 * Maybe set the onboarding flag if user is not new.
	 * This function is called during Install::maybe_update if the version is being updated.
	 *
	 * Onboarding introduced in 1.1.2.
	 * If a version already existed (not a new install) and the user has used the plugin (has ads), skip onboarding.
	 *
	 * @param int|string $version The version string to check.
	 */
	public static function maybe_set_onboarding( $version ) {
		if ( $version && $version !== '' ) {
			$admin_onboarding = AdminOnboarding::instance();
			if ( $admin_onboarding->needs_onboarding() && Query::has_ads() ) {
				$admin_onboarding->set_onboarded( 'global' );
				$admin_onboarding->set_onboarded( 'ads' );
			}
		}
	}

	/**
	 * Clean up on plugin deactivation.
	 * Always delete transients.
	 * Sometimes delete all data, if option selected.
	 *
	 * @return void
	 */
	public function deactivation_cleanup() {
		/**
		 * Delete transients no matter what; these will be re-created if plugin reactivated.
		 */
		$this->delete_group_transients();
		$this->delete_tracking_transients();
		$this->delete_report_transients();

		self::flush_css_transients();
		Options::instance()->delete( 'custom_css_failure' );

		/**
		 * Delete schedule if one exists.
		 */
		wp_clear_scheduled_hook( Util::ns( 'maybe_expire', '_' ) );
		wp_clear_scheduled_hook( Util::ns( 'maybe_sync_adsense_alerts', '_' ) );

		/**
		 * Delete other data if option selected.
		 */
		$options = Options::instance();

		if ( $options->get( 'delete_data', 'admin', true, false ) ) {

			/**
			 * Deactivate pro
			 */
			if ( ProBridge::instance()->is_pro_loaded() ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				deactivate_plugins( 'ad-commander-pro/ad-commander-pro.php' );
			}

			/**
			 * Delete options
			 */
			$settings = Admin::settings();
			if ( ! empty( $settings ) ) {
				foreach ( $settings as $key => $value ) {
					$options->delete( $key );
				}
			}

			$options->delete( 'version' );
			$options->delete( 'pro_license_status' );
			$options->delete( 'pro_license_expires' );
			$options->delete( 'notifications_hidden' );
			$options->delete( 'adsense_api' );
			$options->delete( 'adsense_api_quota' ); // If someone wants to re-make all of their ads just to get around the quota, fine.

			foreach ( array_merge( array( 'load-group' ), Frontend::get_action_keys( true ) ) as $key ) {
				$options->delete( Frontend::not_a_nonce_option_key( $key ) );
			}

			/**
			 * Delete tracking tables
			 */
			global $wpdb;
			$tracking_tables = array( TrackingLocal::get_tracking_table( 'impressions' ), TrackingLocal::get_tracking_table( 'clicks' ) );
			foreach ( $tracking_tables as $table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping on deactivation.
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
			}

			/**
			 * Delete CPTs and terms
			 */
			$placements = Query::placements( Util::any_post_status() );
			if ( ! empty( $placements ) ) {
				foreach ( $placements as $placement ) {
					wp_delete_post( $placement->ID, true );
				}
			}

			$ads = Query::ads( 'post_id', 'asc', Util::any_post_status() );
			if ( ! empty( $ads ) ) {
				foreach ( $ads as $ad ) {
					wp_delete_post( $ad->ID, true );
				}
			}

			$groups = Query::groups();
			if ( ! empty( $groups ) ) {
				foreach ( $groups as $group ) {
					wp_delete_term( $group->term_id, AdCommander::tax_group() );
				}
			}
		}
	}
}
