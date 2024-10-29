<?php
namespace ADCmdr;

/**
 * Class for working with a virtual ads.txt.
 */
class AdsTxt {

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		/**
		 * Maybe display the ads.txt on init.
		 */
		add_action( 'init', array( $this, 'maybe_display_ads_txt' ), 11 );
	}

	/**
	 * Determine if we should generate an ads.txt.
	 *
	 * @return bool
	 */
	public function should_generate_ads_txt() {
		return ! Options::instance()->get( 'disable_virtual_adstxt', 'general', true ) && $this->can_generate_ads_txt();
	}

	/**
	 * Determine if we can generate an ads.txt on plugin activation.
	 *
	 * @return bool
	 */
	public function should_disable_ads_txt_on_plugin_activation() {
		return ! $this->can_generate_ads_txt();
	}

	/**
	 * Determine if we can generate an ads.txt on this site.
	 *
	 * @param bool $for_admin Return an error message for use in the admin.
	 *
	 * @return bool|array
	 */
	public function can_generate_ads_txt( $for_admin = false ) {

		if ( self::site_is_subdir() ) {
			return ! $for_admin ? false : array(
				'can'     => false,
				'message' => __( 'Your site appears to be using a subfolder. Ads.txt must be placed on the root-level domain.', 'ad-commander' ),
			);
		}

		$is_not_root = self::site_is_not_root_domain( $for_admin );

		if ( $is_not_root !== false ) {
			if ( $is_not_root === 'www' ) {
				return ! $for_admin ? true : array(
					'can'     => true,
					'message' => __( 'Your site is using www. It must redirect to your root domain in order to be crawled. If it does not, we recommend disabling ads.txt.', 'ad-commander' ),
				);
			}

			/**
			 * All non-www subdomains
			 */
			return ! $for_admin ? false : array(
				'can'     => false,
				'message' => __( 'Your site appears to be using a subdomain. Ads.txt must be placed on the root-level domain.', 'ad-commander' ),
			);
		}

		return ! $for_admin ? true : array(
			'can'     => true,
			'message' => '',
		);
	}

	/**
	 * Display the ads.txt if we should.
	 *
	 * TODO: More extensive testing
	 */
	public function maybe_display_ads_txt() {
		if ( isset( $_SERVER['REQUEST_URI'] ) && sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) === '/ads.txt' && $this->should_generate_ads_txt() ) {

			$output = $this->all_ads_txt_lines();

			if ( $output ) {
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo esc_html( $output );
				die();
			}
		}
	}

	/**
	 * Ads a comment to ads.txt to identify it was created by this plugin.
	 *
	 * @return string
	 */
	public function ads_txt_comment_header() {
		return '# ' . AdCommander::title() . ' generated ads.txt';
	}

	/**
	 * The AdSense ads.txt line.
	 *
	 * @return null|string
	 */
	public function adsense_ads_txt_line() {
		$pub_id = sanitize_text_field( AdSense::instance()->current_adsense_publisher_id() );

		if ( ! $pub_id ) {
			return null;
		}

		/**
		 * Filter: adcmdr_ads_txt_adsense
		 *
		 * Filter the generated AdSense ads.txt line to change if necessary.
		 */
		return apply_filters( 'adcmdr_ads_txt_adsense', 'google.com, ' . $pub_id . ', DIRECT, f08c47fec0942fa0' );
	}

	/**
	 * Get the manually added ads.txt lines.
	 *
	 * @return null|string
	 */
	public function manual_ads_txt_lines() {
		return Options::instance()->get( 'ads_txt_records', 'general' );
	}

	/**
	 * Combine manual and automatic ads.txt lines
	 *
	 * @return null|string
	 */
	public function all_ads_txt_lines() {
		$lines   = array();
		$adsense = $this->adsense_ads_txt_line();

		if ( $adsense ) {
			$lines[] = $adsense;
		}

		$manual = $this->manual_ads_txt_lines();
		if ( $manual ) {
			$lines[] = sanitize_textarea_field( $manual );
		}

		if ( empty( $lines ) ) {
			return null;
		}

		return apply_filters( 'adcmdr_manual_ads_txt', implode( "\r\n", array_merge( array( $this->ads_txt_comment_header() ), $lines ) ) );
	}

	/**
	 * Check if an alternate ads.txt file already exists.
	 *
	 * @return bool
	 */
	public function alternate_file_exists() {
		$response = wp_remote_get(
			home_url( 'ads.txt' ),
			array(
				'timeout'   => 5,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers'   => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$response_content_type = wp_remote_retrieve_header( $response, 'content-type' );

		if ( $response &&
				! is_wp_error( $response ) &&
				$response_code !== 404 &&
				stripos( $response_content_type, 'text/plain' ) !== false &&
				strpos( $response_body, $this->ads_txt_comment_header() ) === false
			) {
				return true;
		}

		return false;
	}

	/**
	 * Determine if the current site is in a subdirectory.
	 * This can be overridden in obscure cases with the filter adcmdr_force_is_not_subdir.
	 *
	 * @param string|null $site_url The URL to test.
	 *
	 * @return bool
	 */
	public static function site_is_subdir( $site_url = null ) {
		if ( apply_filters( 'adcmdr_force_is_not_subdir', false ) ) {
			return false;
		}

		$site_url = $site_url ? $site_url : home_url( '/' );

		$parsed_site_url = wp_parse_url( $site_url );

		if ( ! empty( $parsed_site_url['path'] ) && $parsed_site_url['path'] !== '/' ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if the site is not on a root domain. This does not check for subdirectories.
	 * This can be overridden in obscure cases with the filter adcmdr_force_is_root_domain.
	 *
	 * @param bool        $for_admin If this check is for the admin or not.
	 * @param string|null $site_url The URL to test.
	 *
	 * @return bool|string
	 */
	public static function site_is_not_root_domain( $for_admin = false, $site_url = null ) {

		if ( apply_filters( 'adcmdr_force_is_root_domain', false ) ) {
			return false;
		}

		$site_url = $site_url ? trim( $site_url ) : home_url( '/' );

		$parsed_site_url = wp_parse_url( $site_url );

		if ( ! isset( $parsed_site_url['host'] ) ) {
			return false;
		}

		$parsed_host = $parsed_site_url['host'];

		if ( \WP_Http::is_ip_address( $parsed_host ) ) {
			return false;
		}

		$parsed_host    = strtolower( $parsed_host );
		$site_url_parts = explode( '.', $parsed_host );
		$count_parts    = count( $site_url_parts );

		if ( $count_parts < 3 ) {
			return false;
		}

		if ( $count_parts === 3 ) {
			/**
			 * If domain is domain.co.uk, domain.com.au, etc.
			 */
			if ( in_array( $site_url_parts[ $count_parts - 1 ], array( 'com', 'net', 'org', 'gov', 'edu', 'co' ), true ) ) {
				return false;
			}

			if ( $site_url_parts[0] === 'www' ) {
				/**
				 * Return a strring so that we can post a message in the admin.
				 * Ultimately, we will allow this, but want admins to know that WWW should redirect.
				 *
				 * The alternative is to check for a redirect programatically, but the overhead does not seem worth it.
				 */
				return ( $for_admin ) ? 'www' : false;
			}
		}

		return true;
	}
}
