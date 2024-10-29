<?php
namespace ADCmdr;

/**
 * Load plugin textdomain
 */
class Localize {
	/**
	 * Hooks
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the lugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		$locale = get_user_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'ad-commander' );

		unload_textdomain( 'ad-commander' );

		if ( load_textdomain( 'ad-commander', WP_LANG_DIR . '/plugins/ad-commander-' . $locale . '.mo' ) === false ) {
			load_textdomain( 'ad-commander', WP_LANG_DIR . '/ad-commander/ad-commander-' . $locale . '.mo' );
		}

		load_plugin_textdomain( 'ad-commander', false, dirname( ADCMDR_PLUGIN_BASENAME ) . '/languages' );
	}
}
