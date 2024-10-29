<?php
/**
 * Plugin Name:     Ad Commander - Banner & Ad Manager
 * Plugin URI:      https://github.com/wildoperation/Ad-Commander
 * Description:     Insert, schedule and track custom advertising banners or script ads from AdSense, Amazon, and other affiliate networks into your site.
 * Version:         1.1.8
 * Author:          Wild Operation
 * Author URI:      https://wildoperation.com
 * License:         GPL-3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:     ad-commander
 *
 * @package WordPress
 * @subpackage Ad Commander - Banner & Ad Manager
 * @since 1.0.0
 * @version 1.1.8
 */

/* Abort! */
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ADCMDR_LOADED', true );
define( 'ADCMDR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADCMDR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADCMDR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load
 */
require ADCMDR_PLUGIN_DIR . 'vendor/autoload.php';
require ADCMDR_PLUGIN_DIR . 'includes/functions/frontend.php';

/**
 * Activation
 */
register_activation_hook(
	__FILE__,
	function () {
		ADCmdr\Install::activate();
	}
);

/**
 * Deactivation
 */
register_deactivation_hook(
	__FILE__,
	function () {
		$maintenance = new ADCmdr\Maintenance();
		$maintenance->deactivation_cleanup();
	}
);


/**
 * Review request framework
 */
new ADCmdr\Vendor\WOWPRB\WPPluginReviewBug(
	__FILE__,
	'ad-commander',
	array(
		'intro'            => __( 'Your reviews are invaluable to Ad Commander and help us maintain a free version of this plugin. We appreciate your support! If you need assistance, please visit the support section of this plugin.', 'ad-commander' ),
		'rate_link_text'   => __( 'Leave ★★★★★ rating', 'ad-commander' ),
		'need_help_text'   => __( 'I need help', 'ad-commander' ),
		'remind_link_text' => __( 'Remind me later', 'ad-commander' ),
		'nobug_link_text'  => __( 'Don\'t ask again', 'ad-commander' ),
	),
	array(
		'need_help_url' => ADCmdr\Admin::support_admin_url(),
	)
);

/**
 * Initialize; plugins_loaded
 */
add_action(
	'plugins_loaded',
	function () {
		/**
		 * Has the plugin version updated?
		 */
		ADCmdr\Install::maybe_update();

		/**
		 * Initiate classes and their hooks.
		 */
		$classes = array(
			'ADCmdr\Localize',
			'ADCmdr\PostTypes',
			'ADCmdr\Block',
			'ADCmdr\Admin',
			'ADCmdr\Maintenance',
			'ADCmdr\Placement',
			'ADCmdr\Frontend',
			'ADCmdr\AdsTxt',
			'ADCmdr\ProBridge',
			'ADCmdr\Amp',
		);

		foreach ( $classes as $class ) {
			$instance = new $class();

			if ( method_exists( $instance, 'hooks' ) ) {
				$instance->hooks();
			}
		}
	},
	10
);
