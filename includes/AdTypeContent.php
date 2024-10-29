<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Class to create a content ad.
 *
 * Doesn't extend Ad class, but must be used in conjunction with it.
 */
class AdTypeContent {

	/**
	 * The current ad post.
	 *
	 * @var \WP_Post
	 */
	private $ad;

	/**
	 * The type of content ad we are working with.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The current post's meta.
	 *
	 * @var mixed
	 */
	private $meta;

	/**
	 * The current instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * AdTypeBanner class __construct.
	 *
	 * @param \WP_Post $ad The WP_Post object to use while creating this Ad.
	 * @param array    $meta The current meta for the post (so we don't have to re-query it).
	 * @param WOMeta   $wo_meta An instance of WOMeta, so we don't have to re-create it.
	 * @param string   $type The type of content ad we are working with.
	 */
	public function __construct( \WP_Post $ad, array $meta, WOMeta $wo_meta, string $type ) {
		$this->ad      = $ad;
		$this->meta    = $meta;
		$this->wo_meta = $wo_meta;
		$this->type    = $type;
	}

	/**
	 * Build the content ad.
	 *
	 * @return string
	 */
	public function build_ad() {
		$key     = ( $this->type === 'textcode' ) ? 'adcontent_text' : 'adcontent_rich';
		$content = $this->wo_meta->get_value( $this->meta, $key );

		if ( $key === 'adcontent_text' ) {
			$content = str_replace( array( "\r", "\n" ), '', $content );
		}

		return $content;
	}
}
