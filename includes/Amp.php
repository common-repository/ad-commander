<?php
namespace ADCmdr;

/**
 * Class for working with Amp and related settings.
 */
class Amp {
	/**
	 * An instance of this class.
	 *
	 * @var null|Amp
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return Amp
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp', array( $this, 'hooks_amp_auto_ads' ) );
	}

	/**
	 * Determine if this is an amp page load.
	 *
	 * @return bool
	 */
	public function is_amp() {
		global $pagenow;

		if ( is_admin() || is_embed() || is_feed() ||
			( isset( $pagenow ) && in_array( $pagenow, array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ), true ) ) ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		) {
			return false;
		}

		if ( ! did_action( 'wp' ) ) {
			return false;
		}

		return ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ||

		// AMP for WP.
		( function_exists( 'ampforwp_is_amp_endpoint' ) && ampforwp_is_amp_endpoint() ) ||

		// WP AMP.
		( function_exists( 'is_wp_amp' ) && is_wp_amp() );
	}

	/**
	 * Determine if an AMP plugin is enabled.
	 *
	 * @return bool
	 */
	public function has_amp_plugin() {
		return function_exists( 'is_amp_endpoint' ) || function_exists( 'is_wp_amp' ) || function_exists( 'ampforwp_is_amp_endpoint' );
	}

	/**
	 * Enable hooks for inserting amp auto ads codes.
	 *
	 * @return void
	 */
	public function hooks_amp_auto_ads() {
		if ( ! $this->is_amp() ) {
			return;
		}

		if ( Options::instance()->get( 'enable_amp_auto_ads', 'adsense', true ) && AdSense::instance()->current_adsense_publisher_id() ) {
			if ( apply_filters( 'adcmdr_amp_auto_ads_enabled', true ) ) {
				/**
				 * Head script
				 */
				add_action( 'amp_post_template_data', array( $this, 'amp_auto_ads_head_script' ) );

				/**
				 * Body script
				 */
				add_action( 'bunyad_amp_pre_main', array( $this, 'amp_auto_ads_body_code' ) );
				add_action( 'wp_footer', array( $this, 'amp_auto_ads_body_code' ) );
				add_action( 'amp_post_template_footer', array( $this, 'amp_auto_ads_body_code' ) );
			}
		}
	}

	/**
	 * Get the base URL for Amp JS
	 *
	 * @return string
	 */
	public function amp_auto_ads_js_base_url() {
		return apply_filters( 'adcmdr_amp_auto_ads_js_base_url', 'https://cdn.ampproject.org/v0/amp-auto-ads-0.1.js' );
	}

	/**
	 * Get full AMP script tag.
	 *
	 * @return string
	 */
	public function get_amp_auto_ads_script_tag() {
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This script is output using custom AMP plugin hooks and cannot be output reliably with wp_enqueue_script.
		return '<script async custom-element="amp-auto-ads" src="' . esc_url( $this->amp_auto_ads_js_base_url() ) . '"></script>';
	}

	/**
	 * Gets the full AMP auto-ads element.
	 *
	 * @param bool|string $publisher_id The publisher ID.
	 * @param bool|string $fallback_id A fake publisher id used for display purposes.
	 *
	 * @return string
	 */
	public function get_amp_auto_ads_element( $publisher_id = false, $fallback_id = false ) {
		if ( ! $publisher_id ) {
			$publisher_id = AdSense::instance()->current_adsense_publisher_id();
		}

		if ( ! $publisher_id && $fallback_id ) {
			$publisher_id = $fallback_id;
		}

		if ( ! $publisher_id ) {
			return '';
		}

		return '<amp-auto-ads type="adsense" data-ad-client="ca-' . esc_attr( $publisher_id ) . '"></amp-auto-ads>';
	}

	/**
	 * Output the AMP auto ads head script.
	 *
	 * @return void
	 */
	public function amp_auto_ads_head_script() {
		$script = apply_filters( 'adcmdr_amp_auto_ads_script_tag', $this->get_amp_auto_ads_script_tag() );

		if ( $script && $script !== '' ) {
			echo wp_kses(
				$script,
				apply_filters(
					'adcmdr_amp_auto_ads_script_tag_allowed_html',
					array(
						'script' => array(
							'async'          => array(),
							'src'            => array(),
							'nomodule'       => array(),
							'crossorigin'    => array(),
							'custom-element' => array(),
						),
					)
				)
			);
		}
	}

	/**
	 * Output the AMP auto ads body code.
	 *
	 * @return void
	 */
	public function amp_auto_ads_body_code() {
		$code = apply_filters( 'adcmdr_amp_auto_ads_body_code', $this->get_amp_auto_ads_element() );

		if ( $code && $code !== '' ) {
			echo wp_kses(
				$code,
				apply_filters(
					'adcmdr_amp_auto_ads_body_code_allowed_html',
					array(
						'amp-auto-ads' => array(
							'type'           => array(),
							'data-ad-client' => array(),
							'class'          => array(),
						),
					)
				)
			);
		}
	}
}
