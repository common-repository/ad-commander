<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Admin Reports page and related functionality.
 */
class AdminSupport extends Admin {

	/**
	 * Create the Reports page.
	 *
	 * @return void
	 */
	public function page() {
		$this->start_wrap();
		$this->title( __( 'Support', 'ad-commander' ) );
		$this->start_poststuff();
		$this->content();
		$this->end_poststuff();
		$this->end_wrap();
	}

	/**
	 * Create HTML for the title of the page.
	 *
	 * @param string $title The title of the page.
	 *
	 * @return void
	 */
	private function title( $title ) {
		$this->sf()->title( $title );
	}

	/**
	 * Create content for support page.
	 *
	 * @return void
	 */
	private function content() {
		$this->postbox( __( 'News and updates', 'ad-commander' ), $this->get_newsletter() );
		$this->start_div( Util::ns( 'twocol' ) );
			$this->postbox( __( 'Manuals and forums', 'ad-commander' ), $this->get_support() );
			$this->postbox( __( 'Priority support', 'ad-commander' ), $this->get_priority_support() );
		$this->end_div();

		$addons = $this->get_addons();

		if ( $addons ) {
			$this->postbox( esc_html__( 'Ad Commander Add-ons', 'ad-commander' ), $addons );
		}
	}

	/**
	 * Create content for support page.
	 *
	 * @return string
	 */
	private function get_support() {
		return $this->info( __( 'Ad Commander is a new plugin, and we appreciate you reporting bugs and requesting features. Please visit the following resources to do so. Thank you for your support!', 'ad-commander' ), array(), array( 'display' => false ) ) .
		// Html::p( 'If you need help with ' . AdCommander::title() . ', please visit one of the following resources.' ) .
		Html::h4( __( 'Manuals and knowledgebase', 'ad-commander' ) ) .
		Html::p( __( 'Directions and answers to frequently asked questions.', 'ad-commander' ) . ' ' . Html::a( self::documentation_url(), __( 'Visit knowledgebase >', 'ad-commander' ) ) ) .
		Html::h4( __( 'Public support forums', 'ad-commander' ) ) .
		Html::p( __( 'WordPress.org support forum for reporting a bug or requesting a feature.', 'ad-commander' ) . ' ' . Html::a( self::support_public_url(), __( 'Visit forums >', 'ad-commander' ) ) ) .
		Html::h4( __( 'Report security issue', 'ad-commander' ) ) .
		Html::p( __( 'Discreetly report possible security issues with Ad Commander.', 'ad-commander' ) . ' ' . Html::a( self::security_issue_url(), __( 'Report problem >', 'ad-commander' ) ) );
	}

	/**
	 * Create content for support page.
	 *
	 * @return string
	 */
	private function get_priority_support() {
		return Html::h4( __( 'Support tickets', 'ad-commander' ) ) .
		Html::p( __( 'Email support and support for Ad Commander Pro is available through our ticket system.', 'ad-commander' ) ) .
		Html::abtn( self::pro_support_url( array( 'utm_medium' => 'button' ) ), __( 'Open a ticket', 'ad-commander' ) ) .
		Html::p( __( 'Login to your Pro account to submit a ticket. Not yet a Pro user?', 'ad-commander' ) . ' ' . Html::a( AdCommander::public_site_url(), __( 'Learn more >', 'ad-commander' ) ) );
	}

	/**
	 * Create the newsletter notice.
	 *
	 * @return string
	 */
	private function get_newsletter() {
		return $this->start_div( Util::ns( 'flexrowend' ), array( 'display' => false ) ) .
			$this->start_div( array(), array( 'display' => false ) ) .
			Html::h4( __( 'Newsletter signup', 'ad-commander' ) ) . Html::p( __( 'Sign up for our newsletter to receive Ad Commander news, announcements, and feature updates.', 'ad-commander' ) ) .
			$this->end_div( array( 'display' => false ) ) .
			$this->start_div( array(), array( 'display' => false ) ) .
			Html::p( Html::abtn( self::newsletter_url( array( 'utm_medium' => 'button' ) ), __( 'Join our newsletter', 'ad-commander' ) ) ) .
			$this->end_div( array( 'display' => false ) ) .
			$this->end_div( array( 'display' => false ) );
	}
}
