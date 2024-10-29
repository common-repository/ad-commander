<?php
namespace ADCmdr;

/**
 * Interface with the pro plugin.
 */
class ProBridge {

	/**
	 * An instance of this class.
	 *
	 * @var null|ProBridge
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return ProBridge
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if the pro plugin is loaded.
	 * This constant is defined during plugins_loaded -- AFTER Pro has checked for Ad Commander.
	 *
	 * @return bool
	 */
	public function is_pro_loaded() {
		return defined( 'ADCMDR_PRO_LOADED' ) && ADCMDR_PRO_LOADED === true;
	}

	/**
	 * Check if the pro plugin is active.
	 * This constant is defined when the file is loaded -- before plugins_loaded.
	 *
	 * @return bool
	 */
	public function is_pro_activated() {
		return defined( 'ADCMDR_PRO_PLUGIN_BASENAME' ) && ADCMDR_PRO_PLUGIN_BASENAME !== null && ADCMDR_PRO_PLUGIN_BASENAME !== false;
	}

	/**
	 * Get and set the addons settings url.
	 *
	 * @return string
	 */
	public function addons_admin_url() {
		return AddonBridge::instance()->addons_admin_url();
	}

	/**
	 * The EDD Pro license name.
	 *
	 * This is included here instead of Pro, because if it changes in a future we can push an update to the public repo.
	 * Then Pro could update from the private repo using the correct name.
	 */
	public static function edd_item_name() {
		return 'Ad Commander Pro';
	}

	/**
	 * Is the pro license added?
	 *
	 * @return bool
	 */
	public function is_pro_license_added() {
		if ( $this->is_pro_loaded() ) {
			return License::instance()->is_pro_license_added();
		}

		return false;
	}

	/**
	 * Get the pro license status.
	 *
	 * @param bool $refresh Whether to refresh the status.
	 *
	 * @return bool|string
	 */
	public function pro_license_status( $refresh = false ) {
		if ( $this->is_pro_loaded() ) {
			return License::instance()->license_status( $refresh );
		}

		return false;
	}

	/**
	 * Enqueue script data for the license settings page.
	 *
	 * @param string $status The status to pass to the data.
	 *
	 * @return void
	 */
	public function enqueue_pro_license_script_data( $status ) {
		if ( $this->is_pro_loaded() ) {
			$admin_license = new AdminLicense();
			$admin_license->enqueue_script_data( $status );
		}
	}

	/**
	 * Reset the pro license status.
	 *
	 * @param bool $hard Hard reset or not.
	 * @param bool $pending Reset to pending or not.
	 *
	 * @return void
	 */
	public function pro_license_reset_status( $hard = true, $pending = false ) {
		if ( $this->is_pro_loaded() ) {
			License::instance()->reset_status( $hard );

			if ( $pending ) {
				License::instance()->reset_status_to_pending();
			}
		}
	}

	/**
	 * Deactivate the license key.
	 *
	 * @param mixed $license_key The license key, if one exists.
	 *
	 * @return void
	 */
	public function pro_license_deactivate( $license_key ) {
		if ( $this->is_pro_loaded() ) {
			License::instance()->deactivate_license( $license_key );
		}
	}

	/**
	 * Group modes tha require Pro.
	 *
	 * @return array
	 */
	public function pro_group_modes() {
		return array( 'grid' );
	}

	/**
	 * Placement positions that require pro.
	 *
	 * @return array
	 */
	public function pro_placement_positions() {
		return array( 'within_content', 'post_list', 'above_title', 'after_p_tag', 'popup' );
	}

	/**
	 * Check for specific version of Pro.
	 *
	 * @param string $ver The required version.
	 *
	 * @return array
	 */
	public function pro_version_required( $ver ) {
		if ( ! self::is_pro_loaded() ) {
			return false;
		}

		return version_compare( AdCommanderPro::version(), $ver, '>=' );
	}

	/**
	 * These conditions are only usable if Pro is installed.
	 *
	 * @return array
	 */
	public function pro_visitor_conditions() {
		/**
		 * All conditions except 'logged in'
		 */
		$targets = TargetingMeta::allowed_visitor_targets();

		if ( isset( $targets['logged_in'] ) ) {
			unset( $targets['logged_in'] );
		}

		return array_keys( $targets );
	}

	/**
	 * These group ordering methods are only usable if Pro is installed.
	 *
	 * @return array
	 */
	public function pro_group_order_methods() {
		return array( 'weighted', 'sequential' );
	}

	/**
	 * The label appended to featurs that require pro.
	 *
	 * @return string
	 */
	public static function pro_label( $pro_link = false ) {
		if ( $pro_link ) {
			/* translators: This text is appended to features that are only included in the pro version. Leading spacing and hyphen (or equivalent) should be included. */
			return ' - <a href="' . Admin::pro_upgrade_url() . '" target="_blank">' . __( 'Upgrade to Pro', 'ad-commander' ) . '</a>';
		}

		/* translators: This text is appended to features that are only included in the pro version. Leading spacing and hyphen (or equivalent) should be included. */
		return __( ' - Pro Add-on', 'ad-commander' );
	}
}
