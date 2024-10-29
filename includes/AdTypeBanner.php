<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Class to create a banner ad.
 *
 * Doesn't extend Ad class, but must be used in conjunction with it.
 */
class AdTypeBanner {

	/**
	 * The current ad post.
	 *
	 * @var \WP_Post
	 */
	private $ad;

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
	 */
	public function __construct( \WP_Post $ad, array $meta, WOMeta $wo_meta ) {
		$this->ad      = $ad;
		$this->meta    = $meta;
		$this->wo_meta = $wo_meta;
	}

	/**
	 * Build the image tag for the post thumbnail, if one exists.
	 *
	 * @return string
	 */
	private function build_image() {
		$attachment_id = get_post_thumbnail_id( $this->ad );

		if ( ! $attachment_id ) {
			return '';
		}

		$size  = 'full';
		$image = wp_get_attachment_image_src( $attachment_id, $size );

		if ( ! $image ) {
			return '';
		}

		/**
		 * If a custom height and width are set, we'll create our own image tag.
		 */
		$width  = $this->wo_meta->get_value( $this->meta, 'display_width' );
		$height = $this->wo_meta->get_value( $this->meta, 'display_height' );

		if ( $width ) {
			$width = absint( $width );
		}

		if ( $height ) {
			$height = absint( $height );
		}

		if ( ( ! $width && ! $height ) || ( $width === $image[1] && $height === $image[2] ) ) {
			/**
			 * Just reutrn the WP-generated image tag.
			 */
			return wp_get_attachment_image( $attachment_id, $size );
		}

		$hw     = image_hwstring( $width, $height );
		$alt    = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
		$sizes  = wp_get_attachment_image_sizes( $attachment_id, $size );

		$img = sprintf( '<img src="%s" alt="%s" %s srcset="%s" sizes="%s" />', esc_url( $image[0] ), esc_attr( $alt ), $hw, $srcset, $sizes );
		$img = wp_img_tag_add_loading_optimization_attrs( $img, current_filter() );

		return $img;
	}

	/**
	 * Build the banner ad.
	 *
	 * @return string
	 */
	public function build_ad() {
		$html = '';

		$url = $this->wo_meta->get_value( $this->meta, 'bannerurl' );

		if ( $url ) {
			$newwindow  = Util::truthy_or_site_default( $this->wo_meta->get_value( $this->meta, 'newwindow', 'site_default' ), 'newwindow', 'general' );
			$noopener   = Util::truthy_or_site_default( $this->wo_meta->get_value( $this->meta, 'noopener', 'site_default' ), 'rel_attributes', 'general', 'noopener' );
			$noreferrer = Util::truthy_or_site_default( $this->wo_meta->get_value( $this->meta, 'noreferrer', 'site_default' ), 'rel_attributes', 'general', 'noreferrer' );
			$nofollow   = Util::truthy_or_site_default( $this->wo_meta->get_value( $this->meta, 'nofollow', 'site_default' ), 'rel_attributes', 'general', 'nofollow' );
			$sponsored  = Util::truthy_or_site_default( $this->wo_meta->get_value( $this->meta, 'sponsored', 'site_default' ), 'rel_attributes', 'general', 'sponsored' );

			$html .= '<a href="' . esc_url( $url ) . '"';

			if ( $newwindow ) {
				$html .= ' target="_blank"';
			}

			$rel = array();

			if ( $sponsored ) {
				$rel[] = 'sponsored';
			}

			if ( $nofollow ) {
				$rel[] = 'nofollow';
			}

			if ( $noopener ) {
				$rel[] = 'noopener';
			}

			if ( $noreferrer ) {
				$rel[] = 'noreferrer';
			}

			if ( ! empty( $rel ) ) {
				$html .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
			}

			$html .= '>';
		}

		$html .= $this->build_image();

		if ( $url ) {
			$html .= '</a>';
		}

		return $html;
	}
}
