<?php
namespace ADCmdr;

/**
 * Block fucntionality
 */
class Block {
	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'inline_data' ) );
	}

	/**
	 * Init; register block types.
	 */
	public function init() {
		register_block_type( ADCMDR_PLUGIN_DIR . 'blocks/ad-group-block/build' );
	}

	/**
	 * Enqueue script data in admin for block.
	 */
	public function inline_data() {
		$data = array(
			'groups' => array(),
			'ads'    => array(),
		);

		$groups_terms = Query::groups();
		$ads          = Query::ads( 'post_title', 'asc', Util::any_post_status( array( 'trash' ) ) );

		foreach ( $groups_terms as $term ) {
			$data['groups'][] = array(
				'value' => 'g_' . $term->term_id,
				'label' => $term->name,
			);
		}

		foreach ( $ads as $ad ) {
			$status     = '';
			$post_title = $ad->post_title;

			if ( $ad->post_status !== 'publish' ) {
				$status      = ( $ad->post_status === 'future' ) ? __( 'Scheduled', 'ad-commander' ) : ucfirst( $ad->post_status );
				$post_title .= ' - ' . strtoupper( $status );
			}

			$data['ads'][] = array(
				'value' => 'a_' . $ad->ID,
				'label' => $post_title,
			);
		}

		Util::enqueue_script_data(
			'adcmdr-ad-group-block-editor-script',
			$data
		);
	}
}
