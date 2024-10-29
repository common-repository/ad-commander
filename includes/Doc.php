<?php
namespace ADCmdr;

/**
 * Class for creating documentation links in plugin.
 */
class Doc {

	/**
	 * Array of doc links.
	 *
	 * @return array
	 */
	public static function doc_urls() {
		$args = array( 'utm_medium' => 'button' );

		return array(
			'rendering'            => AdCommander::public_site_url( 'documentation/ad-group-rendering', $args ),
			'bots'                 => AdCommander::public_site_url( 'documentation/bots-disabling-ads-or-tracking', $args ),
			'group_mode'           => AdCommander::public_site_url( 'documentation/group-display-modes', $args ),
			'group_order'          => AdCommander::public_site_url( 'documentation/group-ordering-methods', $args ),
			'tracking_methods'     => AdCommander::public_site_url( 'documentation/impression-and-click-tracking-methods', $args ),
			'ad_type'              => AdCommander::public_site_url( 'documentation/understanding-ad-types', $args ),
			'content_targeting'    => AdCommander::public_site_url( 'documentation/content-targeting', $args ),
			'visitor_targeting'    => AdCommander::public_site_url( 'documentation/visitor-targeting', $args ),
			'geo_targeting'        => AdCommander::public_site_url( 'documentation/geolocation-targeting-with-maxmind', $args ),
			'expiring_ads'         => AdCommander::public_site_url( 'documentation/scheduling-and-expiring-ads', $args ),
			'custom_code'          => AdCommander::public_site_url( 'documentation/custom-code-for-ads-and-groups', $args ),
			'placement_position'   => AdCommander::public_site_url( 'documentation/placement-positions', $args ),
			'requiring_consent'    => AdCommander::public_site_url( 'documentation/requiring-consent', $args ),
			'unfiltered_html'      => AdCommander::public_site_url( 'documentation/unfiltered-html', $args ),
			'manual_placement'     => AdCommander::public_site_url( 'documentation/manual-ad-group-placement', $args ),
			'automantic_placement' => AdCommander::public_site_url( 'documentation/automatic-placement-of-ads-and-groups', $args ),
			'popup_placement'      => AdCommander::public_site_url( 'documentation/create-a-popup-with-automatic-placements', $args ),
			'amp'                  => AdCommander::public_site_url( 'documentation/amp-ads-in-wordpress', $args ),
			'adsense'              => AdCommander::public_site_url( 'documentation/adsense-in-wordpress', $args ),
			'first_ad'             => AdCommander::public_site_url( 'documentation/creating-your-first-ad-on-your-wordpress-site', $args ),
		);
	}

	/**
	 * Displays a doc link for a specified slug.
	 *
	 * @param string      $slug Doc link to use.
	 * @param bool        $display To display or return.
	 * @param bool|string $text The text for the button.
	 *
	 * @return void
	 */
	public static function doc_link( $slug, $display = true, $text = false ) {
		$urls = self::doc_urls();
		$url  = ( isset( $urls[ $slug ] ) ) ? $urls[ $slug ] : false;

		if ( $url ) {
			if ( Options::instance()->get( 'disable_doc_links', 'admin', true ) ) {
				return;
			}

			if ( ! $text ) {
				$text = __( 'Help', 'ad-commander' );
			}

			$link = '<a href="' . esc_url( $url ) . '" target="_blank" title="' . esc_attr( __( 'Read documentation', 'ad-commander' ) ) . '" class="adcmdr-doc-link button button-secondary">' . esc_html( $text ) . '<i class="dashicons dashicons-external"></i></a>';

			$allowed_html = array(
				'a' => array(
					'href'   => array(),
					'title'  => array(),
					'target' => array(),
					'class'  => array(),
				),
				'i' =>
				array(
					'class' => array(),
				),
			);

			if ( ! $display ) {
				return wp_kses( $link, $allowed_html );
			}

			echo wp_kses( $link, $allowed_html );
		}
	}
}
