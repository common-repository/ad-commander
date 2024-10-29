<?php
namespace ADCmdr;

/**
 * Class or creating HTML elements from strings and settings.
 */
class Html {

	/**
	 * Create an element.
	 *
	 * @param string      $element The element to create (e.g., 'p').
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function element( $element, $content, $classes = null, $id = null ) {
		$element = sanitize_text_field( $element );
		return '<' . $element . self::maybe_class( $classes ) . self::maybe_id( $id ) . '>' . $content . '</' . $element . '>';
	}

	/**
	 * Maybe create a class attribute.
	 *
	 * @param mixed $classes Classes to add.
	 *
	 * @return string
	 */
	public static function maybe_class( $classes ) {
		if ( $classes ) {

			if ( is_array( $classes ) ) {
				$classes = implode( ' ', $classes );
			}

			return ' class="' . esc_attr( $classes ) . '"';
		}

		return '';
	}

	/**
	 * Maybe create an id attribute.
	 *
	 * @param mixed $id Id to add.
	 *
	 * @return string
	 */
	public static function maybe_id( $id ) {
		if ( $id ) {
			return ' id="' . esc_attr( $id ) . '"';
		}

		return '';
	}

	/**
	 * Create a p element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function p( $content, $classes = null, $id = null ) {
		return self::element( 'p', $content, $classes, $id );
	}

	/**
	 * Create an h1 element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function h1( $content, $classes = null, $id = null ) {
		return self::element( 'h1', $content, $classes, $id );
	}

	/**
	 * Create an h2 element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function h2( $content, $classes = null, $id = null ) {
		return self::element( 'h2', $content, $classes, $id );
	}

	/**
	 * Create an h3 element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function h3( $content, $classes = null, $id = null ) {
		return self::element( 'h3', $content, $classes, $id );
	}

	/**
	 * Create an h4 element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function h4( $content, $classes = null, $id = null ) {
		return self::element( 'h4', $content, $classes, $id );
	}

	/**
	 * Create an h5 element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function h5( $content, $classes = null, $id = null ) {
		return self::element( 'h5', $content, $classes, $id );
	}

	/**
	 * Create an h6 element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function h6( $content, $classes = null, $id = null ) {
		return self::element( 'h6', $content, $classes, $id );
	}

	/**
	 * Create a div element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function div( $content, $classes = null, $id = null ) {
		return self::element( 'div', $content, $classes, $id );
	}

	/**
	 * Create a ul element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function ul( $items, $classes = null, $id = null ) {
		$items = Util::arrayify( $items );
		$html  = '';

		foreach ( $items as $item ) {
			$html .= self::li( $item );
		}

		return self::element( 'ul', $html, $classes, $id );
	}

	/**
	 * Create an li element.
	 *
	 * @param string      $content The content inside the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function li( $content, $classes = null, $id = null ) {
		return self::element( 'li', $content, $classes, $id );
	}

	/**
	 * Create an a element.
	 *
	 * @param string      $link The url for the link.
	 * @param string      $text The content inside the element.
	 * @param string|null $target The target for the element.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function a( $link, $text, $target = '_blank', $classes = null, $id = null ) {
		$html = '<a href="' . esc_url( $link ) . '"';

		if ( $target ) {
			$html .= ' target="' . esc_attr( $target ) . '"';
		}

		$html .= self::maybe_class( $classes ) . self::maybe_id( $id );

		$html .= '>' . esc_html( $text ) . '</a>';

		return $html;
	}

	/**
	 * Create an a button element.
	 *
	 * @param string      $link The url for the link.
	 * @param string      $text The content inside the element.
	 * @param string|null $target The target for the element.
	 * @param bool        $secondary Whether this is a secondary button or not.
	 * @param null|array  $classes Any classes to add.
	 * @param null|string $id The ID to add.
	 *
	 * @return string
	 */
	public static function abtn( $link, $text, $target = '_blank', $secondary = false, $classes = null, $id = null ) {
		$classes   = Util::arrayify( $classes );
		$classes[] = 'button';

		if ( $secondary ) {
			$classes[] = 'button-secondary';
		} else {
			$classes[] = 'button-primary';
		}

		return self::a( $link, $text, $target, $classes, $id );
	}

	/**
	 * Start a table with similar layout to one created with the Settings API.
	 *
	 * @return void
	 */
	public static function admin_table_start( $class = 'form-table' ) {
		?><table class="<?php echo esc_attr( $class ); ?>" role="presentation"><tbody>
		<?php
	}

	/**
	 * End table.
	 *
	 * @return void
	 */
	public static function admin_table_end() {
		?>
		</tbody></table>
		<?php
	}
}
