<?php
namespace ADCmdr;

/**
 * Interface with the addon plugins.
 */
class AddonBridge {
	/**
	 * An instance of this class.
	 *
	 * @var null|AddonBridge
	 */
	private static $instance = null;

	/**
	 * The URL to the aaddons settings page.
	 *
	 * @var string
	 */
	private $addons_admin_url;

	/**
	 * Get or create an instance.
	 *
	 * @return AddonBridge
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Link to an addon URL
	 *
	 * @param string $addon The addon to link to.
	 *
	 * @return string
	 */
	public static function addon_url( $addon ) {
		switch ( $addon ) {
			case 'tools':
				return 'https://wordpress.org/plugins/ad-commander-tools/';
				break;

			default:
				return '';
			break;
		}
	}

	/**
	 * Get and set the addons settings url.
	 *
	 * @return string
	 */
	public function addons_admin_url() {
		if ( ! $this->addons_admin_url ) {
			$this->addons_admin_url = Admin::settings_admin_url( 'addons' );
		}

		return $this->addons_admin_url;
	}

	/**
	 * Check if the addon plugin is loaded.
	 *
	 * @param string $addon The addon to check.
	 *
	 * @return bool
	 */
	public function is_addon_loaded( $addon ) {
		switch ( $addon ) {
			case 'tools':
				return defined( 'ADCMRDRTOOLS_LOADED' ) && ADCMRDRTOOLS_LOADED === true;
			break;

			default:
				return false;
			break;
		}
	}
}
