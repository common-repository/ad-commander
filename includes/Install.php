<?php
namespace ADCmdr;

/**
 * Class for handling various tasks during activation, updating, etc.
 */
class Install {

	/**
	 * Fired on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::add_cap( get_role( 'administrator' ) );

		TrackingLocal::create_tables();

		self::maybe_update();
	}

	/**
	 * Set the plugin version in the database to the current version.
	 *
	 * @return void
	 */
	public static function set_dbversion() {
		Options::instance()->update( 'version', AdCommander::version() );
	}

	/**
	 * If the database version doesn't match the current version, run updates.
	 *
	 * @return void
	 */
	public static function maybe_update() {
		if ( ! wp_doing_ajax() ) {
			$version = Util::get_dbversion();

			if ( $version !== AdCommander::version() ) {
				Maintenance::flush_css_transients( version_compare( $version, '1.0.15', '<' ) );
				Maintenance::maybe_set_onboarding( $version );
				self::update();
			}

			if ( is_admin() && Tracking::instance()->is_local_tracking_enabled() ) {
				global $wpdb;

				foreach ( array( TrackingLocal::get_tracking_table( 'impressions' ), TrackingLocal::get_tracking_table( 'clicks' ) ) as $table_name ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query ok and do not want caching
					if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
						TrackingLocal::create_tables();
						break;
					}
				}
			}
		}
	}

	/**
	 * Tasks to run during a version update.
	 *
	 * @return void
	 */
	public static function update() {
		self::set_dbversion();
	}

	/**
	 * Add capabilities to a role.
	 *
	 * @param \WP_Role $role The \WP_Role to add capabilities.
	 *
	 * @return void
	 */
	public static function add_cap( $role ) {
		$role->add_cap( AdCommander::capability(), true );
	}

	/**
	 * Remove capabilities from a role.
	 *
	 * @param \WP_Role $role The \WP_Role to remove capabilities.
	 *
	 * @return void
	 */
	public static function remove_cap( $role ) {
		$role->remove_cap( AdCommander::capability() );
	}
}
