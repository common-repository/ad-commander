<?php
namespace ADCmdr;

/**
 * Admin Dashboard page and related functionality.
 */
class AdminDashboard extends Admin {

	/**
	 * Create the Reports page.
	 *
	 * @return void
	 */
	public function page() {
		$this->start_wrap();
		$this->title( 'Dashboard' );
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
	 * Create content for dashboard.
	 *
	 * @return void
	 */
	private function content() {
		$this->notifications();
		$this->start_div( Util::ns( 'twocol' ) );
		$this->intro();
		$this->nextsteps();
		$this->end_div();

		$addons = $this->get_addons();

		if ( $addons ) {
			$this->postbox( esc_html__( 'Ad Commander Add-ons', 'ad-commander' ), $addons );
		}
	}

	/**
	 * Display notifications to the dashboard.
	 *
	 * @return void
	 */
	private function notifications() {
		$admin_n = new AdminNotifications();
		$content = $admin_n->display();

		$this->postbox( __( 'Notifications', 'ad-commander' ), $content );
	}

	/**
	 * Create intro for dashboard.
	 *
	 * @return void
	 */
	private function intro() {
		$content      = Html::h3( esc_html__( 'Manage your ads, groups and placements', 'ad-commander' ) );
		$content     .= $this->start_div( 'adcmdr-btn-group', array( 'display' => false ) );
			$content .= Html::abtn( self::admin_ad_post_type_url(), esc_html__( 'Manage Ads', 'ad-commander' ), false, true );
			$content .= Html::abtn( self::admin_group_tax_url(), esc_html__( 'Manage Groups', 'ad-commander' ), false, true );
			$content .= Html::abtn( self::admin_placement_post_type_url(), esc_html__( 'Manage Placements', 'ad-commander' ), false, true );
		$content     .= $this->end_div( array( 'display' => false ) );
		$content     .= '<hr class="adcmdr-divide" />';
		$content     .= Html::h3( esc_html__( 'Configure Ad Commander', 'ad-commander' ) );
		$content     .= $this->start_div( 'adcmdr-btn-group', array( 'display' => false ) );
			$content .= Html::abtn( self::settings_admin_url(), esc_html__( 'Configure Settings', 'ad-commander' ), null );
		$content     .= $this->end_div( array( 'display' => false ) );

		$this->postbox( esc_html__( 'Manage ads', 'ad-commander' ), $content );
	}

	/**
	 * Create next steps section for dashboard.
	 *
	 * @return void
	 */
	private function nextsteps() {
		$content = Html::h4( esc_html__( 'Support resources', 'ad-commander' ) );
		/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
		$support_section_link = sprintf( esc_html__( '%1$sVisit the support section%2$s for help.', 'ad-commander' ), '<a href="' . esc_url( self::support_admin_url() ) . '">', '</a>' );
		$content             .= Html::p( $support_section_link );

		$content .= Html::h4( esc_html__( 'Join our newsletter', 'ad-commander' ) );
		/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
		$join_newsletter_link = sprintf( esc_html__( 'Get news about important plugin updates by %1$sjoining our newsletter%2$s.', 'ad-commander' ), '<a href="' . esc_url( self::newsletter_url() ) . '" target="_blank">', '</a>' );
		$content             .= Html::p( $join_newsletter_link );

		$content .= Html::h4( esc_html__( 'Leave a review', 'ad-commander' ) );
		/* translators: %1$s: five star characters */
		$content .= Html::p( sprintf( esc_html__( 'Your %1$s reviews and bug reports are invaluable to Ad Commander and help us maintain a free version of this plugin. We appreciate your support!', 'ad-commander' ), '★★★★★' ) );
		$content .= $this->start_div( 'adcmdr-btn-group', array( 'display' => false ) );
		$content .= Html::abtn( self::review_url(), esc_html__( 'Leave a review', 'ad-commander' ) );
		$content .= Html::abtn( self::support_admin_url(), esc_html__( 'Get support', 'ad-commander' ), false, true );
		$content .= $this->end_div( array( 'display' => false ) );

		$this->postbox( esc_html__( 'Support and updates', 'ad-commander' ), $content );
	}
}
